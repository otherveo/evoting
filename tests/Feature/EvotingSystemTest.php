<?php

namespace Tests\Feature;

use App\Livewire\Admin\OperatorIndexPage;
use App\Livewire\Auth\ActivateAccountPage;
use App\Livewire\Auth\LoginPage;
use App\Livewire\Auth\VerifyTwoFactorPage;
use App\Livewire\Operator\ElectionDetailPage;
use App\Livewire\Operator\ElectionIndexPage;
use App\Livewire\Operator\ElectionRecapPage;
use App\Livewire\Operator\VoterMonitorPage;
use App\Livewire\Public\LiveRecapPage;
use App\Livewire\Voter\BallotPage;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\PublicElection;
use App\Models\StaffAccount;
use App\Services\StaffAccountService;
use App\Services\SupabaseStorageService;
use App\Services\TokenService;
use App\Services\TotpService;
use App\Services\VotingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\UsesLocalVotingDatabase;

class EvotingSystemTest extends TestCase
{
    use UsesLocalVotingDatabase;

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

    public function test_public_candidate_directory_respects_schedule_and_photo_visibility(): void
    {
        $index = route('public.candidates.index');
        $this->get(route('home'))->assertOk()->assertSee('Lihat daftar paslon');
        $this->get($index)->assertOk()->assertSee('Belum ada daftar paslon');

        $operator = StaffAccount::create([
            'username' => 'operator-paslon',
            'full_name' => 'Operator Paslon',
            'password_hash' => Hash::make('SandiUjiOperator123!'),
            'role' => 'operator',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $scheduled = ['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'max_voter_rights' => 30];
        $election = Election::create(['name' => 'Pemilihan Terjadwal'] + $scheduled);
        $draft = Election::create(['name' => 'Pemilihan Draf', 'max_voter_rights' => 30]);
        Candidate::on('staff')->create(['election_id' => $draft->id, 'number' => 1, 'name' => 'Paslon Draf']);
        Candidate::on('staff')->create(['election_id' => $election->id, 'number' => 2, 'name' => 'Paslon Tanpa Foto']);
        $candidate = Candidate::on('staff')->create([
            'election_id' => $election->id,
            'number' => 1,
            'name' => 'Paslon Dengan Foto',
            'photo_path' => 'public-example.jpg',
            'vision' => 'Visi lengkap',
            'missions' => ['Misi satu', 'Misi dua'],
        ]);

        $url = route('public.candidates', $election);
        $this->get($index)->assertRedirect($url);
        $this->get(route('public.candidates', $draft))->assertNotFound();
        $this->get($url)->assertOk()
            ->assertSeeInOrder(['Paslon Nomor 01', 'Paslon Nomor 02'])
            ->assertSee('Lihat paslon ini')
            ->assertSee($candidate->photoUrl())
            ->assertSee('Visi lengkap')
            ->assertSee('Misi satu')
            ->assertSee('Misi dua');

        $other = Election::create(['name' => 'Pemilihan Lain'] + $scheduled);
        Candidate::on('staff')->create(['election_id' => $other->id, 'number' => 1, 'name' => 'Paslon Lain']);
        $this->get($index)->assertOk()
            ->assertSee($url)
            ->assertSee(route('public.candidates', $other))
            ->assertDontSee('Pemilihan Draf');

        $editor = Livewire::actingAs($operator, 'staff')->test(ElectionDetailPage::class, ['election' => $election]);
        $editor->call('setCandidatePublicPhoto', $candidate->id, false)->assertDispatched('toast');
        $this->assertFalse($candidate->fresh()->public_photo_visible);
        $this->get($url)->assertOk()->assertSee('Paslon Nomor 01')->assertDontSee('public-example.jpg');
        $editor->call('setCandidatePublicPhoto', $candidate->id, true)->assertDispatched('toast');
        $this->get($url)->assertOk()->assertSee($candidate->photoUrl());
    }

    public function test_public_candidate_directory_excludes_election_names_with_practice_prefixes(): void
    {
        $index = route('public.candidates.index');
        $schedule = ['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'max_voter_rights' => 30];

        foreach (['Train Pilketos', 'TRAINING Pilketos', 'Simulasi Pilketos', 'Simulations Pilketos', 'Tes Pilketos', 'Test Pilketos', 'Testing Pilketos', 'Latih Pilketos', 'Latihan Pilketos'] as $name) {
            $election = Election::create(['name' => $name] + $schedule);
            Candidate::on('staff')->create(['election_id' => $election->id, 'number' => 1, 'name' => 'Paslon Uji']);

            $this->get(route('public.candidates', $election))->assertNotFound();
        }

        $this->get($index)->assertOk()->assertSee('Belum ada daftar paslon');

        $election = Election::create(['name' => 'Pilketos setelah simulasi'] + $schedule);
        Candidate::on('staff')->create(['election_id' => $election->id, 'number' => 1, 'name' => 'Paslon Resmi']);

        $url = route('public.candidates', $election);
        $this->get($index)->assertRedirect($url);
        $this->get($index.'?pilih=1')->assertOk()
            ->assertSee($election->name)
            ->assertDontSee('TRAINING Pilketos');
        $this->get($url)->assertOk()->assertSee($election->name);
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

        $c1 = Candidate::on('staff')->create([
            'election_id' => $election->id,
            'number' => 1,
            'name' => 'Paslon Satu',
            'vision' => 'Visi Satu',
            'missions' => ['Misi 1A', 'Misi 1B'],
        ]);
        $c2 = Candidate::on('staff')->create([
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
            ->call('registerVoter', '  andi   pratama ', 0)
            ->assertHasErrors(['voterName']);

        Livewire::actingAs($operator, 'staff')
            ->test(VoterMonitorPage::class, ['election' => $election])
            ->call('registerVoter', 'Andi Pratama', 1)
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

    public function test_device_limit_two_uses_per_election_and_admin_block(): void
    {
        $voting = app(VotingService::class);
        $dbNow = $voting->databaseNow();

        $operator = StaffAccount::create([
            'username' => 'op_device_limit',
            'full_name' => 'Operator Batas Perangkat',
            'password_hash' => Hash::make('SandiOperator123!'),
            'role' => 'operator',
            'is_active' => true,
            'totp_secret' => null,
            'totp_enabled' => false,
            'must_change_password' => false,
        ]);

        $admin = StaffAccount::create([
            'username' => 'adm_device_limit',
            'full_name' => 'Admin Batas Perangkat',
            'password_hash' => Hash::make('SandiAdmin123!'),
            'role' => 'admin',
            'is_active' => true,
            'totp_secret' => null,
            'totp_enabled' => false,
            'must_change_password' => false,
        ]);

        $election = Election::create([
            'name' => 'Pemilihan Ketua OSIS Batas Perangkat',
            'max_voter_rights' => 5,
        ]);

        $c1 = Candidate::on('staff')->create([
            'election_id' => $election->id,
            'number' => 1,
            'name' => 'Paslon Satu',
            'vision' => 'Visi Satu',
            'missions' => ['Misi 1A'],
        ]);
        $c2 = Candidate::on('staff')->create([
            'election_id' => $election->id,
            'number' => 2,
            'name' => 'Paslon Dua',
            'vision' => 'Visi Dua',
            'missions' => ['Misi 2A'],
        ]);

        $this->assertTrue($voting->setElectionSchedule(
            $election->id,
            $dbNow->subMinute(),
            $dbNow->addHours(2),
            $operator->id
        )['ok']);

        $vr1 = $voting->createVoterRight($election->id, 'Andi Pratama', $operator->id);
        $vr2 = $voting->createVoterRight($election->id, 'Budi Santoso', $operator->id);
        $vr3 = $voting->createVoterRight($election->id, 'Citra Lestari', $operator->id);
        $this->assertTrue($vr1['ok']);
        $this->assertTrue($vr2['ok']);
        $this->assertTrue($vr3['ok']);

        $browserX = 'browser_v7_shared_1234567890123456';
        $browserY = 'browser_v7_lain_12345678901234567';

        // Penggunaan ke-1: OK; refresh token yang sama tetap dihitung 1 penggunaan.
        $this->assertTrue($voting->openQr($vr1['raw_token'], $browserX, 'Mozilla/5.0 Test', '10.0.0.1')['ok']);
        $this->assertTrue($voting->openQr($vr1['raw_token'], $browserX, 'Mozilla/5.0 Test', '10.0.0.1')['ok']);
        $this->assertTrue($voting->startVoting($vr1['raw_token'], $browserX, 'Mozilla/5.0 Test', '10.0.0.1')['ok']);

        // Penggunaan ke-2: OK saat membuka link (gerbang persetujuan baru di mulai memilih).
        $this->assertTrue($voting->openQr($vr2['raw_token'], $browserX, 'Mozilla/5.0 Test', '10.0.0.1')['ok']);

        // Penggunaan ke-3 di perangkat yang sama: URL sepenuhnya tidak valid.
        $open3 = $voting->openQr($vr3['raw_token'], $browserX, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertFalse($open3['ok']);
        $this->assertSame('device_limit_reached', $open3['code']);
        $this->assertSame('Satu perangkat yang sama hanya diizinkan maksimal 2x digunakan. Silakan cari perangkat lain untuk melanjutkan proses.', $open3['message']);

        // Perangkat lain tidak terpengaruh.
        $this->assertTrue($voting->openQr($vr3['raw_token'], $browserY, 'Mozilla/5.0 Test', '10.0.0.2')['ok']);

        // Penolakan tercatat di log audit.
        $this->assertTrue(DB::connection('staff')->table('evoting.audit_logs')
            ->where('action', 'tolak_batas_perangkat')->exists());

        // Penggunaan ke-2 tetap butuh persetujuan operator saat mulai memilih.
        $start2 = $voting->startVoting($vr2['raw_token'], $browserX, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertFalse($start2['ok']);
        $this->assertSame('approval_required', $start2['code']);

        // Pertahanan berlapis: mulai memilih langsung untuk penggunaan ke-3 tetap ditolak.
        $start3 = $voting->startVoting($vr3['raw_token'], $browserX, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertFalse($start3['ok']);
        $this->assertSame('device_limit_reached', $start3['code']);

        // Penggunaan ke-1 tetap bisa memilih sampai selesai.
        $confirm1 = $voting->confirmVote($vr1['raw_token'], $browserX, $c1->id, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertTrue($confirm1['ok']);

        // Blokir manual Admin: reset sesi vr2 dulu (sedang menunggu persetujuan).
        $this->assertTrue($voting->resetSession($vr2['voter_right_id'], $operator->id, 'Uji blokir perangkat')['ok']);
        $blocked = $voting->blockDevice($election->id, $browserX, $admin->id, 'Uji blokir v7');
        $this->assertTrue($blocked['ok']);

        $openBlocked = $voting->openQr($vr2['raw_token'], $browserX, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertFalse($openBlocked['ok']);
        $this->assertSame('device_blocked', $openBlocked['code']);

        // Buka blokir: perangkat bisa dipakai lagi (vr2 sudah tercatat sebagai penggunaan ke-2).
        $unblocked = $voting->unblockDevice($election->id, $browserX, $admin->id);
        $this->assertTrue($unblocked['ok']);
        $this->assertTrue($voting->openQr($vr2['raw_token'], $browserX, 'Mozilla/5.0 Test', '10.0.0.1')['ok']);

        // Halaman admin (Log Audit perangkat) bisa dibuka oleh Admin.
        $this->actingAs($admin, 'staff')
            ->withSession(['staff_2fa_verified' => true])
            ->get(route('admin.device-usage'))
            ->assertStatus(200);
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

        $c1 = Candidate::on('staff')->create(['election_id' => $election->id, 'number' => 1, 'name' => 'Paslon Alpha']);
        $c2 = Candidate::on('staff')->create(['election_id' => $election->id, 'number' => 2, 'name' => 'Paslon Beta']);

        // BUG 2 (b): Rename scheduled election (persiapan with future schedule)
        $voting->setElectionSchedule($election->id, $dbNow->addMinutes(30), $dbNow->addHours(3), $operator->id);
        $this->travel(3)->seconds(); // Separate edits beyond the two-second duplicate-submit window.
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
        $this->travel(3)->seconds();
        $renamedC = 'Pilketis Rename C '.uniqid();
        Livewire::actingAs($operator, 'staff')
            ->test(ElectionDetailPage::class, ['election' => $election->fresh()])
            ->set('name', $renamedC)
            ->set('maxVoterRights', 50)
            ->call('saveElectionInfo')
            ->assertHasNoErrors();
        $this->assertSame($renamedC, $election->fresh()->name);

        $this->travelBack();

        // PERUBAHAN 3.2 + UPDATE V2: Shared-device operator approval gate & issuer isolation
        $operatorB = StaffAccount::create([
            'username' => 'op_b_update_v2',
            'full_name' => 'Operator Kedua V2',
            'password_hash' => Hash::make('SandiOperator123!'),
            'role' => 'operator',
            'is_active' => true,
            'totp_enabled' => false,
            'must_change_password' => false,
        ]);

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
        $this->assertSame('wrong_browser', $voting->checkDeviceApproval($vr2['raw_token'], 'other_browser')['code']);
        $checkPending = $voting->checkDeviceApproval($vr2['raw_token'], $sharedBrowser);
        $this->assertSame('pending', $checkPending['code']);

        // UPDATE V2: Operator A (issuer) sees the pending approval popup on monitor page
        Livewire::actingAs($operator, 'staff')
            ->test(VoterMonitorPage::class, ['election' => $election->fresh()])
            ->assertSee('Permintaan Persetujuan Perangkat Bersama')
            ->assertSee('Lihat 1 orang lainnya')
            ->assertDontSee('Perangkat ini juga dipakai oleh:');

        // UPDATE V2: Operator B (non-issuer) does NOT see the pending approval popup
        Livewire::actingAs($operatorB, 'staff')
            ->test(VoterMonitorPage::class, ['election' => $election->fresh()])
            ->assertDontSee('Permintaan Persetujuan Perangkat Bersama');

        // UPDATE V2: Operator B cannot decide Operator A's QR approval request
        $wrongOpRes = $voting->decideDeviceApproval($vr2['voter_right_id'], $operatorB->id, 'setujui');
        $this->assertFalse($wrongOpRes['ok']);
        $this->assertSame('bukan_penerbit', $wrongOpRes['code']);
        $this->assertSame(
            'Permintaan ini hanya dapat diputuskan oleh operator yang menerbitkan QR.',
            $wrongOpRes['message']
        );

        // Status remains pending after Operator B's rejected attempt and no audit log is created
        $this->assertSame('pending', $voting->checkDeviceApproval($vr2['raw_token'], $sharedBrowser)['code']);
        $this->assertFalse(
            DB::connection('staff')->table('evoting.audit_logs')
                ->where('actor_id', $operatorB->id)
                ->where('object_id', $vr2['voter_right_id'])
                ->exists()
        );

        // Operator A approves Voter 2
        $approveRes = $voting->decideDeviceApproval($vr2['voter_right_id'], $operator->id, 'setujui');
        $this->assertTrue($approveRes['ok']);

        $checkApproved = $voting->checkDeviceApproval($vr2['raw_token'], $sharedBrowser);
        $this->assertSame('approved', $checkApproved['code']);

        // Voter 3 starts on the SAME device -> locked -> Operator A rejects Voter 3
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

        // Existing rows without an issuer require explicit reissue before approval.
        $legacyPair = $tokens->generateVoterToken();
        $legacy = $this->fixtureDb->prepare("insert into evoting.voter_rights
            (election_id, name, name_normalized, token_hash) values (?, 'Legacy', 'legacy', ?) returning id");
        $legacy->execute([$election->id, $legacyPair['hash']]);
        $legacyId = $legacy->fetchColumn();
        $this->assertSame('approval_required', $voting->startVoting($legacyPair['raw'], $sharedBrowser, 'UA', null)['code']);
        $this->assertSame('bukan_penerbit', $voting->decideDeviceApproval($legacyId, $operatorB->id, 'setujui')['code']);
        $this->assertTrue($voting->resetSession($legacyId, $operatorB->id, 'Verifikasi ulang di meja petugas')['ok']);
        $reissued = $voting->reissueToken($legacyId, $operatorB->id, 'QR lama tanpa penerbit');
        $this->assertSame('approval_required', $voting->startVoting($reissued['raw_token'], $sharedBrowser, 'UA', null)['code']);
        $this->assertSame('bukan_penerbit', $voting->decideDeviceApproval($legacyId, $operator->id, 'setujui')['code']);
        $this->assertTrue($voting->decideDeviceApproval($legacyId, $operatorB->id, 'setujui')['ok']);

        // UPDATE V4 BAGIAN A: Monitor pagination (25 per page) + poll only on page 1
        DB::connection('staff')->select(
            "select vr.* from generate_series(1, 23) g cross join lateral evoting.fn_create_voter_right(?::uuid, 'Pemilih Paginasi ' || g, 'pemilih paginasi ' || g, encode(sha256(('tok_v4_' || g || '_' || ?)::bytea), 'hex'), ?::uuid) vr",
            [$election->id, uniqid(), $operator->id]
        );

        $monitorComp = Livewire::actingAs($operator, 'staff')
            ->test(VoterMonitorPage::class, ['election' => $election->fresh()]);

        $this->assertCount(25, $monitorComp->viewData('voterRows'));
        $this->assertTrue($monitorComp->viewData('isPollingPage'));
        $monitorComp->assertSeeHtml('wire:poll.3s.visible');

        $monitorComp->call('gotoPage', 2);
        $this->assertCount(2, $monitorComp->viewData('voterRows'));
        $this->assertFalse($monitorComp->viewData('isPollingPage'));
        $monitorComp->assertDontSeeHtml('wire:poll.3s.visible');
        $monitorComp->assertSee('Pembaruan otomatis dijeda di halaman 2');

        $monitorComp->call('gotoPage', 1);
        $this->assertCount(25, $monitorComp->viewData('voterRows'));
        $this->assertTrue($monitorComp->viewData('isPollingPage'));
        $monitorComp->assertSeeHtml('wire:poll.3s.visible');

        // UPDATE V4 BAGIAN B & C: Wizard draft behavior, subtitle, & copy-link-label outside token-box
        $countBeforeCancel = Election::count();
        Livewire::actingAs($operator, 'staff')
            ->test(ElectionIndexPage::class)
            ->call('openCreateModal')
            ->assertSee('Keluar setelah Identitas terisi akan menyimpan pemilihan sebagai draft')
            ->assertDontSee('Keluar di tengah jalan akan membatalkan seluruh isian')
            ->set('name', '')
            ->call('closeCreateModal');
        $this->assertSame($countBeforeCancel, Election::count());

        $draftName = 'Pilketis Draft V4 '.uniqid();
        Livewire::actingAs($operator, 'staff')
            ->test(ElectionIndexPage::class)
            ->call('openCreateModal')
            ->call(
                'saveWizardDraft',
                $draftName,
                120,
                [
                    ['number' => 1, 'name' => 'Paslon Tunggal Draft', 'vision' => 'Visi Draft', 'missions' => 'Misi 1'],
                    ['number' => 2, 'name' => '', 'vision' => '', 'missions' => ''],
                ],
                '',
                ''
            )
            ->assertDispatched('toast', message: 'Draft tersimpan', type: 'success');

        $draftElection = Election::where('name', $draftName)->first();
        $this->assertNotNull($draftElection);
        $this->assertSame(120, $draftElection->max_voter_rights);
        $this->assertNull($draftElection->starts_at);
        $this->assertNull($draftElection->ends_at);
        $this->assertSame(1, Candidate::where('election_id', $draftElection->id)->count());

        Livewire::actingAs($operator, 'staff')
            ->test(ElectionDetailPage::class, ['election' => $draftElection])
            ->assertSeeHtml('class="copy-link-label"');

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

        Livewire::test(LiveRecapPage::class, ['election' => PublicElection::findOrFail($election->id)])
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

    public function test_update_v5_ballot_recap_reveal_and_css(): void
    {
        $voting = app(VotingService::class);
        $dbNow = $voting->databaseNow();

        $operatorA = StaffAccount::create([
            'username' => 'op_a_v5_'.uniqid(),
            'full_name' => 'Operator A V5',
            'password_hash' => Hash::make('SandiOperator123!'),
            'role' => 'operator',
            'is_active' => true,
            'totp_enabled' => false,
            'must_change_password' => false,
        ]);

        $operatorB = StaffAccount::create([
            'username' => 'op_b_v5_'.uniqid(),
            'full_name' => 'Operator B V5',
            'password_hash' => Hash::make('SandiOperator123!'),
            'role' => 'operator',
            'is_active' => true,
            'totp_enabled' => false,
            'must_change_password' => false,
        ]);

        $election = Election::create([
            'name' => 'Pemilihan Uji Update V5 '.uniqid(),
            'max_voter_rights' => 3,
        ]);

        $c1 = Candidate::on('staff')->create([
            'election_id' => $election->id,
            'number' => 1,
            'name' => 'Paslon Satu V5',
            'vision' => 'Visi Paslon Satu',
            'missions' => ['Misi 1A', 'Misi 1B'],
        ]);

        $c2 = Candidate::on('staff')->create([
            'election_id' => $election->id,
            'number' => 2,
            'name' => 'Paslon Dua V5',
            'vision' => 'Visi Paslon Dua',
            'missions' => ['Misi 2A'],
        ]);

        $c3 = Candidate::on('staff')->create([
            'election_id' => $election->id,
            'number' => 3,
            'name' => 'Paslon Nol Suara V5',
            'vision' => 'Visi Paslon Tiga',
            'missions' => ['Misi 3A'],
        ]);

        $sched = $voting->setElectionSchedule($election->id, $dbNow->subMinute(), $dbNow->addHours(3), $operatorA->id);
        $this->assertTrue($sched['ok']);

        // --- BAGIAN A: Popup visi/misi opsional dan konfirmasi suara ---
        $vr1 = $voting->createVoterRight($election->id, 'Pemilih Satu V5', $operatorA->id);
        $vr2 = $voting->createVoterRight($election->id, 'Pemilih Dua V5', $operatorA->id);
        $vr3 = $voting->createVoterRight($election->id, 'Pemilih Tiga V5', $operatorA->id);

        // Verifikasi route bilik suara memuat CSS mandiri public/css/voter/ballot.css?v=
        $this->get(route('voter.ballot', ['token' => $vr1['raw_token']]))
            ->assertStatus(200)
            ->assertSee('css/voter/ballot.css?v=', false);

        $ballot = Livewire::withCookie(config('evoting.browser_cookie_name'), 'test_browser_v5_0123456789abcdef')
            ->test(BallotPage::class, ['token' => $vr1['raw_token']])
            ->call('startVoting')
            ->assertSet('stage', 'memilih')
            ->assertSee('Pilih Paslon Ini')
            ->assertSee('Lihat Visi & Misi')
            ->assertDontSeeHtml('<div class="candidate-vision">')
            ->assertSeeHtml('role="radio"');

        $ballot->call('confirmVote', (string) Str::uuid())
            ->assertSet('stage', 'memilih')
            ->assertDispatched('ballot-edit')
            ->call('confirmVote', $c1->id)
            ->assertSet('stage', 'selesai');

        $this->assertSame('recap_locked', $voting->operatorElectionResults($election->id)['code']);
        $election->update(['recap_open' => true]);
        $this->assertSame('recap_locked', $voting->operatorElectionResults($election->id)['code']);
        $election->update(['recap_open' => false]);

        // --- BAGIAN B.1: Default terkunci, tidak bisa dibuka saat kuota belum penuh (1/3) & masih berlangsung ---
        $this->actingAs($operatorA, 'staff')
            ->get(route('operator.elections.recap', $election))
            ->assertStatus(200)
            ->assertSee('css/operator/recap-reveal.css?v=', false)
            ->assertSee('js/operator/recap-reveal.js?v=', false)
            ->assertSee('1 / 3 suara terpakai');

        $recapA = Livewire::actingAs($operatorA, 'staff')
            ->test(ElectionRecapPage::class, ['election' => $election->fresh()]);

        $recapA->call('openRecap')
            ->assertHasErrors(['recapLock']);
        $this->assertFalse($election->fresh()->recap_open);

        // Keamanan: saat terkunci, tidak bisa memanggil setRecapMode atau fetchRevealResults secara paksa
        $recapA->call('setRecapMode', 'biasa')
            ->assertSet('activeViewMode', null);
        $lockedFetch = $recapA->instance()->fetchRevealResults($voting);
        $this->assertFalse($lockedFetch['ok']);
        $this->assertSame([], $lockedFetch['votes_by_id']);

        // Selesaikan sisa 2 suara (total 3/3 = penuh) saat pemilihan MASIH berlangsung
        $this->assertTrue($voting->startVoting($vr2['raw_token'], 'br_v5_2', 'UA', '10.0.0.2')['ok']);
        $this->assertTrue($voting->confirmVote($vr2['raw_token'], 'br_v5_2', $c2->id, 'UA', '10.0.0.2')['ok']);
        $this->assertTrue($voting->startVoting($vr3['raw_token'], 'br_v5_3', 'UA', '10.0.0.3')['ok']);
        $this->assertTrue($voting->confirmVote($vr3['raw_token'], 'br_v5_3', $c2->id, 'UA', '10.0.0.3')['ok']);

        // Syarat kuota penuh (3/3) terpenuhi meski fase masih berlangsung -> Operator A bisa buka & tutup bebas
        // Setelah menekan "Buka Rekapitulasi", masuk ke Langkah 3 (Pilih Mode) — hasil BELUM dibocorkan!
        $recapA->call('openRecap')
            ->assertHasNoErrors()
            ->assertSet('activeViewMode', null)
            ->assertSee('Pilih Mode Tampilan')
            ->assertDontSee('Paslon Dua V5');
        $this->assertTrue($election->fresh()->recap_open);

        // Operator B melihat state terbuka (Langkah 3: pilih mode) dan dapat membuka Mode Biasa
        $recapB = Livewire::actingAs($operatorB, 'staff')
            ->test(ElectionRecapPage::class, ['election' => $election->fresh()])
            ->assertSee('Rekapitulasi Terbuka')
            ->assertSee('Pilih Mode Tampilan')
            ->assertDontSee('Paslon Dua V5')
            ->call('setRecapMode', 'biasa')
            ->assertSet('activeViewMode', 'biasa')
            ->assertSee('Paslon Dua V5')
            ->call('backToModeChoice')
            ->assertSet('activeViewMode', null)
            ->assertDontSee('Paslon Dua V5');

        // Tutup & buka kembali secara bebas oleh Operator A
        $this->actingAs($operatorA, 'staff');
        $recapA->call('closeRecap');
        $this->assertFalse($election->fresh()->recap_open);
        $recapA->call('openRecap');
        $this->assertTrue($election->fresh()->recap_open);

        $setupUrl = route('operator.elections.recap', $election);
        $raceUrl = route('operator.elections.recap.race', $election);
        $this->get($setupUrl)
            ->assertOk()
            ->assertSee('Tipe Sekuensial')
            ->assertSee('Tipe Balapan');
        $this->get($raceUrl)->assertRedirect($setupUrl);
        $recapA->call('saveRaceSetup', 'auto', '19')->assertNoRedirect();
        $recapA->call('saveRaceSetup', 'auto', '30.5')->assertNoRedirect();
        $recapA->call('saveRaceSetup', 'auto', '10001')->assertNoRedirect();
        $recapA->call('saveRaceSetup', 'manual', null)->assertRedirect($raceUrl);
        $this->get($raceUrl)
            ->assertOk()
            ->assertSee('Papan Peringkat')
            ->assertSee('Buka Surat Suara')
            ->assertSeeHtml(Js::from(['mode' => 'manual', 'delay' => 3000])->toHtml())
            ->assertDontSee('vote_count');
        $recapA->call('saveRaceSetup', 'auto', '50')->assertRedirect($raceUrl);
        $this->get($raceUrl)
            ->assertOk()
            ->assertSeeHtml(Js::from(['mode' => 'auto', 'delay' => 50])->toHtml());

        $recapA->call('closeRecap');
        $this->get($raceUrl)->assertRedirect($setupUrl);
        $recapA->call('openRecap');

        // --- BAGIAN B.2 & B.3: Mode Animasi (metadata paslon saja di awal; suara baru ditarik saat Play) ---
        $recapA->call('setRecapMode', 'animasi')
            ->assertSet('activeViewMode', 'animasi')
            ->assertSee('Pilih Jeda Antar Angka Suara')
            ->assertSee('Pilih Urutan Tampil Paslon')
            ->assertSee('Acak Urutan')
            ->assertSee('Simpan')
            ->assertDontSee('Play (Mulai Animasi)')
            ->assertDontSee('Hasil Perhitungan & Peringkat')
            ->assertDontSee('buildResultRow')
            ->assertDontSee('vote_count')
            ->assertSeeHtml('window.createRecapSim($el, $wire)');
        $this->assertSame('animasi', $election->fresh()->recap_mode);

        // Pastikan sebelum Play ditekan, klien HANYA menerima metadata paslon (tanpa angka suara)
        $animMeta = $recapA->viewData('revealCandidatesMeta');
        $this->assertCount(3, $animMeta);
        $this->assertArrayNotHasKey('vote_count', $animMeta[0]);

        $revealUrl = route('operator.elections.recap.reveal', $election);
        $this->get($revealUrl)->assertRedirect($setupUrl);

        $order = [$c3->id, $c2->id, $c1->id];
        $recapA->call('saveRevealSetup', 19, $order)->assertNoRedirect();
        $recapA->call('saveRevealSetup', 50, [$c3->id, $c3->id, $c1->id])->assertNoRedirect();
        $recapA->call('saveRevealSetup', 50, $order)->assertRedirect($revealUrl);

        $this->get($revealUrl)
            ->assertOk()
            ->assertSee('Hasil Perhitungan & Peringkat')
            ->assertSee('Play (Mulai Animasi)')
            ->assertDontSee('Pilih Jeda Antar Angka Suara')
            ->assertDontSee('Pilih Urutan Tampil Paslon')
            ->assertDontSee('vote_count')
            ->assertSeeHtml(Js::from(['delayMs' => 50, 'orderIds' => $order])->toHtml());

        // Saat tombol Play ditekan -> fetchRevealResults() menarik data suara sekali dari server
        $fetched = $recapA->instance()->fetchRevealResults($voting);
        $this->assertTrue($fetched['ok']);
        $this->assertSame(3, $fetched['total_votes']);
        $this->assertSame(1, $fetched['votes_by_id'][$c1->id]['vote_count']);
        $this->assertSame(2, $fetched['votes_by_id'][$c2->id]['vote_count']);
        $this->assertSame(0, $fetched['votes_by_id'][$c3->id]['vote_count']);

        // Validasi delay di server tetap terjaga di rentang 20..10000
        $recapA->call('saveRevealDelay', 19)->assertHasErrors(['revealDelayInput']);
        $recapA->call('saveRevealDelay', 10001)->assertHasErrors(['revealDelayInput']);
        $recapA->call('saveRevealDelay', 650)->assertHasNoErrors();
        $this->assertSame(650, $election->fresh()->reveal_delay_ms);

        // Susun urutan manual & acak urutan
        $recapA->call('toggleCandidateOrder', $c3->id)
            ->call('toggleCandidateOrder', $c1->id);
        $this->assertSame([$c3->id, $c1->id], $election->fresh()->reveal_order);

        $recapA->call('shuffleRevealOrder');
        $this->assertCount(3, $election->fresh()->reveal_order);

        $recapA->call('setRevealMode', 'satu');
        $this->assertSame('satu', $election->fresh()->reveal_mode);

        // Mulai Animasi -> mengunci konfigurasi untuk operator lain
        $recapA->call('startReveal');
        $elAfterStart = $election->fresh();
        $this->assertTrue($elAfterStart->reveal_locked);
        $this->assertSame($operatorA->id, (string) $elAfterStart->reveal_locked_by);
        $this->assertSame([], $elAfterStart->revealed_ids);

        // Operator B tidak bisa mengubah konfigurasi selama terkunci
        Livewire::actingAs($operatorB, 'staff')
            ->test(ElectionRecapPage::class, ['election' => $election->fresh()])
            ->call('setRevealMode', 'semua')
            ->call('saveRevealDelay', 5000);
        $this->assertSame('satu', $election->fresh()->reveal_mode);
        $this->assertSame(650, $election->fresh()->reveal_delay_ms);

        // Persistensi progres reveal (reload melanjutkan dari posisi terakhir)
        $this->actingAs($operatorA, 'staff');
        $recapA->call('markCandidatesRevealed', [$c1->id]);
        $this->assertSame([$c1->id], $election->fresh()->revealed_ids);

        // Reset membuka kunci dan mengosongkan progres
        $recapA->call('resetReveal');
        $elAfterReset = $election->fresh();
        $this->assertFalse($elAfterReset->reveal_locked);
        $this->assertSame([], $elAfterReset->revealed_ids);
        $this->assertNull($elAfterReset->reveal_locked_by);

        // Shortcut fase Ditutup: pemilihan baru dengan 0 suara yang diakhiri lebih awal tetap bisa dibuka
        $electionShortcut = Election::create([
            'name' => 'Pemilihan Shortcut Ditutup V5 '.uniqid(),
            'max_voter_rights' => 100,
        ]);
        Candidate::on('staff')->create(['election_id' => $electionShortcut->id, 'number' => 1, 'name' => 'Paslon X']);
        Candidate::on('staff')->create(['election_id' => $electionShortcut->id, 'number' => 2, 'name' => 'Paslon Y']);
        $this->assertTrue($voting->setElectionSchedule($electionShortcut->id, $dbNow->subMinute(), $dbNow->addHours(2), $operatorA->id)['ok']);
        $electionShortcut->refresh();
        $this->assertTrue($voting->setElectionSchedule(
            $electionShortcut->id,
            CarbonImmutable::instance($electionShortcut->starts_at),
            $voting->databaseNow()->subSecond(),
            $operatorA->id
        )['ok']);

        Livewire::actingAs($operatorA, 'staff')
            ->test(ElectionRecapPage::class, ['election' => $electionShortcut->fresh()])
            ->call('openRecap')
            ->assertHasNoErrors();
        $this->assertTrue($electionShortcut->fresh()->recap_open);
    }
}
