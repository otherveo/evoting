<?php

namespace App\Livewire\Operator;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Livewire\StaffPage;
use App\Models\Election;
use App\Models\StaffAccount;
use App\Models\VoterRight;
use App\Services\TokenService;
use App\Services\VotingService;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
#[Title('Pantau Pemilih & Registrasi QR')]
class VoterMonitorPage extends StaffPage
{
    protected const ROLE = 'operator';

    use HandlesSafeErrors;
    use WithPagination;

    public Election $election;

    public string $search = '';

    public string $statusFilter = 'all';

    // UI-010: Modal Registrasi Pemilih
    public bool $showRegisterModal = false;

    public string $voterName = '';

    #[Locked]
    public string $registrationId;

    #[Locked]
    public int $registrationReviewVersion = 0;

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
        $this->registrationId = (string) Str::uuid();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openRegisterModal(): void
    {
        $this->resetValidation();
        $this->voterName = '';
        $this->registrationId = (string) Str::uuid();
        $this->showRegisterModal = true;
    }

    public function closeRegisterModal(): void
    {
        $this->showRegisterModal = false;
        $this->voterName = '';
    }

    public function registerVoter(VotingService $voting, TokenService $tokens, string $name, int $confirmedMatches): void
    {
        $this->voterName = $name;
        $this->resetValidation();

        $this->validate([
            'voterName' => ['required', 'string', 'min:2', 'max:140'],
        ], [
            'voterName.required' => 'Nama lengkap pemilih wajib diisi setelah verifikasi identitas.',
            'voterName.min' => 'Nama lengkap pemilih minimal 2 karakter.',
        ]);

        try {
            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();

            $result = $voting->createVoterRight(
                electionId: $this->election->id,
                name: $this->voterName,
                operatorId: $operator->id,
                registrationId: $this->registrationId,
                confirmedMatches: $confirmedMatches
            );

            if (! $result['ok'] || ! $result['raw_token']) {
                if ($result['code'] === 'duplicate_name') {
                    $this->registrationReviewVersion++;
                }
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

    public function openDetailModal(string $voterRightId, string $intent = 'detail'): void
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

            $this->dispatch('toast', message: 'Berhasil', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menerbitkan ulang kode QR. Silakan coba kembali.', 'reissueReason');
        }
    }

    public function approveSharedDevice(string $voterRightId, VotingService $voting): void
    {
        if (! $this->acquireIdempotencyLock('approve_device:'.$voterRightId, 3)) {
            return;
        }

        try {
            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();
            $result = $voting->decideDeviceApproval($voterRightId, $operator->id, 'setujui');

            if (! $result['ok']) {
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return;
            }

            $this->dispatch('toast', message: 'Disetujui', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menyetujui akses perangkat bersama.');
        }
    }

    public function rejectSharedDevice(string $voterRightId, VotingService $voting): void
    {
        if (! $this->acquireIdempotencyLock('reject_device:'.$voterRightId, 3)) {
            return;
        }

        try {
            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();
            $result = $voting->decideDeviceApproval($voterRightId, $operator->id, 'tolak');

            if (! $result['ok']) {
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return;
            }

            $this->dispatch('toast', message: 'Ditolak', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menolak akses perangkat bersama.');
        }
    }

    public function render(VotingService $voting)
    {
        View::share('activeElection', $this->election);

        $phase = $this->election->currentPhase();
        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');

        $allMonitorRows = DB::connection('staff')->select('
            with ir as materialized (
                select total_votes, is_consistent
                from evoting.fn_integrity_report(?::uuid)
            )
            select
                m.*,
                vr.name_normalized,
                vr.created_at as vr_created_at,
                (
                    select count(*)
                    from evoting.voter_rights vr_dup
                    where vr_dup.election_id = m.election_id
                      and vr_dup.name_normalized = vr.name_normalized
                ) as same_name_count,
                ir.total_votes as total_votes_cast,
                ir.is_consistent as ir_consistent,
                d_last.browser_id as last_browser_id,
                d_last.user_agent as last_user_agent,
                d_last.access_type as last_access_type,
                (
                    select coalesce(jsonb_agg(jsonb_build_object(
                        \'id\', s.id,
                        \'short_id\', upper( left(s.id::text, 7) ),
                        \'name\', s.name,
                        \'browser_hash\', upper( left(encode(sha256(s.browser_id::bytea), \'hex\'), 6) )
                    )), \'[]\'::jsonb)
                    from (
                        select distinct vr2.id, vr2.name, d1.browser_id
                        from evoting.device_access_logs d1
                        join evoting.device_access_logs d2 on d2.browser_id = d1.browser_id
                        join evoting.voter_rights vr2 on vr2.id = d2.voter_right_id
                        where d1.voter_right_id = m.id
                          and vr2.election_id = m.election_id
                          and vr2.id <> m.id
                    ) s
                ) as shared_peers_json
            from evoting.v_voter_right_monitor m
            cross join ir
            join evoting.voter_rights vr on vr.id = m.id
            left join lateral (
                select d.browser_id, d.user_agent, d.access_type
                from evoting.device_access_logs d
                where d.voter_right_id = m.id
                order by d.accessed_at desc
                limit 1
            ) d_last on true
            where m.election_id = ?::uuid
            order by vr.created_at desc, vr.id asc
        ', [$this->election->id, $this->election->id]);

        $allCollection = collect($allMonitorRows)->map(function ($row) {
            $peers = [];
            if (! empty($row->shared_peers_json)) {
                $decoded = json_decode((string) $row->shared_peers_json, true);
                if (is_array($decoded)) {
                    $peers = $decoded;
                }
            }
            $row->shared_peers = $peers;
            $row->shared_group_tag = ! empty($peers) && isset($peers[0]['browser_hash'])
                ? '#'.$peers[0]['browser_hash']
                : ($row->last_browser_id ? '#'.strtoupper(substr(hash('sha256', (string) $row->last_browser_id), 0, 6)) : null);

            return $row;
        });

        /** @var StaffAccount|null $currentOperator */
        $currentOperator = Auth::guard('staff')->user();
        $currentOperatorId = $currentOperator?->id;

        $pendingApprovals = $allCollection->filter(function ($r) use ($currentOperatorId) {
            $isWaiting = $r->status === 'sedang_memilih'
                && ($r->device_approval_status ?? 'tidak_perlu') === 'menunggu'
                && ! $r->session_expired;

            if (! $isWaiting) {
                return false;
            }

            return $r->issued_by !== null && $r->issued_by === $currentOperatorId;
        })->values();

        $totalRights = $allCollection->count();
        $countBelum = $allCollection->where('status', 'belum_memilih')->count();
        $countSedang = $allCollection->where('status', 'sedang_memilih')->count();
        $countSudah = $allCollection->where('status', 'sudah_memilih')->count();
        $countExpired = $allCollection->where('session_expired', true)->count();
        $countDeviceWarning = $allCollection->where('device_warning', true)->count();
        $countAttention = $allCollection->filter(fn ($r) => $r->session_expired || $r->device_warning)->count();

        $totalVotesCast = $totalRights > 0 ? (int) ($allCollection->first()->total_votes_cast ?? 0) : 0;
        $integrity = [
            'ok' => true,
            'code' => 'ok',
            'total_votes' => $totalVotesCast,
            'total_voted' => $countSudah,
            'total_rights' => $totalRights,
            'is_consistent' => ($totalVotesCast === $countSudah) && ($countSudah <= $totalRights),
        ];

        $existingVotersForClient = $allCollection->map(fn ($row) => [
            'id' => (string) $row->id,
            'name' => (string) $row->name,
            'normalized' => (string) $row->name_normalized,
            'status' => VoterRight::statusLabel((string) $row->status),
            'created_at' => ! empty($row->vr_created_at)
                ? CarbonImmutable::parse($row->vr_created_at)->setTimezone($tz)->format('H:i:s')
                : '-',
        ])->values()->all();

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

        $perPage = 25;
        $totalFiltered = $filtered->count();
        $lastPage = max(1, (int) ceil($totalFiltered / $perPage));
        $currentPage = min(max(1, (int) $this->getPage()), $lastPage);
        $pageItems = $filtered->forPage($currentPage, $perPage)->values();

        $voterPaginator = new LengthAwarePaginator(
            items: $pageItems,
            total: $totalFiltered,
            perPage: $perPage,
            currentPage: $currentPage,
            options: ['path' => Paginator::resolveCurrentPath()]
        );

        $isPollingPage = $currentPage === 1;
        if (! $isPollingPage) {
            $pendingApprovals = collect();
        }

        $detailData = null;
        if ($this->showDetailModal && $this->selectedVoterRightId) {
            $monitorItem = $allCollection->firstWhere('id', $this->selectedVoterRightId);
            if ($monitorItem) {
                $accessLogs = DB::connection('staff')->select('
                    select id, access_type, browser_id, user_agent, ip_address::text as ip_address, accessed_at
                    from evoting.device_access_logs
                    where voter_right_id = ?::uuid
                    order by accessed_at desc
                ', [$this->selectedVoterRightId]);

                $sharedRights = DB::connection('staff')->select('
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
            'voterRows' => $voterPaginator->getCollection(),
            'voterPaginator' => $voterPaginator,
            'isPollingPage' => $isPollingPage,
            'pendingApprovals' => $pendingApprovals,
            'existingVotersForClient' => $existingVotersForClient,
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
