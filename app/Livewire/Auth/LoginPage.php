<?php

namespace App\Livewire\Auth;

use App\Models\StaffAccount;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Masuk Staf')]
class LoginPage extends Component
{
    public string $username = '';

    public string $password = '';

    public ?string $errorMessage = null;

    public function mount(): void
    {
        /** @var StaffAccount|null $user */
        $user = Auth::guard('staff')->user();
        if ($user && $user->is_active) {
            if ($user->isAdmin() && $user->totp_enabled && ! session('staff_2fa_verified', false)) {
                $this->redirectRoute('auth.2fa');

                return;
            }
            if ($user->needsActivation()) {
                $this->redirectRoute('auth.activate');

                return;
            }
            $this->redirectRoute($user->isAdmin() ? 'admin.operators' : 'operator.elections');
        }
    }

    public function login(AuditService $audit): void
    {
        $this->errorMessage = null;

        $this->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $cleanUsername = mb_strtolower(trim($this->username));
        $ip = (string) request()->ip();
        $throttleKey = "staff_login:{$cleanUsername}|{$ip}";
        $maxAttempts = (int) config('evoting.login_max_attempts', 5);
        $decaySeconds = (int) config('evoting.login_decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->errorMessage = "Terlalu banyak percobaan masuk yang gagal. Silakan tunggu {$seconds} detik sebelum mencoba kembali.";

            return;
        }

        /** @var StaffAccount|null $account */
        $account = StaffAccount::query()
            ->whereRaw('lower(username) = ?', [$cleanUsername])
            ->first();

        if (! $account || ! $account->is_active || ! Hash::check($this->password, $account->password_hash)) {
            RateLimiter::hit($throttleKey, $decaySeconds);

            $audit->log(
                actorType: $account ? $account->role : 'sistem',
                actorId: $account?->id,
                action: 'login_gagal',
                objectType: 'staff_account',
                objectId: $account?->id,
                detail: [
                    'username_attempt' => $cleanUsername,
                    'ip' => $ip,
                ]
            );

            $this->password = '';
            $this->errorMessage = 'Username atau kata sandi tidak sesuai.';

            return;
        }

        RateLimiter::clear($throttleKey);
        Auth::guard('staff')->login($account);
        session()->regenerate();

        // Amandemen PRD: Admin wajib melewati TOTP (UI-002 bila sudah aktif, atau UI-003 bila aktivasi awal)
        if ($account->isAdmin()) {
            session(['staff_2fa_verified' => false]);

            if ($account->totp_enabled) {
                $audit->log(
                    actorType: 'admin',
                    actorId: $account->id,
                    action: 'login_tahap1_berhasil',
                    objectType: 'staff_account',
                    objectId: $account->id,
                    detail: ['username' => $account->username]
                );
                $this->redirectRoute('auth.2fa');

                return;
            }

            $audit->log(
                actorType: 'admin',
                actorId: $account->id,
                action: 'login_aktivasi_awal',
                objectType: 'staff_account',
                objectId: $account->id,
                detail: ['username' => $account->username]
            );
            $this->redirectRoute('auth.activate');

            return;
        }

        // Operator login cukup username + kata sandi (Amandemen PRD §1)
        $audit->log(
            actorType: 'operator',
            actorId: $account->id,
            action: 'login_berhasil',
            objectType: 'staff_account',
            objectId: $account->id,
            detail: ['username' => $account->username]
        );

        if ($account->needsActivation()) {
            $this->redirectRoute('auth.activate');

            return;
        }

        $this->redirectRoute('operator.elections');
    }

    public function render()
    {
        return view('livewire.auth.login-page');
    }
}
