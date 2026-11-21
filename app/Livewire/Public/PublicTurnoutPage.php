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
#[Title('Jumlah Pemilih yang Sudah Memilih')]
class PublicTurnoutPage extends Component
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
            $this->dispatch('toast', message: 'Data partisipasi berhasil diperbarui.', type: 'info');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal memuat ulang data partisipasi.');
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
        $report = $voting->integrityReport($this->election->id);
        $totalVoted = (int) $report['total_voted'];

        return view('livewire.public.public-turnout-page', [
            'phase' => $phase,
            'totalVoted' => $totalVoted,
        ]);
    }
}
