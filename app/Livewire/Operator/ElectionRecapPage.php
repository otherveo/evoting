<?php

namespace App\Livewire\Operator;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Models\Election;
use App\Services\VotingService;
use Illuminate\Support\Facades\Auth;
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

    public int $revealDelayInput = 150;

    /**
     * null (langkah pilih mode) | 'biasa' | 'animasi'
     * Selalu dimulai dari null agar menekan "Buka Rekapitulasi" tidak membocorkan hasil duluan.
     */
    public ?string $activeViewMode = null;

    public function mount(Election $election): void
    {
        $this->election = $election;
        $this->revealDelayInput = (int) ($election->reveal_delay_ms ?: 150);
        $this->activeViewMode = null;
    }

    private function canUnlockRecap(array $integrity, string $phase): bool
    {
        if ($phase === 'ditutup') {
            return true;
        }

        $totalVoted = (int) ($integrity['total_voted'] ?? 0);
        $maxRights = (int) $this->election->max_voter_rights;

        return $maxRights > 0 && $totalVoted >= $maxRights;
    }

    public function refreshData(): void
    {
        try {
            $this->election->refresh();
            $this->revealDelayInput = (int) ($this->election->reveal_delay_ms ?: 1000);
            $this->dispatch('toast', message: 'Diperbarui', type: 'info');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal memuat ulang data rekapitulasi.');
        }
    }

    public function openRecap(VotingService $voting): void
    {
        try {
            $this->election->refresh();
            $phase = $this->election->currentPhase();
            $integrity = $voting->integrityReport($this->election->id);

            if (! $this->canUnlockRecap($integrity, $phase)) {
                $this->addError(
                    'recapLock',
                    'Rekapitulasi belum dapat dibuka: seluruh kuota hak pilih harus terpakai atau pemilihan harus diakhiri terlebih dahulu.'
                );
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return;
            }

            $this->resetErrorBag('recapLock');
            $this->election->update([
                'recap_open' => true,
            ]);
            $this->activeViewMode = null;
            $this->dispatch('toast', message: 'Dibuka', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal membuka halaman rekapitulasi.', 'recapLock');
        }
    }

    public function closeRecap(): void
    {
        try {
            $this->election->refresh();
            $this->election->update([
                'recap_open' => false,
            ]);
            $this->activeViewMode = null;
            $this->dispatch('toast', message: 'Ditutup', type: 'info');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menutup halaman rekapitulasi.');
        }
    }

    public function backToModeChoice(): void
    {
        $this->activeViewMode = null;
    }

    public function setRecapMode(string $mode): void
    {
        if (! in_array($mode, ['biasa', 'animasi'], true)) {
            return;
        }

        try {
            $this->election->refresh();
            $this->election->update([
                'recap_mode' => $mode,
            ]);
            $this->activeViewMode = $mode;
            $this->dispatch('toast', message: $mode === 'animasi' ? 'Mode animasi' : 'Mode biasa', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mengubah mode tampilan rekapitulasi.');
        }
    }

    public function setRevealMode(string $mode): void
    {
        if (! in_array($mode, ['satu', 'semua'], true)) {
            return;
        }

        try {
            $this->election->refresh();
            if ($this->election->reveal_locked) {
                $this->dispatch('toast', message: 'Terkunci', type: 'warning');

                return;
            }

            $this->election->update([
                'reveal_mode' => $mode,
            ]);
            $this->dispatch('toast', message: 'Tersimpan', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mengubah mode animasi reveal.');
        }
    }

    public function toggleCandidateOrder(string $candidateId): void
    {
        try {
            $this->election->refresh();
            if ($this->election->reveal_locked) {
                $this->dispatch('toast', message: 'Terkunci', type: 'warning');

                return;
            }

            $validIds = $this->election->candidates()->pluck('id')->map(static fn ($id) => (string) $id)->all();
            if (! in_array($candidateId, $validIds, true)) {
                return;
            }

            $current = is_array($this->election->reveal_order) ? array_values(array_map('strval', $this->election->reveal_order)) : [];
            $current = array_values(array_intersect($current, $validIds));

            if (in_array($candidateId, $current, true)) {
                $current = array_values(array_diff($current, [$candidateId]));
            } else {
                $current[] = $candidateId;
            }

            $this->election->update([
                'reveal_order' => $current,
            ]);
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal memperbarui urutan tampil paslon.');
        }
    }

    public function clearRevealOrder(): void
    {
        try {
            $this->election->refresh();
            if ($this->election->reveal_locked) {
                $this->dispatch('toast', message: 'Terkunci', type: 'warning');

                return;
            }

            $this->election->update([
                'reveal_order' => [],
            ]);
            $this->dispatch('toast', message: 'Direset', type: 'info');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mengosongkan urutan tampil.');
        }
    }

    public function shuffleRevealOrder(): void
    {
        try {
            $this->election->refresh();
            if ($this->election->reveal_locked) {
                $this->dispatch('toast', message: 'Terkunci', type: 'warning');

                return;
            }

            $ids = $this->election->candidates()->pluck('id')->map(static fn ($id) => (string) $id)->all();
            shuffle($ids);

            $this->election->update([
                'reveal_order' => array_values($ids),
            ]);
            $this->dispatch('toast', message: 'Diacak', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mengacak urutan tampil paslon.');
        }
    }

    public function saveRevealDelay(?int $delayMs = null): bool
    {
        $this->election->refresh();
        if ($this->election->reveal_locked) {
            $this->dispatch('toast', message: 'Terkunci', type: 'warning');

            return false;
        }

        if ($delayMs !== null) {
            $this->revealDelayInput = $delayMs;
        }

        $this->resetErrorBag('revealDelayInput');

        if ($this->revealDelayInput < 20 || $this->revealDelayInput > 10000) {
            $this->addError(
                'revealDelayInput',
                'Delay animasi wajib berada di antara 20 ms hingga 10000 ms (10 detik).'
            );
            $this->dispatch('toast', message: 'Gagal', type: 'error');

            return false;
        }

        try {
            $this->election->update([
                'reveal_delay_ms' => $this->revealDelayInput,
            ]);
            $this->dispatch('toast', message: 'Tersimpan', type: 'success');

            return true;
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menyimpan delay animasi.', 'revealDelayInput');

            return false;
        }
    }

    public function startReveal(VotingService $voting, ?int $delayMs = null): ?array
    {
        try {
            $this->election->refresh();
            if (! $this->election->recap_open) {
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return null;
            }

            if ($this->election->reveal_locked) {
                $this->dispatch('toast', message: 'Terkunci', type: 'warning');

                return null;
            }

            if ($delayMs !== null) {
                $this->revealDelayInput = $delayMs;
            }

            $this->resetErrorBag('revealDelayInput');
            if ($this->revealDelayInput < 20 || $this->revealDelayInput > 10000) {
                $this->addError(
                    'revealDelayInput',
                    'Delay animasi wajib berada di antara 20 ms hingga 10000 ms (10 detik).'
                );
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return null;
            }

            $validIds = $this->election->candidates()->orderBy('number')->pluck('id')->map(static fn ($id) => (string) $id)->all();
            $currentOrder = is_array($this->election->reveal_order) ? array_values(array_map('strval', $this->election->reveal_order)) : [];
            $currentOrder = array_values(array_intersect($currentOrder, $validIds));

            foreach ($validIds as $cid) {
                if (! in_array($cid, $currentOrder, true)) {
                    $currentOrder[] = $cid;
                }
            }

            $operatorId = Auth::guard('staff')->id();

            $this->election->update([
                'recap_mode' => 'animasi',
                'reveal_order' => $currentOrder,
                'reveal_delay_ms' => $this->revealDelayInput,
                'reveal_locked' => true,
                'revealed_ids' => [],
                'reveal_locked_by' => $operatorId,
            ]);

            $snapshot = $this->buildResultsSnapshot($voting);
            $this->dispatch('toast', message: 'Dimulai', type: 'success');

            return [
                'ok' => true,
                'order' => $currentOrder,
                'mode' => (string) ($this->election->reveal_mode ?: 'satu'),
                'delay_ms' => (int) $this->revealDelayInput,
                'revealed_ids' => [],
                'total_votes' => $snapshot['total_votes'],
                'candidates' => $snapshot['reveal_candidates'],
            ];
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal memulai animasi reveal.');

            return null;
        }
    }

    /**
     * @param  list<string>  $candidateIds
     */
    public function markCandidatesRevealed(array $candidateIds): void
    {
        try {
            $this->election->refresh();
            if (! $this->election->reveal_locked) {
                return;
            }

            $validIds = $this->election->candidates()->pluck('id')->map(static fn ($id) => (string) $id)->all();
            $existing = is_array($this->election->revealed_ids) ? array_values(array_map('strval', $this->election->revealed_ids)) : [];

            foreach ($candidateIds as $cid) {
                $sid = (string) $cid;
                if (in_array($sid, $validIds, true) && ! in_array($sid, $existing, true)) {
                    $existing[] = $sid;
                }
            }

            $this->election->update([
                'revealed_ids' => $existing,
            ]);
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menyimpan progres reveal.');
        }
    }

    public function resetReveal(): void
    {
        try {
            $this->election->refresh();
            $this->election->update([
                'reveal_locked' => false,
                'revealed_ids' => [],
                'reveal_locked_by' => null,
            ]);
            $this->dispatch('toast', message: 'Direset', type: 'info');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mereset konfigurasi reveal.');
        }
    }

    /**
     * @return array{
     *   results_data: list<array<string, mixed>>,
     *   leading_candidates: list<array<string, mixed>>,
     *   reveal_candidates: list<array<string, mixed>>,
     *   total_votes: int
     * }
     */
    private function buildResultsSnapshot(VotingService $voting): array
    {
        $candidatesById = $this->election->candidates()->get()->keyBy('id');
        $res = $voting->operatorElectionResults($this->election->id);

        if (! $res['ok']) {
            return [
                'results_data' => [],
                'leading_candidates' => [],
                'reveal_candidates' => [],
                'total_votes' => 0,
            ];
        }

        $totalVotesCast = (int) collect($res['items'])->sum('vote_count');

        $items = collect($res['items'])->map(function (array $item) use ($candidatesById, $totalVotesCast) {
            $cand = $candidatesById->get($item['candidate_id']);
            $share = $totalVotesCast > 0 ? round(($item['vote_count'] / $totalVotesCast) * 100, 1) : 0.0;

            return [
                'id' => (string) $item['candidate_id'],
                'candidate' => $cand,
                'number' => (int) $item['number'],
                'formatted_number' => str_pad((string) $item['number'], 2, '0', STR_PAD_LEFT),
                'name' => (string) $item['name'],
                'photo_url' => $cand ? $cand->photoUrl() : '',
                'vote_count' => (int) $item['vote_count'],
                'share' => $share,
            ];
        });

        $maxVote = (int) $items->max('vote_count');
        $leadingCandidates = $maxVote > 0
            ? $items->where('vote_count', $maxVote)->values()->all()
            : [];

        $revealCandidates = $items->map(static fn (array $row) => [
            'id' => $row['id'],
            'number' => $row['number'],
            'formatted_number' => $row['formatted_number'],
            'name' => $row['name'],
            'photo_url' => $row['photo_url'],
            'vote_count' => $row['vote_count'],
            'share' => $row['share'],
        ])->values()->all();

        return [
            'results_data' => $items->all(),
            'leading_candidates' => $leadingCandidates,
            'reveal_candidates' => $revealCandidates,
            'total_votes' => $totalVotesCast,
        ];
    }

    public function render(VotingService $voting)
    {
        $this->election->refresh();
        View::share('activeElection', $this->election);

        $phase = $this->election->currentPhase();
        $integrity = $voting->integrityReport($this->election->id);
        $totalVotesCast = (int) ($integrity['total_votes'] ?? 0);
        $canUnlockRecap = $this->canUnlockRecap($integrity, $phase);
        $isRecapOpen = (bool) $this->election->recap_open && $canUnlockRecap;

        $candidates = $this->election->candidates()->orderBy('number')->get();
        $validIds = $candidates->pluck('id')->map(static fn ($id) => (string) $id)->all();

        $savedOrder = is_array($this->election->reveal_order)
            ? array_values(array_intersect(array_map('strval', $this->election->reveal_order), $validIds))
            : [];
        $revealedIds = is_array($this->election->revealed_ids)
            ? array_values(array_intersect(array_map('strval', $this->election->revealed_ids), $validIds))
            : [];

        $effectiveOrder = $savedOrder;
        foreach ($validIds as $cid) {
            if (! in_array($cid, $effectiveOrder, true)) {
                $effectiveOrder[] = $cid;
            }
        }

        $resultsData = null;
        $leadingCandidates = [];
        $revealCandidates = [];

        if ($isRecapOpen && $this->activeViewMode !== null) {
            $snapshot = $this->buildResultsSnapshot($voting);
            $resultsData = $snapshot['results_data'];
            $leadingCandidates = $snapshot['leading_candidates'];
            $revealCandidates = $snapshot['reveal_candidates'];
            $totalVotesCast = $snapshot['total_votes'];
        } else {
            $revealCandidates = $candidates->map(static fn ($c) => [
                'id' => (string) $c->id,
                'number' => (int) $c->number,
                'formatted_number' => $c->formattedNumber(),
                'name' => (string) $c->name,
                'photo_url' => $c->photoUrl(),
                'vote_count' => 0,
                'share' => 0.0,
            ])->values()->all();
        }

        $currentOperatorId = Auth::guard('staff')->id();
        $isLocalRevealRunner = (bool) $this->election->reveal_locked
            && ($this->election->reveal_locked_by === null || (string) $this->election->reveal_locked_by === (string) $currentOperatorId);

        return view('livewire.operator.election-recap-page', [
            'phase' => $phase,
            'integrity' => $integrity,
            'canUnlockRecap' => $canUnlockRecap,
            'isRecapOpen' => $isRecapOpen,
            'activeViewMode' => $this->activeViewMode,
            'recapMode' => (string) ($this->election->recap_mode ?: 'biasa'),
            'revealMode' => (string) ($this->election->reveal_mode ?: 'satu'),
            'revealDelayMs' => (int) ($this->election->reveal_delay_ms ?: 150),
            'revealLocked' => (bool) $this->election->reveal_locked,
            'isLocalRevealRunner' => $isLocalRevealRunner,
            'savedOrder' => $savedOrder,
            'effectiveOrder' => $effectiveOrder,
            'revealedIds' => $revealedIds,
            'candidatesList' => $candidates,
            'resultsData' => $resultsData,
            'leadingCandidates' => $leadingCandidates,
            'revealCandidates' => $revealCandidates,
            'totalVotesCast' => $totalVotesCast,
        ]);
    }
}
