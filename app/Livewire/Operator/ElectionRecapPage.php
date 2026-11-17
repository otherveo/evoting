<?php

namespace App\Livewire\Operator;

use App\Models\Election;
use App\Services\VotingService;
use Illuminate\Support\Facades\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Rekapitulasi & Integritas Suara')]
class ElectionRecapPage extends Component
{
    public Election $election;

    public function mount(Election $election): void
    {
        $this->election = $election;
    }

    public function refreshData(): void
    {
        $this->election->refresh();
    }

    public function render(VotingService $voting)
    {
        $this->election->refresh();
        View::share('activeElection', $this->election);

        $phase = $this->election->currentPhase();
        $integrity = $voting->integrityReport($this->election->id);
        $candidatesById = $this->election->candidates()->get()->keyBy('id');

        $resultsData = null;
        $leadingCandidates = [];
        $totalVotesCast = (int) $integrity['total_votes'];

        if ($phase === 'ditutup') {
            $res = $voting->electionResults($this->election->id);
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
            }
        }

        return view('livewire.operator.election-recap-page', [
            'phase' => $phase,
            'integrity' => $integrity,
            'resultsData' => $resultsData,
            'leadingCandidates' => $leadingCandidates,
            'totalVotesCast' => $totalVotesCast,
        ]);
    }
}
