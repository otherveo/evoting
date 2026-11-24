<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Livewire\StaffPage;
use App\Models\Election;
use App\Services\TokenService;
use App\Services\VotingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
#[Title('Penggunaan Perangkat')]
class DeviceUsagePage extends StaffPage
{
    protected const ROLE = 'admin';

    use HandlesSafeErrors;
    use WithPagination;

    public string $electionId = '';

    public string $blockReason = '';

    public function mount(): void
    {
        $first = $this->elections()->first();
        $this->electionId = $first ? (string) $first->id : '';
    }

    public function selectElection(string $electionId): void
    {
        $this->electionId = $electionId;
        $this->resetPage();
    }

    /**
     * Update v7: Blokir manual perangkat oleh Admin (per pemilihan).
     */
    public function blockDevice(string $browserId, VotingService $voting): void
    {
        if ($this->electionId === '' || $browserId === '') {
            return;
        }

        try {
            /** @var \App\Models\StaffAccount $admin */
            $admin = Auth::guard('staff')->user();
            $result = $voting->blockDevice($this->electionId, $browserId, $admin->id, $this->blockReason);

            if (! $result['ok']) {
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return;
            }

            $this->blockReason = '';
            $this->dispatch('toast', message: 'Diblokir', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal memblokir perangkat.');
        }
    }

    /**
     * Update v7: Buka blokir manual perangkat oleh Admin (per pemilihan).
     */
    public function unblockDevice(string $browserId, VotingService $voting): void
    {
        if ($this->electionId === '' || $browserId === '') {
            return;
        }

        try {
            /** @var \App\Models\StaffAccount $admin */
            $admin = Auth::guard('staff')->user();
            $result = $voting->unblockDevice($this->electionId, $browserId, $admin->id);

            if (! $result['ok']) {
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return;
            }

            $this->dispatch('toast', message: 'Blokir dibuka', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal membuka blokir perangkat.');
        }
    }

    public function render()
    {
        $elections = $this->elections()->get();
        $usage = null;

        if ($this->electionId !== '') {
            $usage = DB::connection('staff')->table('evoting.v_device_usage')
                ->where('election_id', $this->electionId)
                ->orderByDesc('last_accessed_at')
                ->paginate(25);
        }

        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');

        return view('livewire.admin.device-usage-page', [
            'elections' => $elections,
            'usage' => $usage,
            'tz' => $tz,
        ]);
    }

    /**
     * Daftar pemilihan non-training (pola nama yang sama dengan direktori
     * paslon v6): Training/Simulasi/Tes/Latihan tidak tampil dan tidak dihitung.
     *
     * @return Builder<Election>
     */
    private function elections(): Builder
    {
        return Election::query()
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereRaw('name !~* ?', ['^[[:space:]]*(Train(?:ing)?|Simula(?:si|tion)s?|Tes(?:t(?:ing)?)?|Latih(?:an)?)'])
            ->orderByDesc('starts_at')
            ->orderBy('id');
    }

    /**
     * Label perangkat untuk Admin (tanpa nama pemilih): jenis browser + OS
     * + 6 karakter pengenal, mengikuti pola monitor operator.
     */
    public static function deviceLabel(?string $browserId, ?string $userAgent): string
    {
        return TokenService::shortBrowserLabel($browserId, $userAgent);
    }
}
