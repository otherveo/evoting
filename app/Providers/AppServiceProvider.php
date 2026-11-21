<?php

namespace App\Providers;

use App\Http\Middleware\EnsureStaffAuthenticated;
use App\Http\Middleware\EnsureStaffRole;
use App\Models\StaffAccount;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        TrustProxies::at(config('evoting.trusted_proxies'));
        Livewire::addPersistentMiddleware([EnsureStaffAuthenticated::class, EnsureStaffRole::class]);

        Gate::define('use-staff-area', static fn (StaffAccount $staff, string $role): bool => $staff->role === $role && $staff->is_active && ! $staff->needsActivation()
            && $staff->hasCurrentSession()
            && (! $staff->isAdmin() || session('staff_2fa_verified') === true)
        );

        RateLimiter::for('voter-access', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('public-access', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('live-recap', function (Request $request) {
            return Limit::perMinute(40)->by($request->ip());
        });
    }
}
