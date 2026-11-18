<?php

namespace App\Livewire\Operator;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Models\Election;
use App\Models\StaffAccount;
use App\Services\VotingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Rekapitulasi & Integritas Suara')]
class ElectionRecapPage extends Component
{
    use HandlesSafeErrors;

    public Election $election;

    // Update v5 — Bagian B.3.4: pesan validasi delay (20–10000 ms).
    public string $delayError = '';

    public function mount(Election $election): void
    {
        $this->election = $election;
    }

    public function refreshData(): void
    {
        try {
            $this->election->refresh();
            $this->dispatch('toast', message: 'Data rekapitulasi dan status integritas diperbarui.', type: 'info');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal memuat ulang data rekapitulasi.');
        }
    }

    /**
     * Update v5 — Bagian B.1.3: refresh senyap untuk wire:poll (tanpa toast),
     * supaya konfigurasi/state rekap konsisten antar operator.
     */
    public function pollRefresh(): void
    {
        $this->election->refresh();
    }

    /**
     * Update v5 — Bagian B.1: buka/tutup halaman rekap oleh operator.
     * Membuka hanya bila syarat terpenuhi (kuota terpakai penuh atau fase
     * Ditutup); menutup selalu boleh. State persisten di database.
     */
    public function toggleRecap(VotingService $voting): void
    {
        try {
            $this->election->refresh();

            if (! $this->election->recap_open && ! $this->canOpenRecap($voting)) {
                $this->dispatch('toast', message: 'Syarat belum terpenuhi', type: 'warning');

                return;
            }

            $this->persistRecapColumns(['recap_open' => ! $this->election->recap_open]);
            $this->dispatch('toast', message: $this->election->recap_open ? 'Rekap dibuka' : 'Rekap ditutup', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mengubah status rekapitulasi.');
        }
    }

    /**
     * Update v5 — Bagian B.2: mode tampilan (biasa/animasi), tersimpan per pemilihan.
     * Tidak bisa diubah di tengah reveal yang terkunci.
     */
    public function setRecapMode(string $mode): void
    {
        if (! in_array($mode, ['biasa', 'animasi'], true)) {
            return;
        }

        try {
            $this->election->refresh();

            if (! $this->election->recap_open) {
                return;
            }

            if ($this->election->reveal_locked) {
                $this->dispatch('toast', message: 'Reveal sedang berjalan', type: 'warning');

                return;
            }

            $this->persistRecapColumns(['recap_mode' => $mode]);
            $this->dispatch('toast', message: $mode === 'animasi' ? 'Mode animasi' : 'Mode biasa', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mengubah mode tampilan.');
        }
    }

    /**
     * Update v5 — Bagian B.3.1: klik paslon untuk menambah/menghapusnya dari
     * urutan tampil (urutan = urutan klik). Hanya sebelum reveal dimulai.
     */
    public function toggleRevealOrderCandidate(string $candidateId): void
    {
        try {
            $this->election->refresh();

            if ($this->election->reveal_locked) {
                $this->dispatch('toast', message: 'Konfigurasi terkunci', type: 'warning');

                return;
            }

            $validIds = $this->election->candidates()->pluck('id')->all();
            if (! in_array($candidateId, $validIds, true)) {
                return;
            }

            $order = $this->cleanIdList($this->election->reveal_order, $validIds);
            if (in_array($candidateId, $order, true)) {
                $order = array_values(array_diff($order, [$candidateId]));
            } else {
                $order[] = $candidateId;
            }

            $this->persistRecapColumns(['reveal_order' => $order]);
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mengubah urutan tampil.');
        }
    }

    /**
     * Update v5 — Bagian B.3.1: acak urutan tampil (seluruh paslon).
     */
    public function shuffleRevealOrder(): void
    {
        try {
            $this->election->refresh();

            if ($this->election->reveal_locked) {
                $this->dispatch('toast', message: 'Konfigurasi terkunci', type: 'warning');

                return;
            }

            $ids = $this->election->candidates()->orderBy('number')->pluck('id')->all();
            shuffle($ids);
            $this->persistRecapColumns(['reveal_order' => $ids]);
            $this->dispatch('toast', message: 'Urutan diacak', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mengacak urutan tampil.');
        }
    }

    /**
     * Update v5 — Bagian B.3.3: mode reveal (satu per satu / semua sekaligus).
     */
    public function setRevealMode(string $mode): void
    {
        if (! in_array($mode, ['satu', 'semua'], true)) {
            return;
        }

        try {
            $this->election->refresh();

            if ($this->election->reveal_locked) {
                $this->dispatch('toast', message: 'Konfigurasi terkunci', type: 'warning');

                return;
            }

            $this->persistRecapColumns(['reveal_mode' => $mode]);
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mengubah mode reveal.');
        }
    }

    /**
     * Update v5 — Bagian B.3.4: terapkan delay (ms). Tolak nilai di luar
     * 20–10000 dengan pesan yang jelas.
     */
    public function applyDelay(int $ms): void
    {
        try {
            $this->election->refresh();

            if ($this->election->reveal_locked) {
                $this->dispatch('toast', message: 'Konfigurasi terkunci', type: 'warning');

                return;
            }

            if ($ms < 20 || $ms > 10000) {
                $this->delayError = 'Delay harus 20–10000 ms.';
                $this->dispatch('toast', message: 'Delay di luar rentang', type: 'error');

                return;
            }

            $this->delayError = '';
            $this->persistRecapColumns(['reveal_delay_ms' => $ms]);
            $this->dispatch('toast', message: 'Delay disimpan', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menyimpan delay.');
        }
    }

    /**
     * Update v5 — Bagian B.3.5: mulai reveal. Mengunci konfigurasi (urutan +
     * mode + delay) bagi operator lain dan memulai dari nol (revealed kosong).
     * Syarat: urutan harus mencakup seluruh paslon dan delay valid.
     */
    public function startReveal(): void
    {
        try {
            $this->election->refresh();

            if (! $this->election->recap_open) {
                return;
            }

            if ($this->election->reveal_locked) {
                $this->dispatch('toast', message: 'Reveal sedang berjalan', type: 'warning');

                return;
            }

            $candidateIds = $this->election->candidates()->orderBy('number')->pluck('id')->all();
            $order = $this->cleanIdList($this->election->reveal_order, $candidateIds);
            if (count($candidateIds) === 0 || count($order) !== count($candidateIds)) {
                $this->dispatch('toast', message: 'Urutan belum lengkap', type: 'warning');

                return;
            }

            $delay = (int) $this->election->reveal_delay_ms;
            if ($delay < 20 || $delay > 10000) {
                $this->dispatch('toast', message: 'Delay di luar rentang', type: 'error');

                return;
            }

            $this->persistRecapColumns([
                'reveal_locked' => true,
                'reveal_locked_by' => Auth::guard('staff')->id(),
                'revealed_ids' => [],
            ]);
            $this->dispatch('toast', message: 'Reveal dimulai', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal memulai reveal.');
        }
    }

    /**
     * Update v5 — Bagian B.3.2/B.3.5: Reset — kembali ke konfigurasi dan
     * membuka kunci (progres reveal dihapus; urutan/mode/delay dipertahankan).
     */
    public function resetReveal(): void
    {
        try {
            $this->election->refresh();

            $this->persistRecapColumns([
                'reveal_locked' => false,
                'reveal_locked_by' => null,
                'revealed_ids' => [],
            ]);
            $this->dispatch('toast', message: 'Reveal direset', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mereset reveal.');
        }
    }

    /**
     * Update v5 — Bagian B.4.6: menandai satu paslon sudah ditampilkan
     * (dipanggil klien saat hitung-naiknya selesai). Penambahan atomik di
     * database (jsonb || + guard @>) supaya dua operator yang menyelesaikan
     * paslon berbeda di saat bersamaan tidak saling menimpa.
     */
    public function markRevealed(string $candidateId): void
    {
        try {
            $this->election->refresh();

            if (! $this->election->reveal_locked) {
                return;
            }

            $validIds = $this->election->candidates()->pluck('id')->all();
            if (! in_array($candidateId, $validIds, true)) {
                return;
            }

            DB::update(
                'update evoting.elections set revealed_ids = case when revealed_ids @> ?::jsonb then revealed_ids else revealed_ids || ?::jsonb end where id = ?::uuid',
                [json_encode([$candidateId]), json_encode([$candidateId]), $this->election->id]
            );
            $this->election->refresh();
        } catch (Throwable $e) {
            // Progres reveal bersifat best-effort dari klien; jangan ganggu animasi.
            report($e);
        }
    }

    /**
     * Update v5 — Bagian B.1.3/B.8: state reveal terkini untuk sinkronisasi
     * klien (dipanggil berkala oleh halaman yang menampilkan reveal).
     */
    public function syncRevealState(): array
    {
        $this->election->refresh();
        $validIds = $this->election->candidates()->pluck('id')->all();

        return [
            'revealed_ids' => $this->cleanIdList($this->election->revealed_ids, $validIds),
            'reveal_locked' => (bool) $this->election->reveal_locked,
            'recap_open' => (bool) $this->election->recap_open,
        ];
    }

    /**
     * Update v5 — Bagian B: simpan HANYA kolom rekap yang dimaksud.
     * Election::currentPhase() menyinkronkan starts_at/ends_at dari cache
     * jadwal ke model, sehingga atribut itu bisa kotor (nilai cache berpresisi
     * mikrodetik, nilai database terpotong ke detik oleh toIso8601String).
     * Bila ikut tersimpan, UPDATE ditolak grant kolom (starts_at/ends_at
     * hanya boleh diubah lewat fn_set_election_schedule). Karena itu simpan
     * per kolom secara eksplisit lewat query builder, lalu refresh model.
     */
    private function persistRecapColumns(array $columns): void
    {
        foreach ($columns as $column => $value) {
            $this->election->setAttribute($column, $value);
        }

        $payload = [];
        foreach ($columns as $column => $value) {
            $payload[$column] = is_array($value) ? json_encode($value) : $value;
        }

        Election::whereKey($this->election->getKey())->update($payload);
        $this->election->refresh();
    }

    /**
     * Update v5 — Bagian B.1: syarat membuka halaman rekap — seluruh kuota
     * hak suara terpakai (Sudah memilih == max_voter_rights) ATAU fase sudah
     * Ditutup (shortcut; halaman tidak boleh terkunci permanen).
     */
    private function canOpenRecap(VotingService $voting): bool
    {
        $integrity = $voting->integrityReport($this->election->id);

        return (int) $integrity['total_voted'] >= (int) $this->election->max_voter_rights
            || $this->election->currentPhase() === 'ditutup';
    }

    /**
     * Saring daftar ID ke ID yang valid saja (buang duplikat, jaga urutan).
     */
    private function cleanIdList(mixed $list, array $validIds): array
    {
        $list = is_array($list) ? array_map('strval', $list) : [];
        $valid = array_map('strval', $validIds);

        return array_values(array_unique(array_intersect($list, $valid)));
    }

    public function render(VotingService $voting)
    {
        $this->election->refresh();
        View::share('activeElection', $this->election);

        $phase = $this->election->currentPhase();
        $integrity = $voting->integrityReport($this->election->id);
        $candidatesById = $this->election->candidates()->get()->keyBy('id');

        // Update v5 — Bagian B.1: kunci operator menggantikan gate fase.
        $canOpen = (int) $integrity['total_voted'] >= (int) $this->election->max_voter_rights || $phase === 'ditutup';
        $isOpen = (bool) $this->election->recap_open;
        $recapMode = (string) ($this->election->recap_mode ?? 'biasa');

        $resultsData = null;
        $leadingCandidates = [];
        $totalVotesCast = (int) $integrity['total_votes'];
        $revealSnapshot = null;
        $revealOrder = [];
        $revealedIds = [];
        $lockerName = null;

        if ($isOpen) {
            // Update v5 — Bagian B.7: hasil dimuat live (fungsi hasil operator,
            // bukan lagi hanya saat fase Ditutup).
            $res = $voting->operatorElectionResults($this->election->id);
            if ($res['ok']) {
                $items = collect($res['items'])->map(function (array $item) use ($candidatesById, $totalVotesCast) {
                    $cand = $candidatesById->get($item['candidate_id']);
                    $share = $totalVotesCast > 0 ? round(($item['vote_count'] / $totalVotesCast) * 100, 1) : 0.0;

                    return [
                        'candidate' => $cand,
                        'number' => $item['number'],
                        'formatted_number' => str_pad((string) $item['number'], 2, '0', STR_PAD_LEFT),
                        'name' => $item['name'],
                        'photo_url' => $cand ? $cand->photoUrl() : '',
                        'vote_count' => $item['vote_count'],
                        'share' => $share,
                    ];
                });

                $maxVote = (int) $items->max('vote_count');
                if ($maxVote > 0) {
                    $leadingCandidates = $items->where('vote_count', $maxVote)->values()->all();
                }

                $resultsData = $items->all();

                // Snapshot reveal (skalar saja, untuk @js): diambil sekali saat
                // reveal dimulai; animasi setelahnya murni sisi klien.
                $revealSnapshot = $items->map(fn (array $row) => [
                    'id' => $row['candidate'] ? (string) $row['candidate']->id : '',
                    'number' => $row['number'],
                    'formatted_number' => $row['formatted_number'],
                    'name' => $row['name'],
                    'photo_url' => $row['photo_url'],
                    'vote_count' => $row['vote_count'],
                    'share' => $row['share'],
                ])->values()->all();
            }

            $validIds = $candidatesById->keys()->all();
            $revealOrder = $this->cleanIdList($this->election->reveal_order, $validIds);
            $revealedIds = $this->cleanIdList($this->election->revealed_ids, $validIds);

            if ($this->election->reveal_locked && $this->election->reveal_locked_by) {
                $locker = StaffAccount::find($this->election->reveal_locked_by);
                $lockerName = $locker ? ($locker->full_name ?: $locker->username) : null;
            }
        }

        return view('livewire.operator.election-recap-page', [
            'phase' => $phase,
            'integrity' => $integrity,
            'resultsData' => $resultsData,
            'leadingCandidates' => $leadingCandidates,
            'totalVotesCast' => $totalVotesCast,
            'canOpen' => $canOpen,
            'isOpen' => $isOpen,
            'recapMode' => $recapMode,
            'revealSnapshot' => $revealSnapshot,
            'revealOrder' => $revealOrder,
            'revealedIds' => $revealedIds,
            'lockerName' => $lockerName,
        ]);
    }
}
