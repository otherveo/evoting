<?php

namespace Tests\Feature;

use App\Livewire\Auth\ActivateAccountPage;
use App\Livewire\Concerns\HandlesSafeErrors;
use App\Livewire\Operator\ElectionIndexPage;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\StaffAccount;
use App\Services\SecurityAudit;
use App\Services\StaffAccountService;
use App\Services\TotpService;
use App\Services\VotingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesLocalVotingDatabase;

class HardeningTest extends TestCase
{
    use UsesLocalVotingDatabase;

    public function test_security_events_are_minimized_and_hash_chain_detects_changes(): void
    {
        $request = Request::create('/v/fixture-private', 'POST', [
            'password' => 'fixture-password', 'candidate_id' => 'fixture-vote',
        ], [], [], ['HTTP_USER_AGENT' => 'Chrome/1.0 fixture-secret']);
        $audit = app(SecurityAudit::class);
        $audit->record('qr_invalid', $request);
        $audit->record('qr_invalid', $request);
        $audit->record('rate_limited', $request);

        $events = $this->fixtureDb->query('select details from evoting.security_events order by id')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertCount(2, $events);
        foreach (['fixture-private', 'fixture-password', 'fixture-vote', 'fixture-secret', 'candidate_id'] as $private) {
            $this->assertStringNotContainsString($private, implode('', $events));
        }
        $this->assertSame('Chrome', json_decode($events[0], true)['browser']);
        $this->artisan('audit:verify')->assertSuccessful();
        $this->fixtureDb->exec("update evoting.security_events set details = jsonb_set(details, '{browser}', '\"Other\"')");
        $this->artisan('audit:verify')->assertFailed();
    }

