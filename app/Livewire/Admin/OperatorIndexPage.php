<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Livewire\StaffPage;
use App\Models\StaffAccount;
use App\Services\AuditService;
use App\Services\StaffAccountService;
use App\Services\TokenService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Throwable;

#[Layout('layouts.app')]
#[Title('Kelola Akun Operator')]
class OperatorIndexPage extends StaffPage
{
    protected const ROLE = 'admin';

    use HandlesSafeErrors;

    public string $search = '';

    public string $filterStatus = 'all';

    // UI-005: Modal Buat Akun Operator
    public bool $showCreateModal = false;

    public string $newUsername = '';

    public string $newFullName = '';

    // UI-005 / FR-007: Modal Kredensial Sekali Tampil
    public bool $showCredentialModal = false;

    public string $credentialUsername = '';

    public string $credentialFullName = '';

    public string $credentialTempPassword = '';

    public string $credentialContext = 'create';

    // FR-006 / FR-007: Modal Konfirmasi Aksi (Aktif/Nonaktif & Reset Sandi)
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
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal membuat akun Operator. Silakan coba kembali.', 'newUsername');

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

        try {
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
        } catch (Throwable $e) {
            $this->closeConfirmModal();
            $this->reportSafeError($e, 'Gagal memproses perubahan akun Operator. Silakan coba kembali.');
        }
    }

    public function setQrMode(string $mode, TokenService $tokens, AuditService $audit): void
    {
        try {
            /** @var StaffAccount $admin */
            $admin = Auth::guard('staff')->user();
            $normalized = $tokens->setQrMode($mode, $admin, $audit);

            $label = $normalized === TokenService::QR_MODE_CUSTOM
                ? 'Mode Kustom (dengan logo di tengah)'
                : 'Mode Standar (tanpa logo tengah)';

            $this->dispatch('toast', message: "Konfigurasi QR pemilih diubah ke {$label}.", type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menyimpan konfigurasi mode QR. Silakan coba kembali.');
        }
    }

    public function render(TokenService $tokens)
    {
        $allOperators = StaffAccount::query()
            ->where('role', 'operator')
            ->orderByDesc('created_at')
            ->get();

        $totalOperators = $allOperators->count();
        $activeOperators = $allOperators->where('is_active', true)->count();
        $pendingPasswordChange = $allOperators->where('must_change_password', true)->count();
        $inactiveOperators = $totalOperators - $activeOperators;

        $operators = $allOperators->filter(function (StaffAccount $op) {
            if ($this->filterStatus === 'active' && ! $op->is_active) {
                return false;
            }
            if ($this->filterStatus === 'inactive' && $op->is_active) {
                return false;
            }
            if (trim($this->search) !== '') {
                $needle = mb_strtolower(trim($this->search));
                if (
                    ! str_contains(mb_strtolower($op->username), $needle) &&
                    ! str_contains(mb_strtolower($op->full_name), $needle)
                ) {
                    return false;
                }
            }

            return true;
        })->values();

        $sampleUrl = url('/v/contoh-token-verifikasi-scanner');
        $qrMode = $tokens->getQrMode();
        $customQrPreviewSvg = Cache::rememberForever(
            'evoting_preview_qr_custom_v1',
            fn () => $tokens->renderQrSvg($sampleUrl, 160, TokenService::QR_MODE_CUSTOM)
        );
        $standardQrPreviewSvg = Cache::rememberForever(
            'evoting_preview_qr_standard_v1',
            fn () => $tokens->renderQrSvg($sampleUrl, 160, TokenService::QR_MODE_STANDARD)
        );

        return view('livewire.admin.operator-index-page', [
            'operators' => $allOperators,
            'totalOperators' => $totalOperators,
            'activeOperators' => $activeOperators,
            'pendingPasswordChange' => $pendingPasswordChange,
            'inactiveOperators' => $inactiveOperators,
            'qrMode' => $qrMode,
            'customQrPreviewSvg' => $customQrPreviewSvg,
            'standardQrPreviewSvg' => $standardQrPreviewSvg,
        ]);
    }
}
