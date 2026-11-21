<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Models\StaffAccount;
use App\Services\AuditService;
use App\Services\TotpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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

    public ?string $errorMessage = null;

    public function mount(TotpService $totp): void
    {
        /** @var StaffAccount|null $user */
        $user = Auth::guard('staff')->user();
        if (! $user || ! $user->is_active || ! $user->hasCurrentSession()) {
            $this->redirectRoute('login');

            return;
        }

        if (! $user->needsActivation()) {
            $this->redirectRoute($user->isAdmin() ? 'admin.operators' : 'operator.elections');

            return;
        }

        if ($user->isAdmin() && ! $user->totp_enabled) {
            if ($this->pendingSecret($user) === null) {
                session(['pending_totp_enrollment' => [
                    'account' => $user->id,
                    'secret' => $totp->generateSecret(),
                    'expires_at' => now()->addMinutes(10)->timestamp,
                ]]);
            }
        }
    }

    private function pendingSecret(StaffAccount $user): ?string
    {
        $pending = session('pending_totp_enrollment');

        return ($pending['account'] ?? null) === $user->id && ($pending['expires_at'] ?? 0) > now()->timestamp
            ? $pending['secret']
            : null;
    }

    public function updatePassword(AuditService $audit): void
    {
        $this->errorMessage = null;

        /** @var StaffAccount|null $user */
        $user = Auth::guard('staff')->user();
        if (! $user || ! $user->is_active || ! $user->hasCurrentSession()) {
            $this->redirectRoute('login');

            return;
        }

        abort_unless($user->must_change_password, 403);

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

            $changed = StaffAccount::whereKey($user->id)
                ->where('session_version', $user->session_version)
                ->where('must_change_password', true)
                ->where('is_active', true)
                ->update([
                    'password_hash' => Hash::make($this->newPassword),
                    'must_change_password' => false,
                    'session_version' => (int) $user->session_version + 1,
                ]);

            if (! $changed) {
                $this->redirectRoute('login');

                return;
            }

            session()->regenerate();
            session(['staff_session_version' => (int) $user->session_version + 1]);

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
        if (! $user || ! $user->isAdmin() || ! $user->is_active || ! $user->hasCurrentSession() || $user->totp_enabled) {
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
            $key = 'totp-enroll:'.$user->id;
            $secret = $this->pendingSecret($user);
            if ($secret === null || RateLimiter::tooManyAttempts($key, 5)) {
                $this->addError('totpCode', 'Pendaftaran kedaluwarsa atau percobaan dibatasi. Muat ulang halaman dan coba kembali.');

                return;
            }

            RateLimiter::hit($key, 60);
            $enabled = DB::connection('staff')->transaction(function () use ($user, $secret, $totp, $audit) {
                $staff = StaffAccount::query()->lockForUpdate()->findOrFail($user->id);
                if (! $staff->isAdmin() || ! $staff->is_active || ! $staff->hasCurrentSession()
                    || $staff->totp_enabled || $staff->must_change_password
                    || $this->pendingSecret($staff) !== $secret || ! $totp->verifyCode($secret, $this->totpCode, $staff->id)) {
                    return false;
                }

                $staff->update(['totp_secret' => $secret, 'totp_enabled' => true]);
                $audit->log('admin', $staff->id, 'aktifkan_totp_admin', 'staff_account', $staff->id);

                return true;
            });

            if (! $enabled) {
                $this->addError('totpCode', 'Kode atau pendaftaran TOTP tidak berlaku.');

                return;
            }

            RateLimiter::clear($key);
            session()->forget('pending_totp_enrollment');
            session()->regenerate();
            session(['staff_2fa_verified' => true]);
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
        $pendingTotpSecret = $user ? $this->pendingSecret($user) : null;
        $qrSvg = null;
        if ($user && $user->isAdmin() && ! $user->totp_enabled && $pendingTotpSecret !== null) {
            $uri = $totp->getProvisioningUri($user->username, $pendingTotpSecret);
            $qrSvg = $totp->renderQrSvg($uri, 210);
        }

        return view('livewire.auth.activate-account-page', [
            'user' => $user,
            'qrSvg' => $qrSvg,
            'pendingTotpSecret' => $pendingTotpSecret ?? '',
        ]);
    }
}
