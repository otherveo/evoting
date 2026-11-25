<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Models\StaffAccount;
use App\Services\AuditService;
use App\Services\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

#[Layout('layouts.app')]
#[Title('Masuk Staf')]
class LoginPage extends Component
{
    use HandlesSafeErrors;

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

        try {
            $cleanUsername = mb_strtolower(trim($this->username));
            $ip = (string) request()->ip();
            $throttleKey = 'staff_login:'.hash('sha256', $cleanUsername);
            $ipKey = 'staff_login_ip:'.$ip;
            $maxAttempts = (int) config('evoting.login_max_attempts', 5);
            $decaySeconds = (int) config('evoting.login_decay_seconds', 60);

            if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts) || RateLimiter::tooManyAttempts($ipKey, 60)) {
                $seconds = max(RateLimiter::availableIn($throttleKey), RateLimiter::availableIn($ipKey));
                throw new TooManyRequestsHttpException(max(1, $seconds), 'Terlalu banyak percobaan. Silakan coba lagi.');
            }

            /** @var StaffAccount|null $account */
            $account = StaffAccount::query()
                ->whereRaw('lower(username) = ?', [$cleanUsername])
                ->first();

            // FR-001, AC-002: Pesan gagal tidak membedakan username salah dan kata sandi salah
            if (! $account || ! $account->is_active || ! Hash::check($this->password, $account->password_hash)) {
                RateLimiter::hit($throttleKey, $decaySeconds);
                RateLimiter::hit($ipKey, $decaySeconds);

                app(SecurityAudit::class)->record('login_failed', request());

                $this->password = '';
                $this->errorMessage = 'Username atau kata sandi tidak sesuai.';
                $this->dispatch('toast', message: $this->errorMessage, type: 'error');

                return;
            }

            RateLimiter::clear($throttleKey);
            Auth::guard('staff')->login($account);
            session()->regenerate();
            session()->forget(['pending_totp_enrollment', 'staff_2fa_verified']);
            session(['staff_session_version' => (int) $account->session_version]);

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

            // Amandemen PRD §1: Operator login cukup dengan username dan kata sandi
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
        } catch (TooManyRequestsHttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->errorMessage = 'Terjadi kendala saat memproses permintaan masuk. Silakan coba kembali.';
            $this->reportSafeError($e, $this->errorMessage);
        }
    }

    public function render()
    {
        return view('livewire.auth.login-page');
    }
}
