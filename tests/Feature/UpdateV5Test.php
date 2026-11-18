<?php

namespace Tests\Feature;

use App\Livewire\Operator\ElectionRecapPage;
use App\Livewire\Voter\BallotPage;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\StaffAccount;
use App\Services\VotingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class UpdateV5Test extends TestCase
{
    use DatabaseTransactions;

    private function makeOperator(string $username): StaffAccount
    {
        return StaffAccount::create([
            'username' => $username,
            'full_name' => 'Operator Uji v5',
            'password_hash' => Hash::make('SandiOperator123!'),
            'role' => 'operator',
            'is_active' => true,
            'totp_secret' => null,
            'totp_enabled' => false,
            'must_change_password' => false,
        ]);
    }

    /**
     * @return array{Election, list<Candidate>}
     */
    private function makeElectionWithCandidates(int $maxRights = 2): array
    {
        $election = Election::create([
            'name' => 'Pemilihan Uji v5',
            'max_voter_rights' => $maxRights,
        ]);

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

        return [$election, [$c1, $c2]];
    }

    private function startElection(VotingService $voting, Election $election, StaffAccount $operator): void
    {
        $dbNow = $voting->databaseNow();
        $sched = $voting->setElectionSchedule($election->id, $dbNow->subMinute(), $dbNow->addHours(2), $operator->id);
        $this->assertTrue($sched['ok'], 'setElectionSchedule gagal: '.($sched['code'] ?? '?'));
        $this->assertSame('berlangsung', $election->fresh()->currentPhase());
    }

    private function castVote(VotingService $voting, Election $election, StaffAccount $operator, string $name, string $candidateId, string $browser): void
    {
        $vr = $voting->createVoterRight($election->id, $name, $operator->id);
        $this->assertTrue($vr['ok'], 'createVoterRight gagal: '.($vr['code'] ?? '?'));
        $this->assertTrue($voting->startVoting($vr['raw_token'], $browser, 'Mozilla/5.0 Test', '10.0.0.1')['ok']);
        $confirm = $voting->confirmVote($vr['raw_token'], $browser, $candidateId, 'Mozilla/5.0 Test', '10.0.0.1');
        $this->assertTrue($confirm['ok'], 'confirmVote gagal: '.($confirm['code'] ?? '?'));
    }

    // --- Bagian A: gate baca visi/misi -------------------------------------

    public function test_ballot_gate_rejects_confirm_until_all_visions_read(): void
    {
        [$election, $candidates] = $this->makeElectionWithCandidates();
        [$c1, $c2] = $candidates;

        // Cookie browser valid: mount BallotPage memanggil
        // TokenService::resolveOrCreateBrowserId, yang tanpa cookie akan
        // menyentuh session (tidak ada di Livewire test karena middleware
        // dimatikan) dan melempar "Session store not set on request".
        $page = Livewire::withCookie('evoting_browser_id', Str::random(32))
            ->test(BallotPage::class, ['token' => 'token-uji-tidak-valid'])
            ->set('stage', 'memilih')
            ->set('electionId', $election->id)
            ->set('selectedCandidateId', $c1->id);

        // Belum ada yang dibaca -> tolak, tetap di memilih, event gate-rejected.
        $page->call('proceedToConfirm')
            ->assertSet('stage', 'memilih')
            ->assertDispatched('gate-rejected');

        // Baru 1 dari 2 dibaca -> tetap tolak.
        $page->call('markVisionRead', $c1->id)
            ->assertSet('readCandidateIds', [$c1->id]);
        $page->call('proceedToConfirm')
            ->assertSet('stage', 'memilih')
            ->assertDispatched('gate-rejected');

        // Semua dibaca -> lanjut ke konfirmasi.
        $page->call('markVisionRead', $c2->id)
            ->assertSet('readCandidateIds', [$c1->id, $c2->id]);
        $page->call('proceedToConfirm')
            ->assertSet('stage', 'konfirmasi');
    }

    public function test_ballot_mark_vision_read_ignores_unknown_candidate(): void
    {
        [$election, $candidates] = $this->makeElectionWithCandidates();
        [$c1] = $candidates;

        Livewire::withCookie('evoting_browser_id', Str::random(32))
            ->test(BallotPage::class, ['token' => 'token-uji-tidak-valid'])
            ->set('stage', 'memilih')
            ->set('electionId', $election->id)
            ->set('selectedCandidateId', $c1->id)
            ->call('markVisionRead', '00000000-0000-0000-0000-000000000000')
            ->assertSet('readCandidateIds', []);
    }

    // --- Bagian B: kunci rekap ----------------------------------------------

    public function test_recap_toggle_requires_quota_or_closed_phase(): void
    {
        $voting = app(VotingService::class);
        $operator = $this->makeOperator('op_v5_kunci');
        [$election, $candidates] = $this->makeElectionWithCandidates(2);
        [$c1, $c2] = $candidates;
        $this->startElection($voting, $election, $operator);

        $page = Livewire::actingAs($operator, 'staff')
            ->test(ElectionRecapPage::class, ['election' => $election]);

        // 0/2 suara -> halaman terkunci, toggle ditolak.
        $page->assertSee('Belum Bisa Dibuka');
        $page->call('toggleRecap');
        $this->assertFalse($election->fresh()->recap_open);

        // Kuota penuh (2/2) -> bisa dibuka, hasil live terlihat.
        $this->castVote($voting, $election, $operator, 'Pemilih Satu', $c1->id, 'browser_v5_a_12345678901234567890');
        $this->castVote($voting, $election, $operator, 'Pemilih Dua', $c2->id, 'browser_v5_b_12345678901234567890');

        $page->call('toggleRecap');
        $this->assertTrue($election->fresh()->recap_open);
        $page->assertSee('REKAPITULASI OPERATOR (LIVE)');

        // Tutup kembali.
        $page->call('toggleRecap');
        $this->assertFalse($election->fresh()->recap_open);
    }

    public function test_recap_open_by_closed_phase_shortcut(): void
    {
        $voting = app(VotingService::class);
        $operator = $this->makeOperator('op_v5_tutup');
        [$election] = $this->makeElectionWithCandidates(50);

        // Akhiri pemilihan (0 suara, kuota belum penuh) -> tetap bisa dibuka.
        // Pola sama seperti EvotingSystemTest: mulai dulu, lalu tutup dengan
        // starts_at dipertahankan (starts_at terkunci begitu pemilihan berjalan).
        $this->startElection($voting, $election, $operator);
        $election->refresh();
        $dbNow = $voting->databaseNow();
        $end = $voting->setElectionSchedule($election->id, CarbonImmutable::instance($election->starts_at), $dbNow, $operator->id);
        $this->assertTrue($end['ok'], 'setElectionSchedule gagal: '.($end['code'] ?? '?'));
        $this->assertSame('ditutup', $election->fresh()->currentPhase());

        Livewire::actingAs($operator, 'staff')
            ->test(ElectionRecapPage::class, ['election' => $election])
            ->call('toggleRecap');
        $this->assertTrue($election->fresh()->recap_open);
    }

    public function test_operator_results_available_live_before_end(): void
    {
        $voting = app(VotingService::class);
        $operator = $this->makeOperator('op_v5_live');
        [$election, $candidates] = $this->makeElectionWithCandidates(2);
        [$c1] = $candidates;
        $this->startElection($voting, $election, $operator);
        $this->castVote($voting, $election, $operator, 'Pemilih Satu', $c1->id, 'browser_v5_c_12345678901234567890');

        // Fungsi lama tetap menolak sebelum Ditutup ...
        $early = $voting->electionResults($election->id);
        $this->assertFalse($early['ok']);
        $this->assertSame('not_ended', $early['code']);

        // ... tapi fungsi operator mengembalikan hasil live.
        $live = $voting->operatorElectionResults($election->id);
        $this->assertTrue($live['ok']);
        $this->assertCount(2, $live['items']);
        $this->assertSame(1, $live['items'][0]['vote_count']);
        $this->assertSame(0, $live['items'][1]['vote_count']);
    }

    // --- Bagian B: konfigurasi + progres reveal ------------------------------

    public function test_recap_reveal_config_start_mark_reset(): void
    {
        $voting = app(VotingService::class);
        $operator = $this->makeOperator('op_v5_reveal');
        [$election, $candidates] = $this->makeElectionWithCandidates(2);
        [$c1, $c2] = $candidates;
        $this->startElection($voting, $election, $operator);
        $this->castVote($voting, $election, $operator, 'Pemilih Satu', $c1->id, 'browser_v5_d_12345678901234567890');
        $this->castVote($voting, $election, $operator, 'Pemilih Dua', $c2->id, 'browser_v5_e_12345678901234567890');

        $page = Livewire::actingAs($operator, 'staff')
            ->test(ElectionRecapPage::class, ['election' => $election])
            ->call('toggleRecap');
        $this->assertTrue($election->fresh()->recap_open);

        // Mode animasi + urutan + mode + delay, lalu mulai.
        $page->call('setRecapMode', 'animasi')
            ->assertSet('election.recap_mode', 'animasi');
        $page->call('toggleRevealOrderCandidate', $c2->id)
            ->call('toggleRevealOrderCandidate', $c1->id)
            ->assertSet('election.reveal_order', [$c2->id, $c1->id]);
        $page->call('setRevealMode', 'satu')
            ->assertSet('election.reveal_mode', 'satu');
        $page->call('applyDelay', 500)
            ->assertSet('election.reveal_delay_ms', 500)
            ->assertSet('delayError', '');
        $page->call('startReveal');
        $this->assertTrue($election->fresh()->reveal_locked);
        $this->assertSame([], $election->fresh()->revealed_ids);

        // Progres: tandai satu paslon, lalu reset membuka kunci.
        $page->call('markRevealed', $c2->id);
        $this->assertSame([$c2->id], $election->fresh()->revealed_ids);
        $page->call('resetReveal');
        $this->assertFalse($election->fresh()->reveal_locked);
        $this->assertSame([], $election->fresh()->revealed_ids);
    }

    public function test_recap_delay_validation_rejects_out_of_range(): void
    {
        $voting = app(VotingService::class);
        $operator = $this->makeOperator('op_v5_delay');
        [$election, $candidates] = $this->makeElectionWithCandidates(2);
        [$c1, $c2] = $candidates;
        $this->startElection($voting, $election, $operator);
        $this->castVote($voting, $election, $operator, 'Pemilih Satu', $c1->id, 'browser_v5_f_12345678901234567890');
        $this->castVote($voting, $election, $operator, 'Pemilih Dua', $c2->id, 'browser_v5_g_12345678901234567890');

        $page = Livewire::actingAs($operator, 'staff')
            ->test(ElectionRecapPage::class, ['election' => $election])
            ->call('toggleRecap');

        $page->call('applyDelay', 10)
            ->assertSet('delayError', 'Delay harus 20–10000 ms.');
        $this->assertSame(1000, $election->fresh()->reveal_delay_ms);

        $page->call('applyDelay', 20000)
            ->assertSet('delayError', 'Delay harus 20–10000 ms.');
        $this->assertSame(1000, $election->fresh()->reveal_delay_ms);

        $page->call('applyDelay', 250)
            ->assertSet('delayError', '');
        $this->assertSame(250, $election->fresh()->reveal_delay_ms);
    }

    public function test_recap_start_rejects_incomplete_order(): void
    {
        $voting = app(VotingService::class);
        $operator = $this->makeOperator('op_v5_urutan');
        [$election, $candidates] = $this->makeElectionWithCandidates(2);
        [$c1, $c2] = $candidates;
        $this->startElection($voting, $election, $operator);
        $this->castVote($voting, $election, $operator, 'Pemilih Satu', $c1->id, 'browser_v5_h_12345678901234567890');
        $this->castVote($voting, $election, $operator, 'Pemilih Dua', $c2->id, 'browser_v5_i_12345678901234567890');

        $page = Livewire::actingAs($operator, 'staff')
            ->test(ElectionRecapPage::class, ['election' => $election])
            ->call('toggleRecap')
            ->call('setRecapMode', 'animasi')
            ->call('toggleRevealOrderCandidate', $c1->id);

        // Urutan baru 1 dari 2 -> mulai ditolak.
        $page->call('startReveal');
        $this->assertFalse($election->fresh()->reveal_locked);
    }
}