    public function test_public_database_login_cannot_manage_staff_or_read_operator_results(): void
    {
        $rights = DB::selectOne("select current_user as login,
            has_any_column_privilege(current_user, 'evoting.staff_accounts', 'UPDATE') as manages_staff,
            has_any_column_privilege(current_user, 'evoting.elections', 'UPDATE') as changes_election,
            has_column_privilege(current_user, 'evoting.elections', 'access_key_hash', 'SELECT') as reads_key,
            has_table_privilege(current_user, 'evoting.sessions', 'UPDATE') as changes_sessions,
            has_function_privilege(current_user, 'evoting.fn_delete_election(uuid,uuid,text)', 'EXECUTE') as can_delete,
            has_function_privilege(current_user, 'evoting.fn_operator_election_results(uuid)', 'EXECUTE') as can_recap");
        $this->assertSame('evoting_app', $rights->login);
        foreach (['manages_staff', 'changes_election', 'reads_key', 'changes_sessions', 'can_delete', 'can_recap'] as $permission) {
            $this->assertFalse($rights->$permission, $permission);
        }
        $this->assertSame('evoting_staff', DB::connection('staff')->selectOne('select current_user as login')->login);
    }

    public function test_expired_and_consumed_enrollments_require_new_proof(): void
    {
        $totp = app(TotpService::class);
        $admin = StaffAccount::create(['username' => 'enrollment', 'full_name' => 'Admin Uji', 'role' => 'admin',
            'password_hash' => 'unused', 'is_active' => true, 'must_change_password' => false, 'totp_enabled' => false]);
        $tab = Livewire::actingAs($admin, 'staff')->test(ActivateAccountPage::class);
        $secret = session('pending_totp_enrollment.secret');
        $this->assertIsString($secret);
        $this->travel(11)->minutes();
        $tab->set('totpCode', $totp->getCurrentCode($secret))->call('confirmTotp')->assertHasErrors('totpCode');
        $this->assertFalse($admin->fresh()->totp_enabled);
        $this->travelBack();

        $tab = Livewire::test(ActivateAccountPage::class);
        $secret = session('pending_totp_enrollment.secret');
        $tab->set('totpCode', $totp->getCurrentCode($secret))->call('confirmTotp')->assertRedirect(route('admin.operators'));
        $this->assertNull(session('pending_totp_enrollment'));
        $this->assertTrue(session('staff_2fa_verified'));
        $this->assertSame($secret, $admin->fresh()->totp_secret);
        $this->actingAs($admin->fresh(), 'staff');
        Livewire::test(ActivateAccountPage::class)->assertRedirect(route('admin.operators'));
    }

    public function test_operator_reset_revokes_an_open_component_and_the_old_session(): void
    {
        $accounts = app(StaffAccountService::class);
        $operator = $accounts->createAccount('operator_test', 'Operator Test', 'operator')['account'];
        $operator->update(['must_change_password' => false]);
        $admin = $accounts->createAccount('admin_test', 'Admin Test', 'admin')['account'];
        $tab = Livewire::actingAs($operator, 'staff')->test(ElectionIndexPage::class);
        $accounts->resetOperatorPassword($operator, $admin);
        $operator->update(['must_change_password' => false]);
        $this->actingAs($operator->fresh(), 'staff');
        $tab->call('openCreateModal')->assertForbidden();
        $this->get(route('operator.elections'))->assertRedirect(route('login'));
    }

    public function test_staff_page_refreshes_authorization_on_its_livewire_http_route(): void
    {
        $staff = app(StaffAccountService::class)->createAccount('http_operator', 'Operator HTTP', 'operator')['account'];
        $staff->update(['must_change_password' => false]);
        $page = $this->actingAs($staff, 'staff')->get(route('operator.elections'))->assertOk();
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches));
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        $send = fn ($state) => $this->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $state, 'updates' => [],
            'calls' => [['method' => 'openCreateModal', 'params' => [], 'path' => '']],
        ]]], ['X-Livewire' => 'true', 'Origin' => config('app.url')]);
        $updated = $send($snapshot)->assertOk()->json('components.0.snapshot');
        $this->assertTrue(json_decode($updated, true)['data']['showCreateModal']);
        $staff->update(['is_active' => false]);
        $send($updated)->assertForbidden();
    }

    public function test_browser_payloads_can_sign_in_upload_and_cast_anonymous_ballot(): void
    {
        $operator = app(StaffAccountService::class)->createAccount('transport_operator', 'Operator Uji', 'operator');
        $operator['account']->update(['must_change_password' => false]);
        $headers = ['X-Livewire' => '1', 'Origin' => config('app.url')];
        $snapshot = function ($response) {
            $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches));

            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        };
        $send = fn ($current, $method, $params = [], $updates = []) => $this->postJson(Livewire::getUpdateUri(), [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => $current,
                'calls' => [['method' => $method, 'params' => $params, 'metadata' => ['type' => 'action']]],
                'updates' => (object) $updates,
            ]],
        ], $headers)->assertOk();

        $login = $snapshot($this->get(route('login'))->assertOk());
        $send($login, 'login', updates: ['username' => 'transport_operator', 'password' => $operator['temporary_password']])
            ->assertJsonPath('components.0.effects.redirect', route('operator.elections'));

        $election = Election::create(['name' => 'Pemilihan Transport', 'max_voter_rights' => 5]);
        $candidate = Candidate::on('staff')->create(['election_id' => $election->id, 'name' => 'Paslon Satu', 'number' => 1]);
        Candidate::on('staff')->create(['election_id' => $election->id, 'name' => 'Paslon Dua', 'number' => 2]);
        Storage::fake('tmp-for-tests');
        $photo = UploadedFile::fake()->createWithContent('paslon.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aB9sAAAAASUVORK5CYII=',
        ));
        $detail = $snapshot($this->get(route('operator.elections.detail', $election))->assertOk());
        $signed = $send($detail, '_startUpload', ['candidatePhoto', [[
            'name' => $photo->getClientOriginalName(), 'size' => $photo->getSize(), 'type' => $photo->getMimeType(),
        ]], false]);
        $url = $signed->json('components.0.effects.dispatches.0.params.url');
        $this->assertIsString($url);
        $paths = $this->call('POST', $url, [], [], ['files' => [$photo]], [
            'HTTP_ORIGIN' => config('app.url'),
            'CONTENT_TYPE' => 'multipart/form-data',
            'HTTP_ACCEPT' => 'application/json',
        ])->assertOk()->json('paths');
        $send($signed->json('components.0.snapshot'), '_finishUpload', ['candidatePhoto', $paths, false, false]);

        $voting = app(VotingService::class);
        $now = $voting->databaseNow();
        $this->assertTrue($voting->setElectionSchedule($election->id, $now->subMinute(), $now->addHour(), $operator['account']->id)['ok']);
        $qr = $voting->createVoterRight($election->id, 'Pemilih Uji', $operator['account']->id);
        $this->assertTrue($qr['ok']);
        auth('staff')->logout();
        $this->withCredentials()->withCookie(config('evoting.browser_cookie_name'), 'browser_fixture_hardening_123456');
        $ballot = $snapshot($this->get(route('voter.ballot', $qr['raw_token']))->assertOk());
        $started = $send($ballot, 'startVoting')->json('components.0.snapshot');
        $this->assertSame('memilih', json_decode($started, true)['data']['stage']);
        $finished = $send($started, 'confirmVote', [$candidate->id])->json('components.0.snapshot');
        $this->assertSame('selesai', json_decode($finished, true)['data']['stage']);
        $this->assertSame(1, $voting->integrityReport($election->id)['total_votes']);
    }

    public function test_registration_request_and_confirmed_name_count_are_checked_under_the_election_lock(): void
    {
        $operator = app(StaffAccountService::class)->createAccount('registrar', 'Registrar', 'operator')['account'];
        $operator->update(['must_change_password' => false]);
        $election = Election::create(['name' => 'Registration test', 'max_voter_rights' => 10]);
        foreach ([1, 2] as $number) {
            Candidate::on('staff')->create(['election_id' => $election->id, 'number' => $number, 'name' => 'Candidate '.$number]);
        }

        $voting = app(VotingService::class);
        $now = $voting->databaseNow();
        $this->assertTrue($voting->setElectionSchedule($election->id, $now->subMinute(), $now->addHour(), $operator->id)['ok']);
        $request = (string) Str::uuid();
        $issue = fn ($id, $count) => $voting->createVoterRight($election->id, 'Nama Sama', $operator->id, $id, $count);
        // A second database session must wait while the registrar owns the election row.
        $this->fixtureDb->beginTransaction();
        $lock = $this->fixtureDb->prepare('select id from evoting.elections where id = ? for update');
        $lock->execute([$election->id]);
        $staff = DB::connection('staff');
        $staff->statement("set lock_timeout = '150ms'");
        try {
            $issue($request, 0);
            $this->fail('Registration must serialize on the election row.');
        } catch (QueryException $e) {
            $this->assertSame('55P03', $e->getCode());
        } finally {
            $this->fixtureDb->rollBack();
            $staff->statement('reset lock_timeout');
        }
        $this->assertTrue($issue($request, 0)['ok']);
        $this->assertSame('already_issued', $issue($request, 0)['code']);
        $this->assertSame('duplicate_name', $issue((string) Str::uuid(), 0)['code']);
        $this->assertTrue($issue((string) Str::uuid(), 1)['ok']);
        $this->assertSame('duplicate_name', $issue((string) Str::uuid(), 1)['code']);
        $this->assertSame(2, $voting->integrityReport($election->id)['total_rights']);
    }

    public function test_database_errors_log_only_allowlisted_metadata(): void
    {
        $error = new QueryException('pgsql', 'select ?', ['synthetic-binding'], new \RuntimeException('synthetic-private-value'));
        Log::shouldReceive('error')->once()->withArgs(function ($event, $context) {
            $this->assertSame('Uji pencatatan', $event);
            $this->assertSame(['incident_id', 'component', 'exception_class', 'sql_state'], array_keys($context));
            $this->assertStringNotContainsString('synthetic-', json_encode($context));

            return true;
        });
        $component = new class extends Component
        {
            use HandlesSafeErrors;

            public function record(\Throwable $error): void
            {
                $this->reportSafeError($error, 'Uji pencatatan');
            }
        };
        $component->record($error);
    }
}
