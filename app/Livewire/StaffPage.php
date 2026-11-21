<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

abstract class StaffPage extends Component
{
    public function boot(): void
    {
        $staff = Auth::guard('staff')->user();
        abort_unless($staff && Gate::forUser($staff)->allows('use-staff-area', static::ROLE), 403);
    }
}
