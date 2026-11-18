<?php

namespace Tests\Feature;

use App\Livewire\Admin\OperatorIndexPage;
use App\Livewire\Auth\ActivateAccountPage;
use App\Livewire\Auth\LoginPage;
use App\Livewire\Auth\VerifyTwoFactorPage;
use App\Livewire\Operator\ElectionDetailPage;
use App\Livewire\Operator\ElectionIndexPage;
use App\Livewire\Operator\VoterMonitorPage;
use App\Livewire\Public\LiveRecapPage;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\StaffAccount;
use App\Services\StaffAccountService;
use App\Services\SupabaseStorageService;
use App\Services\TokenService;
use App\Services\TotpService;
use App\Services\VotingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class EvotingSystemTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_requires_totp_while_operator_logs_in_with_password_only(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();

        $admin = StaffAccount::create([
            'username' => 'test_admin_1',
            'full_name' => 'Admin Penguji',
            'password_hash' => Hash::make('SandiKuat123!'),
            'role' => 'admin',
            'is_active' => true,
            'totp_secret' => $secret,
            'totp_enabled' => true,
            'must_change_password' => false,
        ]);

        $operator = StaffAccount::create([
            'username' => 'test_operator_1',
            'full_name' => 'Operator Penguji',
            'password_hash' => Hash::make('SandiOperator123!'),
            'role' => 'operator',
            'is_active' => true,
            'totp_secret' => null,
            'totp_enabled' => false,
            'must_change_password' => false,
        ]);

        Livewire::test(LoginPage::class)
            ->set('username', 'test_admin_1')
            ->set('password', 'SandiKuat123!')
            ->call('login')
            ->assertRedirect(route('auth.2fa'));

        $this->actingAs($admin, 'staff')
            ->get(route('admin.operators'))
            ->assertRedirect(route('auth.2fa'));

        $validCode = $totp->getCurrentCode($secret);
        Livewire::actingAs($admin, 'staff')
            ->test(VerifyTwoFactorPage::class)
            ->set('code', $validCode)
            ->call('verify')
            ->assertRedirect(route('admin.operators'));

        auth('staff')->logout();
        session()->flush();

        Livewire::test(LoginPage::class)
            ->set('username', 'test_operator_1')
            ->set('password', 'SandiOperator123!')
            ->call('login')
            ->assertRedirect(route('operator.elections'));
    }

    public function test_role_isolation_between_admin_and_operator(): void
    {
        $totp = app(TotpService::class);
        $admin = StaffAccount::create([
            'username' => 'adm_role_test',
            'full_name' => 'Admin Role',
            'password_hash' => Hash::make('SandiKuat123!'),
            'role' => 'admin',
            'is_active' => true,
            'totp_secret' => $totp->generateSecret(),
            'totp_enabled' => true,
            'must_change_password' => false,
        ]);

        $operator = StaffAccount::create([
            'username' => 'op_role_test',
            'full_name' => 'Operator Role',
            'password_hash' => Hash::make('SandiKuat123!'),
            'role' => 'operator',
            'is_active' => true,
            'totp_secret' => null,
            'totp_enabled' => false,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin, 'staff')
            ->withSession(['staff_2fa_verified' => true])
            ->get(route('operator.elections'))
            ->assertStatus(403);

        $this->actingAs($operator, 'staff')
            ->get(route('admin.operators'))
            ->assertStatus(403);

        $this->actingAs($operator, 'staff')
            ->get(route('admin.audit-logs'))
            ->assertStatus(403);
    }

    public function test_first_login_activation_flow_for_operator_and_admin(): void
    {
        $accountService = app(StaffAccountService::class);
        $created = $accountService->createAccount('op_baru', 'Operator Baru', 'operator');
        $operator = $created['account'];
        $tempPw = $created['temporary_password'];

        $this->actingAs($operator, 'staff')
            ->get(route('operator.elections'))
            ->assertRedirect(route('auth.activate'));

        Livewire::actingAs($operator, 'staff')
            ->test(ActivateAccountPage::class)
            ->set('currentPassword', $tempPw)
            ->set('newPassword', 'SandiBaruOperator99!')
            ->set('newPasswordConfirmation', 'SandiBaruOperator99!')
            ->call('updatePassword')
            ->assertRedirect(route('operator.elections'));

        $this->assertFalse($operator->fresh()->must_change_password);
    }

    public function test_candidate_photo_upload_to_supabase_storage_and_public_url_access(): void
    {
        $operator = StaffAccount::create([
            'username' => 'op_photo_test',
            'full_name' => 'Operator Foto',
            'password_hash' => Hash::make('SandiOperator123!'),
            'role' => 'operator',
            'is_active' => true,
            'totp_secret' => null,
            'totp_enabled' => false,
            'must_change_password' => false,
        ]);

        $election = Election::create([
            'name' => 'Pemilihan Uji Foto Paslon',
            'max_voter_rights' => 50,
        ]);

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
        $uploadedFile = UploadedFile::fake()->createWithContent('paslon1.png', $pngBytes);

        $storage = app(SupabaseStorageService::class);
        $objectPath = null;

        try {
            Livewire::actingAs($operator, 'staff')
                ->test(ElectionDetailPage::class, ['election' => $election])
                ->set('candidateNumber', 1)
                ->set('candidateName', 'Paslon Uji Foto')
                ->set('candidateVision', 'Visi Paslon Uji')
                ->set('candidateMissionsText', "Misi Satu\nMisi Dua")
                ->set('candidatePhoto', $uploadedFile)
                ->call('saveCandidate')
                ->assertHasNoErrors()
                ->assertSet('showCandidateModal', false);

            $candidate = Candidate::query()->where('election_id', $election->id)->firstOrFail();
            $objectPath = $candidate->photo_path;

            $this->assertNotNull($objectPath);
            $this->assertMatchesRegularExpression(
                '/^'.preg_quote($election->id, '/').'\/'.preg_quote($candidate->id, '/').'-[a-f0-9]{8}\.png$/',
                (string) $objectPath
            );

            $publicUrl = $candidate->photoUrl();
            $response = Http::timeout(15)->get($publicUrl);
            $this->assertTrue($response->successful(), 'Public photo URL harus dapat diakses dengan HTTP 200.');
            $this->assertStringContainsString('image/png', (string) $response->header('Content-Type'));
        } finally {
            if ($objectPath) {
                $storage->deleteCandidatePhoto($objectPath);
            }
        }
    }

    public function test_failed_photo_upload_hides_raw_curl_error_and_shows_friendly_message(): void
    {
        $operator = StaffAccount::create([
            'username' => 'op_err_test',
            'full_name' => 'Operator Galat',
            'password_hash' => Hash::make('SandiOperator123!'),
            'role' => 'operator',
            'is_active' => true,
            'totp_secret' => null,
            'totp_enabled' => false,
            'must_change_password' => false,
        ]);

        $election = Election::create([
            'name' => 'Pemilihan Uji Galat Upload',
            'max_voter_rights' => 50,
        ]);

        // Simulasikan kegagalan jaringan cURL mentah dari layanan penyimpanan
        $this->app->bind(SupabaseStorageService::class, function () {
            return new class extends SupabaseStorageService
            {
                public function uploadCandidatePhoto(string $electionId, string $candidateId, UploadedFile $file): string
                {
                    throw new \Exception('cURL error 60: SSL certificate problem: unable to get local issuer certificate');
                }
            };
        });

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
        $file = UploadedFile::fake()->createWithContent('kandidat.png', $pngBytes);

        Livewire::actingAs($operator, 'staff')
            ->test(ElectionDetailPage::class, ['election' => $election])
            ->set('candidateNumber', 1)
            ->set('candidateName', 'Paslon Galat')
            ->set('candidatePhoto', $file)
            ->call('saveCandidate')
            ->assertHasErrors(['candidatePhoto'])
            ->assertDontSee('cURL error 60')
            ->assertDontSee('unable to get local issuer certificate');
    }

    public function test_full_election_lifecycle_and_database_integrity(): void
    {
        $voting = app(VotingService::class);
        $dbNow = $voting->databaseNow();

        $operator = StaffAccount::create([
            'username' => 'op_lifecycle',
            'full_name' => 'Operator Siklus',
            'password_hash' => Hash::make('SandiOperator123!'),
            'role' => 'operator',
            'is_active' => true,
            'totp_secret' => null,
            'totp_enabled' => false,
            'must_change_password' => false,
        ]);

        $election = Election::create([
            'name' => 'Pemilihan Ketua OSIS Uji',
            'max_voter_rights' => 2,
        ]);

        $this->assertSame('persiapan', $election->currentPhase());

        $schedFail = $voting->setElectionSchedule(
            $election->id,
            $dbNow->subMinute(),
            $dbNow->addHours(2),
            $operator->id
        );
        $this->assertFalse($schedFail['ok']);
        $this->assertSame('too_few_candidates', $schedFail['code']);

        $c1 = Candidate::create([
            'election_id' => $election->id,
            'number' => 1,
            'name' => 'Paslon Satu',
            'vision' => 'Visi Satu',
            'missions' => ['Misi 1A', 'Misi 1B'],
        ]);
        $c2 = Candidate::create([
            'election_id' => $election->id,
            'number' => 2,
            'name' => 'Paslon Dua',
            'vision' => 'Visi Dua',
            'missions' => ['Misi 2A'],
        ]);

        $earlyRight = $voting->createVoterRight($election->id, 'Andi Pratama', $operator->id);
        $this->assertFalse($earlyRight['ok']);
        $this->assertSame('wrong_phase', $earlyRight['code']);

        $schedOk = $voting->setElectionSchedule(
            $election->id,
            $dbNow->subMinute(),
            $dbNow->addHours(2),
            $operator->id
        );
        $this->assertTrue($schedOk['ok']);
        $this->assertSame('berlangsung', $election->currentPhase());

        $vr1 = $voting->createVoterRight($election->id, 'Andi Pratama', $operator->id);
        $this->assertTrue($vr1['ok']);
        $this->assertNotNull($vr1['raw_token']);

        Livewire::actingAs($operator, 'staff')
            ->test(VoterMonitorPage::class, ['election' => $election])
            ->set('voterName', '  andi   pratama ')
            ->call('registerVoter')
            ->assertHasErrors(['voterName']);

        Livewire::actingAs($operator, 'staff')
            ->test(VoterMonitorPage::class, ['election' => $election])
            ->set('voterName', 'Andi Pratama')
            ->set('confirmDifferentPerson', true)
            ->call('registerVoter')
            ->assertSet('showQrModal', true);

        $vr3 = $voting->createVoterRight($election->id, 'Siti Aminah', $operator->id);
        $this->assertFalse($vr3['ok']);
        $this->assertSame('limit_reached', $vr3['code']);

        $this->get(route('voter.ballot', ['token' => $vr1['raw_token']]))
            ->assertStatus(200)
            ->assertSee($election->name)
            ->assertDontSee('Andi Pratama');

        $browserA = 'browser_alpha_12345678901234567890';
        $browserB = 'browser_beta_12345678901234567890';

        $startA = $voting->startVoting($vr1['raw_token'], $browserA, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertTrue($startA['ok']);

        $startB = $voting->startVoting($vr1['raw_token'], $browserB, 'Mozilla/5.0 Other', '10.0.0.2');
        $this->assertFalse($startB['ok']);
        $this->assertSame('wrong_browser', $startB['code']);

        $resumeA = $voting->openQr($vr1['raw_token'], $browserA, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertTrue($resumeA['ok']);
        $this->assertSame('sedang_memilih', $resumeA['status']);

        $reissueWhileActive = $voting->reissueToken($vr1['voter_right_id'], $operator->id, 'Coba terbit ulang');
        $this->assertFalse($reissueWhileActive['ok']);
        $this->assertSame('wrong_status', $reissueWhileActive['code']);

        $resetOk = $voting->resetSession($vr1['voter_right_id'], $operator->id, 'Baterai pemilih habis');
        $this->assertTrue($resetOk['ok']);

        $reissued = $voting->reissueToken($vr1['voter_right_id'], $operator->id, 'QR lama rusak');
        $this->assertTrue($reissued['ok']);
        $newToken = (string) $reissued['raw_token'];

        $oldOpen = $voting->openQr($vr1['raw_token'], $browserA, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertFalse($oldOpen['ok']);
        $this->assertSame('invalid_token', $oldOpen['code']);

        $this->assertTrue($voting->startVoting($newToken, $browserA, 'Mozilla/5.0 Test', '10.0.0.1')['ok']);
        $confirm1 = $voting->confirmVote($newToken, $browserA, $c1->id, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertTrue($confirm1['ok']);

        $confirmDuplicate = $voting->confirmVote($newToken, $browserA, $c2->id, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertFalse($confirmDuplicate['ok']);
        $this->assertSame('already_voted', $confirmDuplicate['code']);

        $integrity = $voting->integrityReport($election->id);
        $this->assertTrue($integrity['is_consistent']);
        $this->assertSame(1, $integrity['total_votes']);
        $this->assertSame(1, $integrity['total_voted']);

        $earlyResults = $voting->electionResults($election->id);
        $this->assertFalse($earlyResults['ok']);
        $this->assertSame('not_ended', $earlyResults['code']);

        $this->get(route('public.turnout', $election))
            ->assertStatus(200)
            ->assertSee($election->name)
            ->assertDontSee('Andi Pratama');

        $election->refresh();
        $endNow = $voting->setElectionSchedule(
            $election->id,
            CarbonImmutable::instance($election->starts_at),
            $voting->databaseNow(),
            $operator->id
        );
        $this->assertTrue($endNow['ok']);
        $this->assertSame('ditutup', $election->currentPhase());

        $finalResults = $voting->electionResults($election->id);
        $this->assertTrue($finalResults['ok']);
        $this->assertCount(2, $finalResults['items']);
        $this->assertSame(1, $finalResults['items'][0]['vote_count']);
        $this->assertSame(0, $finalResults['items'][1]['vote_count']);
    }

    public function test_qr_custom_logo_mode_and_admin_fallback_toggle_and_app_name_branding(): void
    {
        $tokens = app(TokenService::class);
        $totp = app(TotpService::class);

        $admin = StaffAccount::create([
            'username' => 'admin_qr_mode',
            'full_name' => 'Admin QR Mode',
            'password_hash' => Hash::make('AdminStrongPass123!'),
            'role' => 'admin',
            'is_active' => true,
            'totp_secret' => $totp->generateSecret(),
            'totp_enabled' => true,
            'must_change_password' => false,
        ]);

        // Default mode is custom with embedded center logo
        $customSvg = $tokens->renderQrSvg('https://example.test/v/sample-token', 240, TokenService::QR_MODE_CUSTOM);
        $this->assertStringContainsString('<image ', $customSvg);
        $this->assertStringContainsString('SMPN7-e-voting_logo.png', $customSvg);

        // Standard fallback mode renders QR without embedded center logo
        $standardSvg = $tokens->renderQrSvg('https://example.test/v/sample-token', 240, TokenService::QR_MODE_STANDARD);
        $this->assertStringNotContainsString('<image ', $standardSvg);

        // Admin can toggle QR mode to standard and back to custom from OperatorIndexPage
        $this->withSession(['staff_2fa_verified' => true]);
        Livewire::actingAs($admin, 'staff')
            ->test(OperatorIndexPage::class)
            ->call('setQrMode', TokenService::QR_MODE_STANDARD)
            ->assertSet('showCreateModal', false);

        $this->assertSame(TokenService::QR_MODE_STANDARD, $tokens->getQrMode());
        $this->assertStringNotContainsString('<image ', $tokens->renderQrSvg('https://example.test/v/sample-token', 240));

        Livewire::actingAs($admin, 'staff')
            ->test(OperatorIndexPage::class)
            ->call('setQrMode', TokenService::QR_MODE_CUSTOM);

        $this->assertSame(TokenService::QR_MODE_CUSTOM, $tokens->getQrMode());
        $this->assertStringContainsString('<image ', $tokens->renderQrSvg('https://example.test/v/sample-token', 240));

        // Header & root landing page use SMPN7-e-voting_logo.png and DigiVoting brand
        config(['app.name' => '']);
        $this->get(route('home'))
            ->assertStatus(200)
            ->assertSee('SMPN7-e-voting_logo.png')
            ->assertSee('DigiVoting')
            ->assertSee('Sisi Operator Pemilihan')
            ->assertSee('Sisi Anonim / Pemilih')
            ->assertDontSee('SuaraSekolah');

        $this->get(route('admin.operators'))
            ->assertStatus(200)
            ->assertSee('SMPN7-e-voting_logo.png')
            ->assertSee('DigiVoting')
            ->assertDontSee('SuaraSekolah');
    }

    public function test_update_v1_features_and_bug_fixes(): void
    {
        $voting = app(VotingService::class);
        $tokens = app(TokenService::class);
        $storage = app(SupabaseStorageService::class);
        $dbNow = $voting->databaseNow();

        $operator = StaffAccount::create([
            'username' => 'op_update_v1',
            'full_name' => 'Operator Update V1',
            'password_hash' => Hash::make('SandiOperator123!'),
            'role' => 'operator',
            'is_active' => true,
            'totp_secret' => null,
            'totp_enabled' => false,
            'must_change_password' => false,
        ]);

        // BUG 1: Spam-calling createElection 10x creates only 1 election
        $uniqueSpamName = 'Pilketis Spam Uji '.uniqid();
        $component = Livewire::actingAs($operator, 'staff')
            ->test(ElectionIndexPage::class)
            ->set('name', $uniqueSpamName)
            ->set('maxVoterRights', 50);

        for ($i = 0; $i < 10; $i++) {
            $component->call('createElection');
        }

        $this->assertSame(1, Election::where('name', $uniqueSpamName)->count());
        $election = Election::where('name', $uniqueSpamName)->firstOrFail();

        // PERUBAHAN 1: Bounds 3..10000
        Livewire::actingAs($operator, 'staff')
            ->test(ElectionDetailPage::class, ['election' => $election])
            ->set('name', $uniqueSpamName)
            ->set('maxVoterRights', 2)
            ->call('saveElectionInfo')
            ->assertHasErrors(['maxVoterRights']);

        Livewire::actingAs($operator, 'staff')
            ->test(ElectionDetailPage::class, ['election' => $election])
            ->set('name', $uniqueSpamName)
            ->set('maxVoterRights', 10001)
            ->call('saveElectionInfo')
            ->assertHasErrors(['maxVoterRights']);

        // BUG 2 (a): Rename new election (persiapan without schedule)
        $renamedA = 'Pilketis Rename A '.uniqid();
        Livewire::actingAs($operator, 'staff')
            ->test(ElectionDetailPage::class, ['election' => $election])
            ->set('name', $renamedA)
            ->set('maxVoterRights', 10)
            ->call('saveElectionInfo')
            ->assertHasNoErrors();
        $this->assertSame($renamedA, $election->fresh()->name);

        $c1 = Candidate::create(['election_id' => $election->id, 'number' => 1, 'name' => 'Paslon Alpha']);
        $c2 = Candidate::create(['election_id' => $election->id, 'number' => 2, 'name' => 'Paslon Beta']);

        // BUG 2 (b): Rename scheduled election (persiapan with future schedule)
        $voting->setElectionSchedule($election->id, $dbNow->addMinutes(30), $dbNow->addHours(3), $operator->id);
        $renamedB = 'Pilketis Rename B '.uniqid();
        Livewire::actingAs($operator, 'staff')
            ->test(ElectionDetailPage::class, ['election' => $election->fresh()])
            ->set('name', $renamedB)
            ->set('maxVoterRights', 12)
            ->call('saveElectionInfo')
            ->assertHasNoErrors();
        $this->assertSame($renamedB, $election->fresh()->name);

        // BUG 2 (c): Rename ongoing election (berlangsung)
        $voting->setElectionSchedule($election->id, $dbNow->subMinute(), $dbNow->addHours(3), $operator->id);
        $renamedC = 'Pilketis Rename C '.uniqid();
        Livewire::actingAs($operator, 'staff')
            ->test(ElectionDetailPage::class, ['election' => $election->fresh()])
            ->set('name', $renamedC)
            ->set('maxVoterRights', 15)
            ->call('saveElectionInfo')
            ->assertHasNoErrors();
        $this->assertSame($renamedC, $election->fresh()->name);

        // PERUBAHAN 3.2: Shared-device operator approval gate
        $vr1 = $voting->createVoterRight($election->id, 'Nayla Putri', $operator->id);
        $vr2 = $voting->createVoterRight($election->id, 'Lindawaty Nono', $operator->id);
        $vr3 = $voting->createVoterRight($election->id, 'Budi Hartono', $operator->id);

        $sharedBrowser = 'shared_browser_device_80884E_123456';

        // Voter 1 starts on the device (first user -> no approval needed)
        $start1 = $voting->startVoting($vr1['raw_token'], $sharedBrowser, 'Chrome Android', '10.0.0.10');
        $this->assertTrue($start1['ok']);
        $this->assertSame('ok', $start1['code']);

        // Voter 2 starts on the SAME device -> locked with approval_required
        $start2 = $voting->startVoting($vr2['raw_token'], $sharedBrowser, 'Chrome Android', '10.0.0.10');
        $this->assertFalse($start2['ok']);
        $this->assertSame('approval_required', $start2['code']);

        // Check permission while pending -> returns pending
        $checkPending = $voting->checkDeviceApproval($vr2['raw_token'], $sharedBrowser);
        $this->assertSame('pending', $checkPending['code']);

        // Operator approves Voter 2
        $approveRes = $voting->decideDeviceApproval($vr2['voter_right_id'], $operator->id, 'setujui');
        $this->assertTrue($approveRes['ok']);

        $checkApproved = $voting->checkDeviceApproval($vr2['raw_token'], $sharedBrowser);
        $this->assertSame('approved', $checkApproved['code']);

        // Voter 3 starts on the SAME device -> locked -> Operator rejects Voter 3
        $start3 = $voting->startVoting($vr3['raw_token'], $sharedBrowser, 'Chrome Android', '10.0.0.10');
        $this->assertSame('approval_required', $start3['code']);

        $rejectRes = $voting->decideDeviceApproval($vr3['voter_right_id'], $operator->id, 'tolak');
        $this->assertTrue($rejectRes['ok']);

        $checkRejected = $voting->checkDeviceApproval($vr3['raw_token'], $sharedBrowser);
        $this->assertSame('rejected', $checkRejected['code']);
        $this->assertSame(
            'Aksesmu telah ditolak oleh operator, silakan minta QR baru jika kamu memang punya hak suara',
            $checkRejected['message']
        );

        // Old QR of Voter 3 is permanently revoked
        $reopen3 = $voting->openQr($vr3['raw_token'], $sharedBrowser, 'Chrome Android', '10.0.0.10');
        $this->assertFalse($reopen3['ok']);
        $this->assertSame('device_rejected', $reopen3['code']);

        // PERUBAHAN 7: Real-time recap access key (64 chars, 1 active key, revocable)
        $key1 = $voting->issueAccessKey($election->id, $operator->id);
        $this->assertTrue($key1['ok']);
        $this->assertSame(64, strlen((string) $key1['raw_key']));
        $this->assertDoesNotMatchRegularExpression('/\s/', (string) $key1['raw_key']);

        // Issuing key 2 revokes key 1
        $key2 = $voting->issueAccessKey($election->id, $operator->id);
        $this->assertTrue($key2['ok']);
        $this->assertFalse($voting->liveElectionResults($election->id, $tokens->hashToken((string) $key1['raw_key']))['ok']);
        $this->assertTrue($voting->liveElectionResults($election->id, $tokens->hashToken((string) $key2['raw_key']))['ok']);

        Livewire::test(LiveRecapPage::class, ['election' => $election->fresh()])
            ->assertSee('PERINGATAN: Halaman ini menampilkan hasil sementara secara real-time.')
            ->set('accessKeyInput', (string) $key2['raw_key'])
            ->call('verifyAccessKey')
            ->assertSet('isAuthorized', true)
            ->assertSee('Paslon Alpha');

        // PERUBAHAN 6: Delete election is rejected while berlangsung, and succeeds when ditutup
        $delWhileOngoing = $voting->deleteElection($election->id, $operator->id, $renamedC, $storage);
        $this->assertFalse($delWhileOngoing['ok']);
        $this->assertSame('wrong_phase', $delWhileOngoing['code']);

        // End election so phase becomes ditutup (keep starts_at locked to existing value)
        $election->refresh();
        $endSched = $voting->setElectionSchedule(
            $election->id,
            CarbonImmutable::instance($election->starts_at),
            $voting->databaseNow()->subSecond(),
            $operator->id
        );
        $this->assertTrue($endSched['ok']);
        $delOk = $voting->deleteElection($election->id, $operator->id, $renamedC, $storage);
        $this->assertTrue($delOk['ok']);
        $this->assertNull(Election::find($election->id));
    }
}
