<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoterRight extends Model
{
    use HasUuids;

    protected $table = 'evoting.voter_rights';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Kolom yang diizinkan SELECT bagi role evoting_app (tanpa token_hash).
     *
     * @var list<string>
     */
    public const SAFE_COLUMNS = [
        'id',
        'election_id',
        'name',
        'name_normalized',
        'status',
        'session_started_at',
        'session_browser_id',
        'device_approval_status',
        'device_approval_requested_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'session_started_at' => 'datetime',
            'device_approval_requested_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class, 'election_id');
    }

    public function deviceAccessLogs(): HasMany
    {
        return $this->hasMany(DeviceAccessLog::class, 'voter_right_id')->orderByDesc('accessed_at');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'belum_memilih' => 'Belum memilih',
            'sedang_memilih' => 'Sedang memilih',
            'sudah_memilih' => 'Sudah memilih',
            default => $status,
        };
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'belum_memilih' => 'is-belum',
            'sedang_memilih' => 'is-sedang',
            'sudah_memilih' => 'is-sudah',
            default => 'is-belum',
        };
    }

    public static function normalizeName(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

        return mb_strtolower($collapsed, 'UTF-8');
    }
}
