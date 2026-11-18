<?php

namespace App\Livewire\Voter;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Models\Candidate;
use App\Models\Election;
use App\Services\TokenService;
use App\Services\VotingService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.voter')]
#[Title('Bilik Suara Digital')]
class BallotPage extends Component
{
    use HandlesSafeErrors;

    public string $token = '';

    public string $browserId = '';

    /**
     * verifikasi | memilih | konfirmasi | selesai | kendala
     */
    public string $stage = 'verifikasi';

    public ?string $electionId = null;

    public string $electionName = 'Pemilihan Sekolah';

    public ?string $selectedCandidateId = null;

    public bool $isResumedSession = false;

    public ?string $sessionExpiresAtIso = null;

    public string $sessionExpiresLabel = '';

    // Perubahan 3.2: Gerbang Persetujuan Operator untuk Perangkat Bersama
    public bool $waitingForDeviceApproval = false;

    /**
     * idle | approved | pending | rejected
     */
    public string $approvalButtonState = 'idle';

    // UI-017: Detail Kendala
    public string $errorCode = '';

    public string $errorTitle = '';

    public string $errorDescription = '';

    public string $errorNextStep = '';

    public function mount(string $token, TokenService $tokens, VotingService $voting): void
    {
        $this->token = trim($token);
        $this->browserId = $tokens->resolveOrCreateBrowserId(request());

        try {
            $openResult = $voting->openQr(
                rawToken: $this->token,
                browserId: $this->browserId,
                userAgent: request()->userAgent(),
                ip: request()->ip()
            );

            if ($openResult['election_id']) {
                $this->electionId = $openResult['election_id'];
                $el = Election::find($this->electionId);
                if ($el) {
                    $this->electionName = $el->name;
                }
            }

            if ($openResult['code'] === 'already_voted') {
                $this->stage = 'selesai';
                $this->selectedCandidateId = null;

                return;
            }

            if ($openResult['code'] === 'approval_required') {
                $this->stage = 'memilih';
                $this->waitingForDeviceApproval = true;
                $this->approvalButtonState = 'idle';
                $this->applySessionExpiry($openResult['session_expires_at']);

                return;
            }

            if ($openResult['code'] === 'device_rejected') {
                $this->setTroubleState('device_rejected', 'Aksesmu telah ditolak oleh operator, silakan minta QR baru jika kamu memang punya hak suara');

                return;
            }

            if (! $openResult['ok']) {
                $this->setTroubleState($openResult['code'], $openResult['message']);

                return;
            }

            if ($openResult['status'] === 'sedang_memilih') {
                $this->stage = 'memilih';
                $this->isResumedSession = true;
                $this->waitingForDeviceApproval = false;
                $this->applySessionExpiry($openResult['session_expires_at']);

                return;
            }

            $this->stage = 'verifikasi';
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Terjadi kendala saat membuka halaman bilik suara.');
            $this->setTroubleState('system_error', 'Terjadi kendala saat memvalidasi kode QR Anda.');
        }
    }

