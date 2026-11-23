<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidate extends Model
{
    use HasUuids;

    protected $table = 'evoting.candidates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'election_id',
        'number',
        'name',
        'photo_path',
        'vision',
        'missions',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'public_photo_visible' => 'boolean',
            'missions' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class, 'election_id');
    }

    public function formattedNumber(): string
    {
        return str_pad((string) $this->number, 2, '0', STR_PAD_LEFT);
    }

    public function photoUrl(): string
    {
        if ($this->photo_path) {
            if (str_starts_with($this->photo_path, 'http://') || str_starts_with($this->photo_path, 'https://') || str_starts_with($this->photo_path, 'data:')) {
                return $this->photo_path;
            }
            $baseUrl = rtrim((string) config('evoting.supabase.url'), '/');
            $bucket = (string) config('evoting.supabase.bucket', 'paslon-foto');
            $cleanPath = ltrim($this->photo_path, '/');
            if (str_starts_with($cleanPath, $bucket.'/')) {
                $cleanPath = substr($cleanPath, strlen($bucket) + 1);
            }

            return "{$baseUrl}/storage/v1/object/public/{$bucket}/{$cleanPath}";
        }

        return $this->placeholderSvgDataUri();
    }

    public function placeholderSvgDataUri(): string
    {
        $palettes = [
            ['#102753', '#2457c5', '#eaf0ff'],
            ['#0d3b4c', '#0f766e', '#e3faf6'],
            ['#2d1e5f', '#5a3ec8', '#f2ebff'],
            ['#4d2c09', '#a75b08', '#fff3df'],
        ];
        $palette = $palettes[(max(1, (int) $this->number) - 1) % count($palettes)];
        $num = htmlspecialchars($this->formattedNumber(), ENT_QUOTES, 'UTF-8');
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 420" width="640" height="420">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$palette[0]}"/>
      <stop offset="100%" stop-color="{$palette[1]}"/>
    </linearGradient>
  </defs>
  <rect width="640" height="420" fill="url(#g)"/>
  <circle cx="540" cy="70" r="160" fill="#ffffff" fill-opacity="0.07"/>
  <circle cx="110" cy="360" r="140" fill="#ffffff" fill-opacity="0.05"/>
  <text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" fill="{$palette[2]}" font-family="Segoe UI, Inter, Arial, sans-serif" font-size="92" font-weight="800" letter-spacing="-3">PASLON {$num}</text>
</svg>
SVG;

        return 'data:image/svg+xml;utf8,'.rawurlencode($svg);
    }

    /**
     * @return list<string>
     */
    public function missionItems(): array
    {
        if (! is_array($this->missions)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item) => is_string($item) ? trim($item) : '',
            $this->missions
        ), static fn (string $v) => $v !== ''));
    }
}
