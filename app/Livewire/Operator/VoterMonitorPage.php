<?php

namespace App\Livewire\Operator;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Models\Election;
use App\Models\StaffAccount;
use App\Models\VoterRight;
use App\Services\TokenService;
use App\Services\VotingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Pantau Pemilih & Registrasi QR')]
class VoterMonitorPage extends Component
{
    use HandlesSafeErrors;

    public Election $election;

    public string $search = '';

    public string $statusFilter = 'all';

    // UI-010: Modal Registrasi Pemilih
    public bool $showRegisterModal = false;

    public string $voterName = '';

    public bool $confirmDifferentPerson = false;

    public int $expectedDuplicateCount = 0;

    /** @var list<array{id: string, name: string, status: string, created_at: string}> */
    public array $duplicateMatches = [];

    // UI-011: Modal Panel QR Sekali Tampil
    public bool $showQrModal = false;

    public string $issuedVoterName = '';

    public string $issuedVoterId = '';

    public string $issuedVotingUrl = '';

    public string $issuedQrSvg = '';

    public bool $issuedIsReissue = false;

    // UI-012: Modal Detail Hak Pilih, Reset Sesi, Terbitkan Ulang QR
    public bool $showDetailModal = false;

    public ?string $selectedVoterRightId = null;

    public string $resetReason = '';

    public string $reissueReason = '';

    public function mount(Election $election): void
    {
        $this->election = $election;
    }

    public function openRegisterModal(): void
    {
        $this->resetValidation();
        $this->voterName = '';
        $this->confirmDifferentPerson = false;
        $this->expectedDuplicateCount = 0;
        $this->duplicateMatches = [];
        $this->showRegisterModal = true;
    }

    public function closeRegisterModal(): void
    {
        $this->showRegisterModal = false;
        $this->voterName = '';
        $this->confirmDifferentPerson = false;
        $this->expectedDuplicateCount = 0;
        $this->duplicateMatches = [];
    }

    public function updatedVoterName(): void
    {
        $this->confirmDifferentPerson = false;
        $this->checkDuplicates();
    }

    private function checkDuplicates(): void
    {
        $normalized = VoterRight::normalizeName($this->voterName);
        if ($normalized === '') {
            $this->duplicateMatches = [];
            $this->expectedDuplicateCount = 0;

            return;
        }

        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');
        $rows = VoterRight::query()
            ->select(VoterRight::SAFE_COLUMNS)
            ->where('election_id', $this->election->id)
            ->where('name_normalized', $normalized)
            ->orderBy('created_at')
            ->get();

        $this->duplicateMatches = $rows->map(fn (VoterRight $vr) => [
            'id' => $vr->id,
            'name' => $vr->name,
            'status' => VoterRight::statusLabel($vr->status),
            'created_at' => $vr->created_at ? $vr->created_at->copy()->setTimezone($tz)->format('H:i:s') : '-',
        ])->all();

        $this->expectedDuplicateCount = count($this->duplicateMatches);
    }

