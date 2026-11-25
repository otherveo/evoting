<?php

namespace App\Livewire\Voter;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Models\Candidate;
use App\Models\PublicElection;
use App\Services\TokenService;
use App\Services\VotingService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.voter')]
#[Title('Bilik Suara Digital')]
class BallotPage extends Component
{
    use HandlesSafeErrors;

    #[Locked]
    public string $token = '';

    /**
     * verifikasi | memilih | selesai | kendala | ditolak_perangkat
     * (ditolak_perangkat = Update v7: URL tidak valid di perangkat ini karena
     * batas 2x atau blokir manual Admin — tanpa tombol konfirmasi apa pun)
     */
    #[Locked]
    public string $stage = 'verifikasi';

    #[Locked]
    public ?string $electionId = null;

    public string $electionName = 'Pemilihan Sekolah';

    public bool $isResumedSession = false;

    public ?string $sessionExpiresAtIso = null;

    public string $sessionExpiresLabel = '';

    // Perubahan 3.2: Gerbang Persetujuan Operator untuk Perangkat Bersama
    #[Locked]
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

    private function browserId(): string
    {
        return app(TokenService::class)->resolveOrCreateBrowserId(request());
    }

    public function mount(string $token, VotingService $voting): void
    {
        $this->token = trim($token);

        try {
            $openResult = $voting->openQr(
                rawToken: $this->token,
                browserId: $this->browserId(),
                userAgent: request()->userAgent(),
                ip: request()->ip()
            );

            if ($openResult['election_id']) {
                $this->electionId = $openResult['election_id'];
                $el = PublicElection::find($this->electionId);
                if ($el) {
                    $this->electionName = $el->name;
                }
            }

            if ($openResult['code'] === 'already_voted') {
                $this->stage = 'selesai';

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

            // Update v7: URL tidak valid di perangkat ini (batas 2x / blokir Admin).
            if ($openResult['code'] === 'device_limit_reached') {
                $this->setDeviceLimitState('device_limit_reached');

                return;
            }

            if ($openResult['code'] === 'device_blocked') {
                $this->setDeviceLimitState('device_blocked');

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
                browserId: $this->browserId(),
                userAgent: request()->userAgent(),
                ip: request()->ip()
            );

            if ($result['election_id'] && ! $this->electionId) {
                $this->electionId = $result['election_id'];
                $el = PublicElection::find($this->electionId);
                if ($el) {
                    $this->electionName = $el->name;
                }
            }

            if ($result['code'] === 'already_voted') {
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

            // Update v7: URL tidak valid di perangkat ini (batas 2x / blokir Admin).
            if (in_array($result['code'], ['device_limit_reached', 'device_blocked'], true)) {
                $this->waitingForDeviceApproval = false;
                $this->setDeviceLimitState($result['code']);
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
            $res = $voting->checkDeviceApproval($this->token, $this->browserId());

            if ($res['code'] === 'device_blocked') {
                $this->setDeviceLimitState('device_blocked');
                $this->dispatch('step-changed');

                return 'error';
            }

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

    public function confirmVote(VotingService $voting, string $candidateId): void
    {
        if ($this->stage !== 'memilih' || $this->waitingForDeviceApproval || ! $this->electionId) {
            return;
        }

        try {
            $roster = Candidate::query()->where('election_id', $this->electionId)->pluck('id')->all();
            if (! in_array($candidateId, $roster, true)) {
                $this->dispatch('ballot-edit');
                $this->dispatch('toast', message: 'Pilih pasangan calon yang terdaftar pada pemilihan ini.', type: 'warning');

                return;
            }
            $result = $voting->confirmVote(
                rawToken: $this->token,
                browserId: $this->browserId(),
                candidateId: $candidateId,
                userAgent: request()->userAgent(),
                ip: request()->ip()
            );

            if ($result['ok'] || $result['code'] === 'already_voted') {
                $this->stage = 'selesai';
                $this->dispatch('step-changed');
                $this->dispatch('toast', message: 'Suara Anda berhasil disimpan secara anonim.', type: 'success');

                return;
            }

            if ($result['code'] === 'invalid_candidate') {
                $this->dispatch('ballot-edit');
                $this->dispatch('toast', message: $result['message'], type: 'error');

                return;
            }

            // Update v7: perangkat diblokir / batas 2x tercapai saat konfirmasi.
            if (in_array($result['code'], ['device_limit_reached', 'device_blocked'], true)) {
                $this->setDeviceLimitState($result['code']);
                $this->dispatch('step-changed');

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

    /**
     * Update v7: URL tidak valid di perangkat ini (batas 2x penggunaan atau
     * blokir manual Admin). Stage khusus 'ditolak_perangkat': hanya
     * pemberitahuan + tombol X untuk menutup popup, tanpa tombol konfirmasi
     * apa pun — pemilih harus memakai perangkat lain.
     */
    private function setDeviceLimitState(string $code): void
    {
        $this->stage = 'ditolak_perangkat';
        $this->waitingForDeviceApproval = false;
        $this->errorCode = $code;

        if ($code === 'device_blocked') {
            $this->errorTitle = 'Perangkat Ini Diblokir';
            $this->errorDescription = 'Perangkat ini diblokir oleh Admin. Silakan gunakan perangkat lain untuk melanjutkan proses.';
            $this->errorNextStep = 'Silakan gunakan perangkat lain untuk membuka tautan QR Anda, atau hubungi Operator di meja verifikasi.';
        } else {
            $this->errorTitle = 'Batas Penggunaan Perangkat Tercapai';
            $this->errorDescription = 'Satu perangkat yang sama hanya diizinkan maksimal 2x digunakan. Silakan cari perangkat lain untuk melanjutkan proses.';
            $this->errorNextStep = 'Silakan cari perangkat lain untuk membuka tautan QR Anda dan melanjutkan proses memilih.';
        }
    }

    private function setTroubleState(string $code, string $message): void
    {
        $this->stage = 'kendala';
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

        if ($this->stage === 'memilih' && ! $this->waitingForDeviceApproval && $this->electionId) {
            $candidates = Candidate::query()
                ->where('election_id', $this->electionId)
                ->orderBy('number')
                ->get();
        }

        return view('livewire.voter.ballot-page', ['candidates' => $candidates]);
    }
}
