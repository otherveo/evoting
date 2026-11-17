<?php

namespace App\Livewire\Auth;

use App\Models\StaffAccount;
use App\Services\AuditService;
use App\Services\TotpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Verifikasi 2FA Admin')]
class VerifyTwoFactorPage extends Component
{
    public string $code = '';

    public ?string $errorMessage = null;

    public function mount(): void
    {
        /** @var StaffAccount|null $user */
        $user = Auth::guard('staff')->user();

        if (! $user) {
            $this->redirectRoute('login');

            return;
        }

        if (! $user->isAdmin()) {
            $this->redirectRoute('operator.elections');

            return;
        }

        if (! $user->totp_enabled) {
            $this->redirectRoute('auth.activate');

            return;
        }

        if (session('staff_2fa_verified', false)) {
            $this->redirectRoute($user->needsActivation() ? 'auth.activate' : 'admin.operators');
        }
    }

    public function verify(TotpService $totp, AuditService $audit): void
    {
        $this->errorMessage = null;

        /** @var StaffAccount|null $user */
        $user = Auth::guard('staff')->user();
        if (! $user || ! $user->isAdmin()) {
            $this->redirectRoute('login');

            return;
        }

        $this->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'code.required' => 'Kode TOTP 6 digit wajib diisi.',
            'code.regex' => 'Kode TOTP harus terdiri dari 6 angka.',
        ]);

        $throttleKey = 'staff_2fa:'.$user->id.'|'.request()->ip();
        $maxAttempts = (int) config('evoting.login_max_attempts', 5);
        $decaySeconds = (int) config('evoting.login_decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->errorMessage = "Terlalu banyak percobaan kode TOTP gagal. Silakan tunggu {$seconds} detik.";

            return;
        }

        $secret = (string) $user->totp_secret;
        if ($secret === '' || ! $totp->verifyCode($secret, $this->code, $user->id)) {
            RateLimiter::hit($throttleKey, $decaySeconds);

            $audit->log(
                actorType: 'admin',
                actorId: $user->id,
                action: 'verifikasi_totp_gagal',
                objectType: 'staff_account',
                objectId: $user->id,
                detail: ['username' => $user->username]
            );

            $this->code = '';
            $this->errorMessage = 'Kode autentikator tidak sesuai atau sudah kedaluwarsa. Periksa kembali aplikasi autentikator Anda.';

            return;
        }

        RateLimiter::clear($throttleKey);
        session(['staff_2fa_verified' => true]);
        session()->regenerate();

        $audit->log(
            actorType: 'admin',
            actorId: $user->id,
            action: 'login_berhasil',
            objectType: 'staff_account',
            objectId: $user->id,
            detail: ['username' => $user->username, 'via_totp' => true]
        );

        if ($user->needsActivation()) {
            $this->redirectRoute('auth.activate');

            return;
        }

        $this->redirectRoute('admin.operators');
    }

    public function render()
    {
        return view('livewire.auth.verify-two-factor-page');
    }
}
