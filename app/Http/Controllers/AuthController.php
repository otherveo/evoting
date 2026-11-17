<?php

namespace App\Http\Controllers;

use App\Models\StaffAccount;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function logout(Request $request, AuditService $audit): RedirectResponse
    {
        /** @var StaffAccount|null $user */
        $user = Auth::guard('staff')->user();

        if ($user) {
            $audit->log(
                actorType: $user->role,
                actorId: $user->id,
                action: 'logout',
                objectType: 'staff_account',
                objectId: $user->id,
                detail: ['username' => $user->username]
            );
        }

        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah keluar dari sesi.');
    }
}
