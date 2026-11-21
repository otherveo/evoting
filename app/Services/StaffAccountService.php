<?php

namespace App\Services;

use App\Models\StaffAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class StaffAccountService
{
    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * Membuat akun Admin atau Operator dengan kata sandi sementara yang hanya ditampilkan sekali (FR-005, KP-006).
     *
     * @return array{account: StaffAccount, temporary_password: string}
     */
    public function createAccount(
        string $username,
        string $fullName,
        string $role,
        string $actorType = 'sistem',
        ?string $actorId = null
    ): array {
        $normalizedUsername = trim($username);
        $normalizedFullName = preg_replace('/\s+/u', ' ', trim($fullName)) ?? '';

        if ($normalizedUsername === '' || ! preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $normalizedUsername)) {
            throw new InvalidArgumentException('Username harus 3–40 karakter (huruf, angka, titik, garis bawah, atau tanda hubung).');
        }

        if ($normalizedFullName === '') {
            throw new InvalidArgumentException('Nama lengkap wajib diisi.');
        }

        if (! in_array($role, ['admin', 'operator'], true)) {
            throw new InvalidArgumentException('Peran akun tidak valid.');
        }

        $exists = StaffAccount::query()
            ->whereRaw('lower(username) = ?', [mb_strtolower($normalizedUsername)])
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException("Username '{$normalizedUsername}' sudah digunakan.");
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        return DB::connection('staff')->transaction(function () use ($normalizedUsername, $normalizedFullName, $role, $temporaryPassword, $actorType, $actorId) {
            $account = StaffAccount::create([
                'username' => $normalizedUsername,
                'full_name' => $normalizedFullName,
                'password_hash' => Hash::make($temporaryPassword),
                'role' => $role,
                'is_active' => true,
                'totp_secret' => null,
                'totp_enabled' => false,
                'must_change_password' => true,
            ]);

            $this->audit->log(
                actorType: $actorType,
                actorId: $actorId,
                action: $role === 'admin' ? 'buat_akun_admin' : 'buat_akun_operator',
                objectType: 'staff_account',
                objectId: $account->id,
                detail: [
                    'username' => $account->username,
                    'full_name' => $account->full_name,
                    'role' => $account->role,
                ]
            );

            return [
                'account' => $account,
                'temporary_password' => $temporaryPassword,
            ];
        });
    }

    /**
     * Mengaktifkan atau menonaktifkan akun Operator dan mencabut sesi aktifnya (FR-006, AC-007).
     */
    public function setOperatorActiveStatus(StaffAccount $operator, bool $isActive, StaffAccount $admin): void
    {
        if (! $operator->isOperator()) {
            throw new InvalidArgumentException('Hanya akun Operator yang dapat dikelola melalui menu ini.');
        }

        DB::connection('staff')->transaction(function () use ($operator, $isActive, $admin) {
            $operator->update([
                'is_active' => $isActive,
            ]);

            if (! $isActive) {
                $operator->increment('session_version');
            }

            $this->audit->log(
                actorType: 'admin',
                actorId: $admin->id,
                action: $isActive ? 'aktifkan_akun_operator' : 'nonaktifkan_akun_operator',
                objectType: 'staff_account',
                objectId: $operator->id,
                detail: [
                    'username' => $operator->username,
                    'is_active' => $isActive,
                ]
            );
        });
    }

    /**
     * Mereset kata sandi Operator menjadi kata sandi sementara baru (FR-007, AC-008, Amandemen PRD §2.3).
     */
    public function resetOperatorPassword(StaffAccount $operator, StaffAccount $admin): string
    {
        if (! $operator->isOperator()) {
            throw new InvalidArgumentException('Hanya kata sandi akun Operator yang dapat direset oleh Admin.');
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        DB::connection('staff')->transaction(function () use ($operator, $admin, $temporaryPassword) {
            $operator->update([
                'password_hash' => Hash::make($temporaryPassword),
                'must_change_password' => true,
            ]);

            $operator->increment('session_version');

            $this->audit->log(
                actorType: 'admin',
                actorId: $admin->id,
                action: 'reset_sandi_operator',
                objectType: 'staff_account',
                objectId: $operator->id,
                detail: [
                    'username' => $operator->username,
                ]
            );
        });

        return $temporaryPassword;
    }

    public function generateTemporaryPassword(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $part1 = '';
        $part2 = '';
        $part3 = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < 4; $i++) {
            $part1 .= $chars[random_int(0, $max)];
            $part2 .= $chars[random_int(0, $max)];
            $part3 .= $chars[random_int(0, $max)];
        }

        return "{$part1}-{$part2}-{$part3}";
    }
}
