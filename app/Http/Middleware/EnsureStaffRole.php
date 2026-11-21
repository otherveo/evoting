<?php

namespace App\Http\Middleware;

use App\Models\StaffAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        /** @var StaffAccount|null $user */
        $user = Auth::guard('staff')->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! Gate::forUser($user)->allows('use-staff-area', $role)) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}
