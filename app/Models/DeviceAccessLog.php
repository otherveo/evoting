<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceAccessLog extends Model
{
    use HasUuids;

    protected $connection = 'staff';

    protected $table = 'evoting.device_access_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    public const CREATED_AT = 'accessed_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'voter_right_id',
        'access_type',
        'browser_id',
        'user_agent',
        'ip_address',
        'accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
        ];
    }

    public function voterRight(): BelongsTo
    {
        return $this->belongsTo(VoterRight::class, 'voter_right_id');
    }

    public static function accessTypeLabel(string $type): string
    {
        return match ($type) {
            'qr_dibuka' => 'QR dibuka',
            'mulai_memilih' => 'Mulai memilih',
            'konfirmasi_pilihan' => 'Konfirmasi pilihan',
            'akses_ditolak' => 'Akses ditolak',
            default => $type,
        };
    }
}
