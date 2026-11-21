<?php

namespace App\Livewire\Public;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Models\PublicElection;
use App\Services\VotingService;
use Illuminate\Support\Facades\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Hasil Pemilihan Publik')]
class PublicResultsPage extends Component
{
    use HandlesSafeErrors;

    public PublicElection $election;

    public string $loadedAtLabel = '';

    public function mount(PublicElection $election): void
    {
        $this->election = $election;
        $this->updateTimestamp();
    }

    public function refreshSnapshot(): void
    {
        try {
            $this->election->refresh();
            $this->updateTimestamp();
            $this->dispatch('toast', message: 'Data halaman hasil telah diperbarui.', type: 'info');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal memuat ulang halaman hasil.');
        }
    }

    private function updateTimestamp(): void
    {
        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');
        $now = now()->setTimezone($tz);
        $tzShort = match ($tz) {
            'Asia/Jakarta' => 'WIB',
            'Asia/Makassar', 'Asia/Pontianak' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => $now->format('T'),
        };
        $this->loadedAtLabel = $now->format('d M Y, H:i:s').' '.$tzShort;
    }

    public function render(VotingService $voting)
    {
        View::share('publicElection', $this->election);

        $phase = $this->election->currentPhase();
        $resultsData = null;
        $leadingCandidates = [];
        $totalVotesCast = 0;

        if ($phase === 'ditutup') {
            $res = $voting->electionResults($this->election->id);
            if ($res['ok']) {
                $candidatesById = $this->election->candidates()->get()->keyBy('id');
                $totalVotesCast = collect($res['items'])->sum('vote_count');

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
            }
        }

        return view('livewire.public.public-results-page', [
            'phase' => $phase,
            'resultsData' => $resultsData,
            'leadingCandidates' => $leadingCandidates,
            'totalVotesCast' => $totalVotesCast,
        ]);
    }
}
