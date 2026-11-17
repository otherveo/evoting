<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Models\StaffAccount;
use App\Services\AuditService;
use App\Services\TotpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Aktivasi Akun Staf')]
class ActivateAccountPage extends Component
{
    use HandlesSafeErrors;

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public string $totpCode = '';

    public string $pendingTotpSecret = '';

    public ?string $errorMessage = null;

    public function mount(TotpService $totp): void
    {
        /** @var StaffAccount|null $user */
        $user = Auth::guard('staff')->user();
        if (! $user) {
            $this->redirectRoute('login');

            return;
        }

        if (! $user->needsActivation()) {
            $this->redirectRoute($user->isAdmin() ? 'admin.operators' : 'operator.elections');

            return;
        }

        if ($user->isAdmin() && ! $user->totp_enabled) {
            $existing = session('pending_totp_secret');
            if (! is_string($existing) || strlen($existing) < 16) {
                $existing = $totp->generateSecret();
                session(['pending_totp_secret' => $existing]);
            }
            $this->pendingTotpSecret = $existing;
        }
    }

    public function updatePassword(AuditService $audit): void
    {
        $this->errorMessage = null;

        /** @var StaffAccount|null $user */
        $user = Auth::guard('staff')->user();
        if (! $user) {
            $this->redirectRoute('login');

            return;
        }

        $minLen = (int) config('evoting.min_password_length', 8);

        $this->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', "min:{$minLen}", 'same:newPasswordConfirmation'],
            'newPasswordConfirmation' => ['required', 'string'],
        ], [
            'currentPassword.required' => 'Kata sandi sementara saat ini wajib diisi.',
            'newPassword.required' => 'Kata sandi baru wajib diisi.',
            'newPassword.min' => "Kata sandi baru minimal {$minLen} karakter.",
            'newPassword.same' => 'Konfirmasi kata sandi baru tidak cocok.',
            'newPasswordConfirmation.required' => 'Konfirmasi kata sandi baru wajib diisi.',
        ]);

        try {
            if (! Hash::check($this->currentPassword, $user->password_hash)) {
                $this->errorMessage = 'Kata sandi sementara saat ini tidak sesuai.';
                $this->dispatch('toast', message: $this->errorMessage, type: 'error');

                return;
            }

            if (Hash::check($this->newPassword, $user->password_hash)) {
                $this->errorMessage = 'Kata sandi baru harus berbeda dari kata sandi sementara.';
                $this->dispatch('toast', message: $this->errorMessage, type: 'error');

                return;
            }

            $user->update([
                'password_hash' => Hash::make($this->newPassword),
                'must_change_password' => false,
            ]);

            $audit->log(
                actorType: $user->role,
                actorId: $user->id,
                action: 'ganti_sandi_aktivasi',
                objectType: 'staff_account',
                objectId: $user->id,
                detail: ['username' => $user->username]
            );

            $this->currentPassword = '';
            $this->newPassword = '';
            $this->newPasswordConfirmation = '';

            $user->refresh();
            Auth::guard('staff')->setUser($user);

            if (! $user->needsActivation()) {
                session()->flash('success', 'Aktivasi akun berhasil. Selamat bertugas.');
                $this->redirectRoute($user->isAdmin() ? 'admin.operators' : 'operator.elections');

                return;
            }

            $this->dispatch('toast', message: 'Kata sandi baru tersimpan. Lanjutkan Langkah 2 untuk mengaktifkan 2FA Admin.', type: 'success');
        } catch (Throwable $e) {
            $this->errorMessage = 'Terjadi kendala saat menyimpan kata sandi baru. Silakan coba kembali.';
            $this->reportSafeError($e, $this->errorMessage);
        }
    }

    public function confirmTotp(TotpService $totp, AuditService $audit): void
    {
        $this->errorMessage = null;

        /** @var StaffAccount|null $user */
        $user = Auth::guard('staff')->user();
        if (! $user || ! $user->isAdmin()) {
            $this->redirectRoute('login');

            return;
        }

        if ($user->must_change_password) {
            $this->errorMessage = 'Selesaikan penggantian kata sandi sementara pada Langkah 1 terlebih dahulu.';
            $this->dispatch('toast', message: $this->errorMessage, type: 'warning');

            return;
        }

        $this->validate([
            'totpCode' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'totpCode.required' => 'Kode konfirmasi 6 digit wajib diisi.',
            'totpCode.regex' => 'Kode konfirmasi harus berupa 6 angka dari aplikasi autentikator.',
        ]);

        try {
            if ($this->pendingTotpSecret === '' || ! $totp->verifyCode($this->pendingTotpSecret, $this->totpCode, $user->id)) {
                $this->errorMessage = 'Kode TOTP tidak sesuai. Pastikan waktu perangkat Anda sinkron dan pindai ulang kode QR.';
                $this->dispatch('toast', message: $this->errorMessage, type: 'error');

                return;
            }

            $user->update([
                'totp_secret' => $this->pendingTotpSecret,
                'totp_enabled' => true,
            ]);

            session()->forget('pending_totp_secret');
            session(['staff_2fa_verified' => true]);

            $audit->log(
                actorType: 'admin',
                actorId: $user->id,
                action: 'aktifkan_totp_admin',
                objectType: 'staff_account',
                objectId: $user->id,
                detail: ['username' => $user->username]
            );

            session()->flash('success', 'Autentikasi dua faktor (TOTP) berhasil diaktifkan.');
            $this->redirectRoute('admin.operators');
        } catch (Throwable $e) {
            $this->errorMessage = 'Terjadi kendala saat mengaktifkan autentikasi dua faktor. Silakan coba kembali.';
            $this->reportSafeError($e, $this->errorMessage);
        }
    }

    public function render(TotpService $totp)
    {
        /** @var StaffAccount $user */
        $user = Auth::guard('staff')->user();
        $qrSvg = null;
        if ($user && $user->isAdmin() && ! $user->totp_enabled && $this->pendingTotpSecret !== '') {
            $uri = $totp->getProvisioningUri($user->username, $this->pendingTotpSecret);
            $qrSvg = $totp->renderQrSvg($uri, 210);
        }

        return view('livewire.auth.activate-account-page', [
            'user' => $user,
            'qrSvg' => $qrSvg,
        ]);
    }
}