    public function startVoting(VotingService $voting): void
    {
        try {
            $result = $voting->startVoting(
                rawToken: $this->token,
                browserId: $this->browserId,
                userAgent: request()->userAgent(),
                ip: request()->ip()
            );

            if ($result['election_id'] && ! $this->electionId) {
                $this->electionId = $result['election_id'];
                $el = Election::find($this->electionId);
                if ($el) {
                    $this->electionName = $el->name;
                }
            }

            if ($result['code'] === 'already_voted') {
                $this->selectedCandidateId = null;
                $this->waitingForDeviceApproval = false;
                $this->stage = 'selesai';
                $this->dispatch('step-changed');

                return;
            }

            if ($result['code'] === 'approval_required') {
                $this->applySessionExpiry(
                    CarbonImmutable::now('UTC')->addMilliseconds(VotingService::$dbOffsetMs)->addMinutes(10)
                );
                $this->stage = 'memilih';
                $this->waitingForDeviceApproval = true;
                $this->approvalButtonState = 'idle';
                $this->dispatch('step-changed');
                $this->dispatch('toast', message: 'Menunggu izin', type: 'warning');

                return;
            }

            if ($result['code'] === 'approval_expired') {
                $this->waitingForDeviceApproval = false;
                $this->stage = 'verifikasi';
                $this->dispatch('step-changed');
                $this->dispatch('toast', message: 'Kedaluwarsa', type: 'warning');

                return;
            }

            if ($result['code'] === 'device_rejected') {
                $this->waitingForDeviceApproval = false;
                $this->setTroubleState('device_rejected', 'Aksesmu telah ditolak oleh operator, silakan minta QR baru jika kamu memang punya hak suara');
                $this->dispatch('step-changed');

                return;
            }

            if (! $result['ok']) {
                $this->waitingForDeviceApproval = false;
                $this->setTroubleState($result['code'], $result['message']);
                $this->dispatch('step-changed');

                return;
            }

            $this->applySessionExpiry(
                CarbonImmutable::now('UTC')->addMilliseconds(VotingService::$dbOffsetMs)->addMinutes(10)
            );
            $this->waitingForDeviceApproval = false;
            $this->stage = 'memilih';
            $this->dispatch('step-changed');
            $this->dispatch('toast', message: 'Berhasil', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal memulai sesi memilih. Silakan coba kembali.');
        }
    }

    public function pollDeviceApproval(VotingService $voting): void
    {
        if (! $this->waitingForDeviceApproval) {
            return;
        }

        $this->checkDeviceApproval($voting, false);
    }

    public function checkDeviceApprovalManual(VotingService $voting): string
    {
        return $this->checkDeviceApproval($voting, true);
    }

    public function checkDeviceApproval(VotingService $voting, bool $isManual = false): string
    {
        try {
            $res = $voting->checkDeviceApproval($this->token, $this->browserId);

            if ($res['code'] === 'approved') {
                $this->approvalButtonState = 'approved';
                $this->waitingForDeviceApproval = false;
                if ($res['session_expires_at']) {
                    $this->applySessionExpiry($res['session_expires_at']);
                }
                $this->stage = 'memilih';
                $this->dispatch('toast', message: 'Diizinkan', type: 'success');

                return 'approved';
            }

            if ($res['code'] === 'pending') {
                if ($isManual) {
                    $this->approvalButtonState = 'pending';
                    $this->dispatch('toast', message: 'Belum diizinkan', type: 'warning');
                }
                $this->waitingForDeviceApproval = true;

                return 'pending';
            }

            if ($res['code'] === 'rejected') {
                $this->approvalButtonState = 'rejected';
                $this->waitingForDeviceApproval = false;
                $this->setTroubleState('device_rejected', 'Aksesmu telah ditolak oleh operator, silakan minta QR baru jika kamu memang punya hak suara');
                $this->dispatch('toast', message: 'Tidak diizinkan', type: 'error');

                return 'rejected';
            }

            if ($res['code'] === 'approval_expired') {
                $this->waitingForDeviceApproval = false;
                $this->approvalButtonState = 'idle';
                $this->stage = 'verifikasi';
                $this->dispatch('toast', message: 'Kedaluwarsa', type: 'warning');

                return 'expired';
            }

            $this->waitingForDeviceApproval = false;
            $this->setTroubleState($res['code'], $res['message']);

            return 'error';
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal memeriksa status persetujuan operator.');

            return 'error';
        }
    }

    public function selectCandidate(string $candidateId): void
    {
        if ($this->stage !== 'memilih' || $this->waitingForDeviceApproval || ! $this->electionId) {
            return;
        }

        $exists = Candidate::query()
            ->where('election_id', $this->electionId)
            ->where('id', $candidateId)
            ->exists();

        if ($exists) {
            $this->selectedCandidateId = $candidateId;
        }
    }

    public function proceedToConfirm(): void
    {
        if ($this->stage !== 'memilih') {
            return;
        }

        if (! $this->selectedCandidateId) {
            $this->dispatch('toast', message: 'Pilih satu pasangan calon terlebih dahulu.', type: 'warning');

            return;
        }

        $this->stage = 'konfirmasi';
        $this->dispatch('step-changed');
    }

    public function backToBallot(): void
    {
        if ($this->stage !== 'konfirmasi') {
            return;
        }

        $this->stage = 'memilih';
        $this->dispatch('step-changed');
    }

    public function confirmVote(VotingService $voting, ?string $candidateId = null): void
    {
        if ($candidateId !== null && $candidateId !== '') {
            $this->selectedCandidateId = $candidateId;
        }

        if (! $this->selectedCandidateId) {
            $this->stage = 'memilih';

            return;
        }

        try {
            $result = $voting->confirmVote(
                rawToken: $this->token,
                browserId: $this->browserId,
                candidateId: $this->selectedCandidateId,
                userAgent: request()->userAgent(),
                ip: request()->ip()
            );

            if ($result['ok'] || $result['code'] === 'already_voted') {
                // BR-028: Hapus pilihan dari memori agar halaman selesai tidak pernah menampilkan kembali pilihan
                $this->selectedCandidateId = null;
                $this->stage = 'selesai';
                $this->dispatch('step-changed');
                $this->dispatch('toast', message: 'Suara Anda berhasil disimpan secara anonim.', type: 'success');

                return;
            }

            if ($result['code'] === 'invalid_candidate') {
                $this->selectedCandidateId = null;
                $this->stage = 'memilih';
                $this->dispatch('toast', message: $result['message'], type: 'error');

                return;
            }

            $this->setTroubleState($result['code'], $result['message']);
            $this->dispatch('step-changed');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Koneksi terputus saat menyimpan suara. Silakan coba tekan Konfirmasi pilihan kembali.');
        }
    }

    private function applySessionExpiry(?CarbonImmutable $expiresAt): void
    {
        if (! $expiresAt) {
            return;
        }
        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');
        $local = $expiresAt->setTimezone($tz);
        $this->sessionExpiresAtIso = $expiresAt->toIso8601String();
        $this->sessionExpiresLabel = $local->format('H:i:s').' ('.($tz === 'Asia/Makassar' ? 'WITA' : ($tz === 'Asia/Jakarta' ? 'WIB' : $local->format('T'))).')';
    }

    private function setTroubleState(string $code, string $message): void
    {
        $this->stage = 'kendala';
        $this->selectedCandidateId = null;
        $this->errorCode = $code;

        match ($code) {
            'device_rejected' => [
                $this->errorTitle = 'Akses Ditolak oleh Operator',
                $this->errorDescription = 'Aksesmu telah ditolak oleh operator, silakan minta QR baru jika kamu memang punya hak suara',
                $this->errorNextStep = 'Silakan hubungi petugas Operator di meja verifikasi untuk meminta kode QR baru apabila Anda memiliki hak pilih.',
            ],
            'invalid_token' => [
                $this->errorTitle = 'Kode QR Tidak Valid atau Sudah Dicabut',
                $this->errorDescription = 'Tautan QR yang Anda buka tidak dikenali atau sudah diganti dengan QR baru.',
                $this->errorNextStep = 'Silakan hubungi Operator di meja verifikasi untuk memeriksa status hak pilih Anda.',
            ],
            'wrong_phase' => [
                $this->errorTitle = 'Pemilihan Sedang Tidak Berlangsung',
                $this->errorDescription = $message ?: 'Saat ini berada di luar jadwal pemungutan suara.',
                $this->errorNextStep = 'Pemungutan suara hanya dapat dilakukan setelah Waktu Mulai tercapai dan sebelum Waktu Selesai berakhir.',
            ],
            'session_expired' => [
                $this->errorTitle = 'Batas Waktu Sesi Memilih (10 Menit) Habis',
                $this->errorDescription = 'Demi keamanan bilik suara, sesi memilih dibatasi selama 10 menit sejak tombol Mulai memilih ditekan.',
                $this->errorNextStep = 'Silakan hubungi Operator untuk melakukan Reset Sesi, lalu pindai ulang QR Anda.',
            ],
            'wrong_browser' => [
                $this->errorTitle = 'Sesi Sedang Aktif di Browser atau Perangkat Lain',
                $this->errorDescription = 'Token QR ini sedang dalam status Sedang memilih pada browser atau perangkat yang berbeda.',
                $this->errorNextStep = 'Lanjutkan memilih pada browser awal tempat Anda menekan Mulai memilih, atau hubungi Operator untuk melakukan Reset Sesi.',
            ],
            default => [
                $this->errorTitle = 'Tidak Dapat Melanjutkan Proses Memilih',
                $this->errorDescription = $message ?: 'Terjadi kendala saat memvalidasi sesi memilih Anda.',
                $this->errorNextStep = 'Silakan hubungi Operator di meja verifikasi untuk mendapatkan bantuan.',
            ],
        };
    }

    public function render()
    {
        $candidates = collect();
        $selectedCandidate = null;

        if ($this->electionId && in_array($this->stage, ['memilih', 'konfirmasi'], true)) {
            $candidates = Candidate::query()
                ->where('election_id', $this->electionId)
                ->orderBy('number')
                ->get();

            if ($this->selectedCandidateId) {
                $selectedCandidate = $candidates->firstWhere('id', $this->selectedCandidateId);
            }
        }

        $stepIndex = match ($this->stage) {
            'verifikasi' => 1,
            'memilih' => 2,
            'konfirmasi' => 3,
            'selesai' => 4,
            default => 0,
        };

        return view('livewire.voter.ballot-page', [
            'candidates' => $candidates,
            'selectedCandidate' => $selectedCandidate,
            'stepIndex' => $stepIndex,
        ]);
    }
}
