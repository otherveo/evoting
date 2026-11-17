<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Election extends Model
{
    use HasUuids;

    protected $table = 'evoting.elections';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'max_voter_rights',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'max_voter_rights' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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
        $row = DB::selectOne('select fase from evoting.v_elections where id = ?', [$this->id]);
        if ($row && isset($row->fase)) {
            return (string) $row->fase;
        }

        if (! $this->starts_at || ! $this->ends_at) {
            return 'persiapan';
        }
        $now = now();
        if ($now->lt($this->starts_at)) {
            return 'persiapan';
        }
        if ($now->gte($this->starts_at) && $now->lt($this->ends_at)) {
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
