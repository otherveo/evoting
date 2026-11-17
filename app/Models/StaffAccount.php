<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StaffAccount extends Authenticatable
{
    use HasUuids;

    protected $table = 'evoting.staff_accounts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'username',
        'full_name',
        'password_hash',
        'role',
        'is_active',
        'totp_secret',
        'totp_enabled',
        'must_change_password',
    ];

    protected $hidden = [
        'password_hash',
        'totp_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'totp_enabled' => 'boolean',
            'must_change_password' => 'boolean',
            'totp_secret' => 'encrypted',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    public function needsActivation(): bool
    {
        if ($this->must_change_password) {
            return true;
        }

        if ($this->isAdmin() && ! $this->totp_enabled) {
            return true;
        }

        return false;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim($this->full_name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : mb_strtoupper(mb_substr($this->username, 0, 2));
    }
}