    public function registerVoter(VotingService $voting, TokenService $tokens): void
    {
        $this->resetValidation();

        $this->validate([
            'voterName' => ['required', 'string', 'min:2', 'max:140'],
        ], [
            'voterName.required' => 'Nama lengkap pemilih wajib diisi setelah verifikasi identitas.',
            'voterName.min' => 'Nama lengkap pemilih minimal 2 karakter.',
        ]);

        try {
            $normalized = VoterRight::normalizeName($this->voterName);
            $currentDuplicatesCount = VoterRight::query()
                ->where('election_id', $this->election->id)
                ->where('name_normalized', $normalized)
                ->count();

            // FR-013, BR-003, BR-004, AC-015: Deteksi Nama Kembar & konfirmasi orang berbeda
            if ($currentDuplicatesCount > 0) {
                if (! $this->confirmDifferentPerson || $currentDuplicatesCount !== $this->expectedDuplicateCount) {
                    $this->checkDuplicates();
                    $this->confirmDifferentPerson = false;
                    $msg = 'Nama ini sudah terdaftar. Pastikan keduanya adalah orang yang berbeda lalu centang pernyataan konfirmasi di bawah.';
                    $this->addError('voterName', $msg);
                    $this->dispatch('toast', message: $msg, type: 'warning');

                    return;
                }
            }

            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();

            $result = $voting->createVoterRight(
                electionId: $this->election->id,
                name: $this->voterName,
                operatorId: $operator->id
            );

            if (! $result['ok'] || ! $result['raw_token']) {
                $this->addError('voterName', $result['message']);
                $this->dispatch('toast', message: $result['message'], type: 'error');

                return;
            }

            $cleanName = preg_replace('/\s+/u', ' ', trim($this->voterName)) ?? trim($this->voterName);
            $url = $tokens->votingUrl($result['raw_token']);
            $qrSvg = $tokens->renderQrSvg($url, 240);

            $this->closeRegisterModal();

            $this->issuedVoterName = $cleanName;
            $this->issuedVoterId = (string) $result['voter_right_id'];
            $this->issuedVotingUrl = $url;
            $this->issuedQrSvg = $qrSvg;
            $this->issuedIsReissue = false;
            $this->showQrModal = true;

            $this->dispatch('toast', message: "Hak pilih untuk {$cleanName} berhasil diterbitkan.", type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menerbitkan hak pilih pemilih. Silakan coba kembali.', 'voterName');
        }
    }

    public function closeQrModal(): void
    {
        // FR-014, AC-016: Hapus token dan SVG QR dari memori sehingga tidak dapat ditampilkan lagi
        $this->showQrModal = false;
        $this->issuedVoterName = '';
        $this->issuedVoterId = '';
        $this->issuedVotingUrl = '';
        $this->issuedQrSvg = '';
        $this->issuedIsReissue = false;
    }

    public function openDetailModal(string $voterRightId): void
    {
        $this->resetValidation();
        $this->selectedVoterRightId = $voterRightId;
        $this->resetReason = '';
        $this->reissueReason = '';
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedVoterRightId = null;
        $this->resetReason = '';
        $this->reissueReason = '';
    }

    public function resetVoterSession(VotingService $voting): void
    {
        if (! $this->selectedVoterRightId) {
            return;
        }

        // FR-030, BR-014, AC-033: Alasan reset sesi wajib diisi
        $this->validate([
            'resetReason' => ['required', 'string', 'min:3', 'max:300'],
        ], [
            'resetReason.required' => 'Alasan reset sesi wajib diisi.',
            'resetReason.min' => 'Alasan reset sesi minimal 3 karakter.',
        ]);

        try {
            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();

            $result = $voting->resetSession(
                voterRightId: $this->selectedVoterRightId,
                operatorId: $operator->id,
                reason: $this->resetReason
            );

            if (! $result['ok']) {
                $this->addError('resetReason', $result['message']);
                $this->dispatch('toast', message: $result['message'], type: 'error');

                return;
            }

            $this->resetReason = '';
            $this->dispatch('toast', message: $result['message'], type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mereset sesi memilih. Silakan coba kembali.', 'resetReason');
        }
    }

    public function reissueVoterQr(VotingService $voting, TokenService $tokens): void
    {
        if (! $this->selectedVoterRightId) {
            return;
        }

        // FR-029, BR-027, AC-032: Alasan penerbitan ulang QR wajib diisi
        $this->validate([
            'reissueReason' => ['required', 'string', 'min:3', 'max:300'],
        ], [
            'reissueReason.required' => 'Alasan penerbitan ulang QR wajib diisi.',
            'reissueReason.min' => 'Alasan penerbitan ulang minimal 3 karakter.',
        ]);

        try {
            /** @var VoterRight $vr */
            $vr = VoterRight::query()
                ->select(VoterRight::SAFE_COLUMNS)
                ->where('election_id', $this->election->id)
                ->findOrFail($this->selectedVoterRightId);

            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();

            $result = $voting->reissueToken(
                voterRightId: $vr->id,
                operatorId: $operator->id,
                reason: $this->reissueReason
            );

            if (! $result['ok'] || ! $result['raw_token']) {
                $this->addError('reissueReason', $result['message']);
                $this->dispatch('toast', message: $result['message'], type: 'error');

                return;
            }

            $url = $tokens->votingUrl($result['raw_token']);
            $qrSvg = $tokens->renderQrSvg($url, 240);

            $this->closeDetailModal();

            $this->issuedVoterName = $vr->name;
            $this->issuedVoterId = $vr->id;
            $this->issuedVotingUrl = $url;
            $this->issuedQrSvg = $qrSvg;
            $this->issuedIsReissue = true;
            $this->showQrModal = true;

            $this->dispatch('toast', message: 'QR lama dicabut dan QR baru berhasil diterbitkan.', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menerbitkan ulang kode QR. Silakan coba kembali.', 'reissueReason');
        }
    }

    public function render(VotingService $voting)
    {
        $this->election->refresh();
        View::share('activeElection', $this->election);

        $phase = $this->election->currentPhase();
        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');

        $allMonitorRows = DB::select('
            select
                m.*,
                (
                    select count(*)
                    from evoting.voter_rights vr_dup
                    where vr_dup.election_id = m.election_id
                      and vr_dup.name_normalized = vr.name_normalized
                ) as same_name_count,
                d_last.browser_id as last_browser_id,
                d_last.user_agent as last_user_agent,
                d_last.access_type as last_access_type
            from evoting.v_voter_right_monitor m
            join evoting.voter_rights vr on vr.id = m.id
            left join lateral (
                select d.browser_id, d.user_agent, d.access_type
                from evoting.device_access_logs d
                where d.voter_right_id = m.id
                order by d.accessed_at desc
                limit 1
            ) d_last on true
            where m.election_id = ?::uuid
            order by m.updated_at desc
        ', [$this->election->id]);

        $allCollection = collect($allMonitorRows);

        $totalRights = $allCollection->count();
        $countBelum = $allCollection->where('status', 'belum_memilih')->count();
        $countSedang = $allCollection->where('status', 'sedang_memilih')->count();
        $countSudah = $allCollection->where('status', 'sudah_memilih')->count();
        $countExpired = $allCollection->where('session_expired', true)->count();
        $countDeviceWarning = $allCollection->where('device_warning', true)->count();
        $countAttention = $allCollection->filter(fn ($r) => $r->session_expired || $r->device_warning)->count();

        $filtered = $allCollection->filter(function ($row) {
            if ($this->statusFilter === 'belum_memilih' && $row->status !== 'belum_memilih') {
                return false;
            }
            if ($this->statusFilter === 'sedang_memilih' && $row->status !== 'sedang_memilih') {
                return false;
            }
            if ($this->statusFilter === 'sudah_memilih' && $row->status !== 'sudah_memilih') {
                return false;
            }
            if ($this->statusFilter === 'attention' && ! $row->session_expired && ! $row->device_warning) {
                return false;
            }
            if (trim($this->search) !== '') {
                $needle = mb_strtolower(trim($this->search));
                if (! str_contains(mb_strtolower((string) $row->name), $needle)) {
                    return false;
                }
            }

            return true;
        })->values();

        $integrity = $voting->integrityReport($this->election->id);

        $detailData = null;
        if ($this->showDetailModal && $this->selectedVoterRightId) {
            $monitorItem = $allCollection->firstWhere('id', $this->selectedVoterRightId);
            if ($monitorItem) {
                $accessLogs = DB::select('
                    select id, access_type, browser_id, user_agent, ip_address::text as ip_address, accessed_at
                    from evoting.device_access_logs
                    where voter_right_id = ?::uuid
                    order by accessed_at desc
                ', [$this->selectedVoterRightId]);

                $sharedRights = DB::select('
                    select distinct vr2.id, vr2.name, vr2.status, d2.browser_id
                    from evoting.device_access_logs d1
                    join evoting.device_access_logs d2 on d2.browser_id = d1.browser_id
                    join evoting.voter_rights vr2 on vr2.id = d2.voter_right_id
                    where d1.voter_right_id = ?::uuid
                      and vr2.election_id = ?::uuid
                      and vr2.id <> ?::uuid
                ', [$this->selectedVoterRightId, $this->election->id, $this->selectedVoterRightId]);

                $sessionExpiresAt = $monitorItem->session_started_at
                    ? CarbonImmutable::parse($monitorItem->session_started_at)->addMinutes(10)->setTimezone($tz)
                    : null;

                $detailData = [
                    'row' => $monitorItem,
                    'access_logs' => $accessLogs,
                    'shared_rights' => $sharedRights,
                    'session_expires_at' => $sessionExpiresAt,
                ];
            }
        }

        return view('livewire.operator.voter-monitor-page', [
            'phase' => $phase,
            'tz' => $tz,
            'voterRows' => $filtered,
            'totalRights' => $totalRights,
            'countBelum' => $countBelum,
            'countSedang' => $countSedang,
            'countSudah' => $countSudah,
            'countExpired' => $countExpired,
            'countDeviceWarning' => $countDeviceWarning,
            'countAttention' => $countAttention,
            'integrity' => $integrity,
            'detailData' => $detailData,
        ]);
    }
}
