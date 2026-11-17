<?php

namespace App\Livewire\Admin;

use App\Models\StaffAccount;
use App\Services\StaffAccountService;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Kelola Akun Operator')]
class OperatorIndexPage extends Component
{
    public string $search = '';

    public string $filterStatus = 'all';

    // Modal Buat Akun (UI-005)
    public bool $showCreateModal = false;

    public string $newUsername = '';

    public string $newFullName = '';

    // Modal Kredensial Sekali Tampil (UI-005 / Reset Sandi)
    public bool $showCredentialModal = false;

    public string $credentialUsername = '';

    public string $credentialFullName = '';

    public string $credentialTempPassword = '';

    public string $credentialContext = 'create';

    // Modal Konfirmasi Aksi (Aktif/Nonaktif & Reset Sandi)
    public bool $showConfirmModal = false;

    public ?string $targetOperatorId = null;

    public string $targetOperatorName = '';

    public string $confirmActionType = '';

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->newUsername = '';
        $this->newFullName = '';
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    public function createOperator(StaffAccountService $service): void
    {
        $this->validate([
            'newUsername' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'newFullName' => ['required', 'string', 'min:2', 'max:120'],
        ], [
            'newUsername.required' => 'Username wajib diisi.',
            'newUsername.min' => 'Username minimal 3 karakter.',
            'newUsername.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.',
            'newFullName.required' => 'Nama lengkap wajib diisi.',
        ]);

        /** @var StaffAccount $admin */
        $admin = Auth::guard('staff')->user();

        try {
            $result = $service->createAccount(
                username: $this->newUsername,
                fullName: $this->newFullName,
                role: 'operator',
                actorType: 'admin',
                actorId: $admin->id
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('newUsername', $e->getMessage());

            return;
        }

        $this->showCreateModal = false;
        $this->credentialUsername = $result['account']->username;
        $this->credentialFullName = $result['account']->full_name;
        $this->credentialTempPassword = $result['temporary_password'];
        $this->credentialContext = 'create';
        $this->showCredentialModal = true;

        $this->dispatch('toast', message: 'Akun Operator berhasil dibuat.', type: 'success');
    }

    public function closeCredentialModal(): void
    {
        $this->showCredentialModal = false;
        $this->credentialUsername = '';
        $this->credentialFullName = '';
        $this->credentialTempPassword = '';
    }

    public function promptToggleActive(string $operatorId): void
    {
        $op = StaffAccount::where('role', 'operator')->findOrFail($operatorId);
        $this->targetOperatorId = $op->id;
        $this->targetOperatorName = "{$op->full_name} (@{$op->username})";
        $this->confirmActionType = $op->is_active ? 'deactivate' : 'activate';
        $this->showConfirmModal = true;
    }

    public function promptResetPassword(string $operatorId): void
    {
        $op = StaffAccount::where('role', 'operator')->findOrFail($operatorId);
        $this->targetOperatorId = $op->id;
        $this->targetOperatorName = "{$op->full_name} (@{$op->username})";
        $this->confirmActionType = 'reset_password';
        $this->showConfirmModal = true;
    }

    public function closeConfirmModal(): void
    {
        $this->showConfirmModal = false;
        $this->targetOperatorId = null;
        $this->targetOperatorName = '';
        $this->confirmActionType = '';
    }

    public function executeConfirmedAction(StaffAccountService $service): void
    {
        if (! $this->targetOperatorId) {
            return;
        }

        /** @var StaffAccount $admin */
        $admin = Auth::guard('staff')->user();
        $operator = StaffAccount::where('role', 'operator')->findOrFail($this->targetOperatorId);
        $action = $this->confirmActionType;

        $this->closeConfirmModal();

        if ($action === 'deactivate') {
            $service->setOperatorActiveStatus($operator, false, $admin);
            $this->dispatch('toast', message: "Akun @{$operator->username} dinonaktifkan dan sesi aktifnya diakhiri.", type: 'warning');

            return;
        }

        if ($action === 'activate') {
            $service->setOperatorActiveStatus($operator, true, $admin);
            $this->dispatch('toast', message: "Akun @{$operator->username} diaktifkan kembali.", type: 'success');

            return;
        }

        if ($action === 'reset_password') {
            $tempPassword = $service->resetOperatorPassword($operator, $admin);
            $this->credentialUsername = $operator->username;
            $this->credentialFullName = $operator->full_name;
            $this->credentialTempPassword = $tempPassword;
            $this->credentialContext = 'reset';
            $this->showCredentialModal = true;
            $this->dispatch('toast', message: "Kata sandi sementara baru untuk @{$operator->username} telah dibuat.", type: 'success');
        }
    }

    public function render()
    {
        $baseQuery = StaffAccount::query()->where('role', 'operator');

        $totalOperators = (clone $baseQuery)->count();
        $activeOperators = (clone $baseQuery)->where('is_active', true)->count();
        $pendingPasswordChange = (clone $baseQuery)->where('must_change_password', true)->count();
        $inactiveOperators = $totalOperators - $activeOperators;

        $query = clone $baseQuery;
        if ($this->filterStatus === 'active') {
            $query->where('is_active', true);
        } elseif ($this->filterStatus === 'inactive') {
            $query->where('is_active', false);
        }

        if (trim($this->search) !== '') {
            $q = '%'.mb_strtolower(trim($this->search)).'%';
            $query->where(function ($sub) use ($q) {
                $sub->whereRaw('lower(username) like ?', [$q])
                    ->orWhereRaw('lower(full_name) like ?', [$q]);
            });
        }

        $operators = $query->orderByDesc('created_at')->get();

        return view('livewire.admin.operator-index-page', [
            'operators' => $operators,
            'totalOperators' => $totalOperators,
            'activeOperators' => $activeOperators,
            'pendingPasswordChange' => $pendingPasswordChange,
            'inactiveOperators' => $inactiveOperators,
        ]);
    }
}
