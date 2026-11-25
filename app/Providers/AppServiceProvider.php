<?php

namespace App\Providers;

use App\Http\Middleware\EnsureStaffAuthenticated;
use App\Http\Middleware\EnsureStaffRole;
use App\Http\Middleware\ThrottleSensitiveActions;
use App\Models\StaffAccount;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $internalProxies = array_filter(config('evoting.trusted_proxies'), static fn ($proxy) => filter_var(explode('/', $proxy)[0], FILTER_VALIDATE_IP));
        TrustProxies::at(array_values(array_unique(['127.0.0.1', '::1', ...$internalProxies])));
        TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        Livewire::addPersistentMiddleware([EnsureStaffAuthenticated::class, EnsureStaffRole::class]);
        if (! $this->app->routesAreCached()) {
            Livewire::setUpdateRoute(static fn ($handle, $path) => Route::post($path, $handle)
                ->middleware(['web', 'throttle:livewire-update', ThrottleSensitiveActions::class]));
        }

        Gate::define('use-staff-area', static fn (StaffAccount $staff, string $role): bool => $staff->role === $role && $staff->is_active && ! $staff->needsActivation()
            && $staff->hasCurrentSession()
            && (! $staff->isAdmin() || session('staff_2fa_verified') === true)
        );

        RateLimiter::for('voter-access', function (Request $request) {
            return [
                Limit::perMinute(900)->by('ip:'.$request->ip()),
                Limit::perMinute(30)->by('qr:'.hash('sha256', (string) $request->route('token'))),
            ];
        });

        RateLimiter::for('livewire-update', fn (Request $request) => [
            // Up to 200 browsers can poll every three seconds from the school NAT.
            Limit::perMinute(6000)->by('ip:'.$request->ip()),
            Limit::perMinute(120)->by('session:'.hash('sha256', $request->session()->getId())),
        ]);

        RateLimiter::for('public-access', function (Request $request) {
            return Limit::perMinute(900)->by($request->ip());
        });

        RateLimiter::for('live-recap', function (Request $request) {
            return Limit::perMinute(900)->by($request->ip());
        });
    }
}
