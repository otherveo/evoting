<?php

namespace App\Livewire\Admin;

use App\Livewire\StaffPage;
use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Log Audit Sistem')]
class AuditLogPage extends StaffPage
{
    protected const ROLE = 'admin';

    use WithPagination;

    public string $actorType = 'all';

    public string $actionFilter = 'all';

    public string $search = '';

    public function applyFilters(string $actor, string $action, string $search): void
    {
        $this->actorType = $actor;
        $this->actionFilter = $action;
        $this->search = trim($search);
        $this->resetPage();
    }

    public function render()
    {
        $query = AuditLog::query();

        if ($this->actorType !== 'all') {
            $query->where('actor_type', $this->actorType);
        }

        if ($this->actionFilter !== 'all') {
            $query->where('action', $this->actionFilter);
        }

        if (trim($this->search) !== '') {
            $q = '%'.mb_strtolower(trim($this->search)).'%';
            $query->where(function ($sub) use ($q) {
                $sub->whereRaw('lower(action) like ?', [$q])
                    ->orWhereRaw('lower(coalesce(object_type, \'\')) like ?', [$q])
                    ->orWhereRaw('lower(coalesce(detail::text, \'\')) like ?', [$q]);
            });
        }

        $distinctActions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $logs = $query->orderByDesc('created_at')->paginate(25);
        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');

        return view('livewire.admin.audit-log-page', [
            'logs' => $logs,
            'distinctActions' => $distinctActions,
            'tz' => $tz,
        ]);
    }
}
