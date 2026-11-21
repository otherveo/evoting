<?php

namespace App\Models;

use App\Services\VotingService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Election extends Model
{
    use HasUuids;

    protected $connection = 'staff';

    protected $table = 'evoting.elections';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'max_voter_rights',
        'starts_at',
        'ends_at',
        'recap_open',
        'recap_mode',
        'reveal_order',
        'reveal_mode',
        'reveal_delay_ms',
        'reveal_locked',
        'revealed_ids',
        'reveal_locked_by',
    ];

    protected $hidden = [
        'access_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'max_voter_rights' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'access_key_created_at' => 'datetime',
            'recap_open' => 'boolean',
            'reveal_order' => 'array',
            'reveal_delay_ms' => 'integer',
            'reveal_locked' => 'boolean',
            'revealed_ids' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function hasActiveAccessKey(): bool
    {
        return $this->access_key_created_at !== null || ! empty($this->attributes['access_key_hash'] ?? null);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'election_id')->orderBy('number');
    }

    public function voterRights(): HasMany
    {
        return $this->hasMany(VoterRight::class, 'election_id');
    }

    public function currentPhase(): string
    {
        $schedule = VotingService::$scheduleCache[$this->id] ?? [];
        $startsAt = $schedule['starts_at'] ?? $this->starts_at;
        $endsAt = $schedule['ends_at'] ?? $this->ends_at;

        if (! $startsAt || ! $endsAt) {
            return 'persiapan';
        }
        $now = now()->addMilliseconds(VotingService::$dbOffsetMs);
        if ($now->lt($startsAt)) {
            return 'persiapan';
        }
        if ($now->lt($endsAt)) {
            return 'berlangsung';
        }

        return 'ditutup';
    }

    public static function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'persiapan' => 'Persiapan',
            'berlangsung' => 'Berlangsung',
            'ditutup' => 'Ditutup',
            default => ucfirst($phase),
        };
    }

    public function formattedSchedule(?CarbonInterface $dt): string
    {
        if (! $dt) {
            return 'Belum diatur';
        }
        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');
        $local = $dt->copy()->setTimezone($tz);
        $tzShort = match ($tz) {
            'Asia/Jakarta' => 'WIB',
            'Asia/Makassar', 'Asia/Pontianak' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => $local->format('T'),
        };

        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];
        $month = $months[(int) $local->format('n')] ?? $local->format('M');

        return sprintf(
            '%s %s %s, %s %s',
            $local->format('d'),
            $month,
            $local->format('Y'),
            $local->format('H:i'),
            $tzShort
        );
    }
}
