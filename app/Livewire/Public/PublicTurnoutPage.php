<?php

namespace App\Livewire\Public;

use App\Models\Election;
use App\Services\VotingService;
use Illuminate\Support\Facades\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Jumlah Pemilih yang Sudah Memilih')]
class PublicTurnoutPage extends Component
{
    public Election $election;

    public string $loadedAtLabel = '';

    public function mount(Election $election): void
    {
        $this->election = $election;
        $this->updateTimestamp();
    }

    public function refreshSnapshot(): void
    {
        $this->election->refresh();
        $this->updateTimestamp();
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
        $report = $voting->integrityReport($this->election->id);
        $totalVoted = (int) $report['total_voted'];

        return view('livewire.public.public-turnout-page', [
            'phase' => $phase,
            'totalVoted' => $totalVoted,
        ]);
    }
}
