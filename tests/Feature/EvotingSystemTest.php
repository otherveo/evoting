<?php

namespace Tests\Feature;

use App\Livewire\Auth\ActivateAccountPage;
use App\Livewire\Auth\LoginPage;
use App\Livewire\Auth\VerifyTwoFactorPage;
use App\Livewire\Operator\VoterMonitorPage;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\StaffAccount;
use App\Services\StaffAccountService;
use App\Services\TotpService;
use App\Services\VotingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
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

        // AC-001 (Amandemen PRD): Admin lolos tahap 1 diarahkan ke UI-002 (2FA)
        Livewire::test(LoginPage::class)
            ->set('username', 'test_admin_1')
            ->set('password', 'SandiKuat123!')
            ->call('login')
            ->assertRedirect(route('auth.2fa'));

        // Sebelum TOTP diverifikasi, akses ke halaman Admin dialihkan ke UI-002
        $this->actingAs($admin, 'staff')
            ->get(route('admin.operators'))
            ->assertRedirect(route('auth.2fa'));

        // Verifikasi kode TOTP yang benar mengarahkan Admin ke UI-004
        $validCode = $totp->getCurrentCode($secret);
        Livewire::actingAs($admin, 'staff')
            ->test(VerifyTwoFactorPage::class)
            ->set('code', $validCode)
            ->call('verify')
            ->assertRedirect(route('admin.operators'));

        // AC-001 (Amandemen PRD): Operator login langsung ke UI-007 tanpa TOTP
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

        // AC-005 & BR-021: Admin ditolak mengakses halaman pemilihan Operator
        $this->actingAs($admin, 'staff')
            ->withSession(['staff_2fa_verified' => true])
            ->get(route('operator.elections'))
            ->assertStatus(403);

        // AC-005 & AC-030: Operator ditolak mengakses kelola akun Operator & Log Audit
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

        // AC-003: Sebelum ganti kata sandi, akses ke halaman lain dialihkan ke UI-003
        $this->actingAs($operator, 'staff')
            ->get(route('operator.elections'))
            ->assertRedirect(route('auth.activate'));

        // Operator hanya wajib Langkah 1 (ganti kata sandi) tanpa TOTP
        Livewire::actingAs($operator, 'staff')
            ->test(ActivateAccountPage::class)
            ->set('currentPassword', $tempPw)
            ->set('newPassword', 'SandiBaruOperator99!')
            ->set('newPasswordConfirmation', 'SandiBaruOperator99!')
            ->call('updatePassword')
            ->assertRedirect(route('operator.elections'));

        $this->assertFalse($operator->fresh()->must_change_password);
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

        // AC-010 & BR-024: Mengatur jadwal saat paslon < 2 ditolak
        $schedFail = $voting->setElectionSchedule(
            $election->id,
            $dbNow->subMinute(),
            $dbNow->addHours(2),
            $operator->id
        );
        $this->assertFalse($schedFail['ok']);
        $this->assertSame('too_few_candidates', $schedFail['code']);

        // Tambah 2 paslon pada Fase Persiapan
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

        // AC-012: Membuat hak pilih saat Fase Persiapan ditolak
        $earlyRight = $voting->createVoterRight($election->id, 'Andi Pratama', $operator->id);
        $this->assertFalse($earlyRight['ok']);
        $this->assertSame('wrong_phase', $earlyRight['code']);

        // Atur jadwal sehingga Fase menjadi Berlangsung
        $schedOk = $voting->setElectionSchedule(
            $election->id,
            $dbNow->subMinute(),
            $dbNow->addHours(2),
            $operator->id
        );
        $this->assertTrue($schedOk['ok']);
        $this->assertSame('berlangsung', $election->currentPhase());

        // AC-014: Registrasi Pemilih 1 pada Fase Berlangsung berhasil
        $vr1 = $voting->createVoterRight($election->id, 'Andi Pratama', $operator->id);
        $this->assertTrue($vr1['ok']);
        $this->assertNotNull($vr1['raw_token']);

        // AC-015: Nama Kembar ditolak di UI sebelum konfirmasi "orang berbeda"
        Livewire::actingAs($operator, 'staff')
            ->test(VoterMonitorPage::class, ['election' => $election])
            ->set('voterName', '  andi   pratama ')
            ->call('registerVoter')
            ->assertHasErrors(['voterName']);

        // Setelah konfirmasi orang berbeda dicentang, Hak Pilih ke-2 berhasil dibuat
        Livewire::actingAs($operator, 'staff')
            ->test(VoterMonitorPage::class, ['election' => $election])
            ->set('voterName', 'Andi Pratama')
            ->set('confirmDifferentPerson', true)
            ->call('registerVoter')
            ->assertSet('showQrModal', true);

        // AC-031: Pembuatan Hak Pilih ke-3 ditolak karena batas kuota (2) tercapai
        $vr3 = $voting->createVoterRight($election->id, 'Siti Aminah', $operator->id);
        $this->assertFalse($vr3['ok']);
        $this->assertSame('limit_reached', $vr3['code']);

        // AC-017 & BR-028: Halaman Pemilih tidak menampilkan nama pemilih ("Andi Pratama")
        $this->get(route('voter.ballot', ['token' => $vr1['raw_token']]))
            ->assertStatus(200)
            ->assertSee($election->name)
            ->assertDontSee('Andi Pratama');

        // AC-018: Mulai memilih mengubah status menjadi sedang_memilih & mengikat browser_id
        $browserA = 'browser_alpha_12345678901234567890';
        $browserB = 'browser_beta_12345678901234567890';

        $startA = $voting->startVoting($vr1['raw_token'], $browserA, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertTrue($startA['ok']);

        // Perangkat/browser kedua yang mencoba mengakses token yang sama saat sedang_memilih ditolak (AC-018, AC-034)
        $startB = $voting->startVoting($vr1['raw_token'], $browserB, 'Mozilla/5.0 Other', '10.0.0.2');
        $this->assertFalse($startB['ok']);
        $this->assertSame('wrong_browser', $startB['code']);

        // Browser yang sama dapat melanjutkan sesi dalam 10 menit (KP-004, AC-034)
        $resumeA = $voting->openQr($vr1['raw_token'], $browserA, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertTrue($resumeA['ok']);
        $this->assertSame('sedang_memilih', $resumeA['status']);

        // AC-032: Terbitkan ulang QR saat sedang_memilih ditolak (harus Reset Sesi dulu)
        $reissueWhileActive = $voting->reissueToken($vr1['voter_right_id'], $operator->id, 'Coba terbit ulang');
        $this->assertFalse($reissueWhileActive['ok']);
        $this->assertSame('wrong_status', $reissueWhileActive['code']);

        // AC-033: Reset Sesi dengan alasan mengembalikan ke belum_memilih
        $resetOk = $voting->resetSession($vr1['voter_right_id'], $operator->id, 'Baterai pemilih habis');
        $this->assertTrue($resetOk['ok']);

        // AC-032: Setelah status kembali belum_memilih, Terbitkan Ulang QR mencabut token lama
        $reissued = $voting->reissueToken($vr1['voter_right_id'], $operator->id, 'QR lama rusak');
        $this->assertTrue($reissued['ok']);
        $newToken = (string) $reissued['raw_token'];

        // Token lama tidak dapat dipakai lagi
        $oldOpen = $voting->openQr($vr1['raw_token'], $browserA, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertFalse($oldOpen['ok']);
        $this->assertSame('invalid_token', $oldOpen['code']);

        // Mulai memilih kembali dengan QR baru lalu Konfirmasi pilihan (AC-020)
        $this->assertTrue($voting->startVoting($newToken, $browserA, 'Mozilla/5.0 Test', '10.0.0.1')['ok']);
        $confirm1 = $voting->confirmVote($newToken, $browserA, $c1->id, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertTrue($confirm1['ok']);

        // Klik ganda / pengiriman ulang konfirmasi tidak menambah suara kedua (AC-020, NFR-007)
        $confirmDuplicate = $voting->confirmVote($newToken, $browserA, $c2->id, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertFalse($confirmDuplicate['ok']);
        $this->assertSame('already_voted', $confirmDuplicate['code']);

        // AC-036: Pemeriksaan integritas konsisten (total_votes = 1, total_voted = 1)
        $integrity = $voting->integrityReport($election->id);
        $this->assertTrue($integrity['is_consistent']);
        $this->assertSame(1, $integrity['total_votes']);
        $this->assertSame(1, $integrity['total_voted']);

        // AC-023 & AC-025 (BR-026): Sebelum Waktu Selesai, hasil per paslon dikembalikan 'not_ended'
        $earlyResults = $voting->electionResults($election->id);
        $this->assertFalse($earlyResults['ok']);
        $this->assertSame('not_ended', $earlyResults['code']);

        // AC-024: Halaman Publik Jumlah Pemilih terbuka dan menampilkan angka 1 tanpa nama
        $this->get(route('public.turnout', $election))
            ->assertStatus(200)
            ->assertSee($election->name)
            ->assertDontSee('Andi Pratama');

        // AC-011: Akhiri pemilihan lebih awal dengan memajukan Waktu Selesai ke sekarang
        $election->refresh();
        $endNow = $voting->setElectionSchedule(
            $election->id,
            CarbonImmutable::instance($election->starts_at),
            $voting->databaseNow(),
            $operator->id
        );
        $this->assertTrue($endNow['ok']);
        $this->assertSame('ditutup', $election->currentPhase());

        // AC-023 & AC-025: Setelah Ditutup, hasil per paslon terbuka (Paslon 1 = 1 suara, Paslon 2 = 0 suara)
        $finalResults = $voting->electionResults($election->id);
        $this->assertTrue($finalResults['ok']);
        $this->assertCount(2, $finalResults['items']);
        $this->assertSame(1, $finalResults['items'][0]['vote_count']);
        $this->assertSame(0, $finalResults['items'][1]['vote_count']);
    }
}
