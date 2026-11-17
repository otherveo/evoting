<?php

namespace App\Http\Middleware;

use App\Models\StaffAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var StaffAccount|null $user */
        $user = Auth::guard('staff')->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Auth::guard('staff')->user() sudah memuat baris terbaru dari evoting.staff_accounts pada setiap request (AC-007, MOD-001)
        if (! $user->is_active) {
            Auth::guard('staff')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Akun Anda tidak aktif atau sesi telah diakhiri.');
        }

        // Jika Admin sudah mengaktifkan TOTP tetapi sesi ini belum lolos verifikasi 2FA (UI-002)
        if ($user->isAdmin() && $user->totp_enabled && ! $request->session()->get('staff_2fa_verified', false)) {
            if (! $request->routeIs('auth.2fa', 'logout')) {
                return redirect()->route('auth.2fa');
            }

            return $next($request);
        }

        // Gerbang aktivasi akun pertama / pasca-reset (FR-002, AC-003, Amandemen PRD §2.2 & §2.13)
        if ($user->needsActivation()) {
            if (! $request->routeIs('auth.activate', 'logout')) {
                return redirect()->route('auth.activate');
            }
        }

        return $next($request);
    }
}
