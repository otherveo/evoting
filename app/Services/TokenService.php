<?php

namespace App\Services;

use App\Models\StaffAccount;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class TokenService
{
    public const QR_MODE_CUSTOM = 'custom';

    public const QR_MODE_STANDARD = 'standard';

    private const QR_MODE_CACHE_KEY = 'evoting_config:qr_mode';

    /**
     * NFR-002, BR-012: Menghasilkan pasangan token acak 256-bit (base64url) dan hash SHA-256 hex 64 karakter.
     *
     * @return array{raw: string, hash: string}
     */
    public function generateVoterToken(): array
    {
        $bytes = random_bytes(32);
        $raw = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        $hash = $this->hashToken($raw);

        return [
            'raw' => $raw,
            'hash' => $hash,
        ];
    }

    public function hashToken(string $rawToken): string
    {
        return hash('sha256', trim($rawToken));
    }

    public function votingUrl(string $rawToken): string
    {
        return route('voter.ballot', ['token' => $rawToken]);
    }

    /**
     * Menghasilkan access_key rekap real-time 64 karakter (huruf + angka + simbol, tanpa spasi) dengan CSPRNG
     * beserta hash SHA-256 hex 64 karakter untuk penyimpanan satu arah di database.
     *
     * @return array{raw: string, hash: string}
     */
    public function generateAccessKey(): array
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '0123456789';
        $symbols = '!@#$%^&*()-_=+[]{}.,:~';
        $all = $upper.$lower.$digits.$symbols;
        $max = strlen($all) - 1;

        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        for ($i = 4; $i < 64; $i++) {
            $chars[] = $all[random_int(0, $max)];
        }

        // Fisher-Yates CSPRNG shuffle
        for ($i = 63; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        $raw = implode('', $chars);

        return [
            'raw' => $raw,
            'hash' => $this->hashToken($raw),
        ];
    }

    public function liveRecapUrl(string $electionId): string
    {
        return route('live-recap.view', ['election' => $electionId]);
    }

    /**
     * Membaca mode konfigurasi QR saat ini ('custom' dengan logo tengah, atau 'standard' polos).
     */
    public function getQrMode(): string
    {
        $cached = Cache::get(self::QR_MODE_CACHE_KEY);
        if (is_string($cached) && in_array($cached, [self::QR_MODE_CUSTOM, self::QR_MODE_STANDARD], true)) {
            return $cached;
        }

        try {
            $row = DB::table('evoting.cache')
                ->where('key', self::QR_MODE_CACHE_KEY)
                ->first();

            if ($row && in_array($row->value, [self::QR_MODE_CUSTOM, self::QR_MODE_STANDARD], true)) {
                $mode = (string) $row->value;
                Cache::forever(self::QR_MODE_CACHE_KEY, $mode);

                return $mode;
            }
        } catch (Throwable) {
            // Fallback ke mode bawaan bila koneksi belum tersedia
        }

        Cache::forever(self::QR_MODE_CACHE_KEY, self::QR_MODE_CUSTOM);

        return self::QR_MODE_CUSTOM;
    }

    /**
     * Mengubah mode konfigurasi QR oleh Admin ('custom' atau 'standard') dan mencatatnya ke Log Audit.
     */
    public function setQrMode(string $mode, StaffAccount $admin, AuditService $audit): string
    {
        if (! in_array($mode, [self::QR_MODE_CUSTOM, self::QR_MODE_STANDARD], true)) {
            throw new InvalidArgumentException('Mode QR tidak valid.');
        }

        Cache::forever(self::QR_MODE_CACHE_KEY, $mode);

        DB::table('evoting.cache')->updateOrInsert(
            ['key' => self::QR_MODE_CACHE_KEY],
            [
                'value' => $mode,
                'expiration' => 2147483647,
            ]
        );

        $audit->log(
            actorType: 'admin',
            actorId: $admin->id,
            action: 'ubah_mode_qr',
            objectType: 'system_config',
            objectId: null,
            detail: [
                'qr_mode' => $mode,
                'label' => $mode === self::QR_MODE_CUSTOM ? 'QR Kustom (Dengan Logo Tengah)' : 'QR Standar (Tanpa Logo)',
            ]
        );

        return $mode;
    }

    /**
     * Menghasilkan SVG QR Code. Pada mode 'custom', memakai ErrorCorrectionLevel::H() (30%)
     * dan menyematkan logo SMPN7-e-voting_logo.png di bagian tengah.
     */
    public function renderQrSvg(string $url, int $size = 240, ?string $forceMode = null): string
    {
        $mode = $forceMode ?? $this->getQrMode();
        $ecLevel = $mode === self::QR_MODE_CUSTOM
            ? ErrorCorrectionLevel::H()
            : ErrorCorrectionLevel::M();

        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($url, Encoder::DEFAULT_BYTE_MODE_ENCODING, $ecLevel);

        if ($mode !== self::QR_MODE_CUSTOM) {
            return $svg;
        }

        $logoUrl = htmlspecialchars(asset('SMPN7-e-voting_logo.png'), ENT_QUOTES, 'UTF-8');
        $logoSize = (int) round($size * 0.225);
        $borderSize = $logoSize + 4;
        $padSize = $logoSize + 8;

        $logoX = (int) round(($size - $logoSize) / 2);
        $borderX = (int) round(($size - $borderSize) / 2);
        $padX = (int) round(($size - $padSize) / 2);

        $rxLogo = (int) round($logoSize * 0.185);
        $rxBorder = $rxLogo + 2;
        $rxPad = $rxLogo + 4;
        $clipId = 'qr-logo-clip-'.$size.'-'.substr(md5($url.$size), 0, 6);

        $overlay = <<<SVG
<defs><clipPath id="{$clipId}"><rect x="{$logoX}" y="{$logoX}" width="{$logoSize}" height="{$logoSize}" rx="{$rxLogo}" ry="{$rxLogo}"/></clipPath></defs><rect x="{$padX}" y="{$padX}" width="{$padSize}" height="{$padSize}" rx="{$rxPad}" ry="{$rxPad}" fill="#ffffff"/><rect x="{$borderX}" y="{$borderX}" width="{$borderSize}" height="{$borderSize}" rx="{$rxBorder}" ry="{$rxBorder}" fill="#000000"/><image x="{$logoX}" y="{$logoX}" width="{$logoSize}" height="{$logoSize}" href="{$logoUrl}" xlink:href="{$logoUrl}" preserveAspectRatio="xMidYMid slice" clip-path="url(#{$clipId})"/>
SVG;

        $svg = str_replace(
            '<svg xmlns="http://www.w3.org/2000/svg"',
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"',
            $svg
        );

        return str_replace('</svg>', $overlay.'</svg>', $svg);
    }

    /**
     * MOD-005, FR-024, NFR-003: Memastikan browser memiliki Pengenal Browser di cookie HttpOnly.
     */
    public function resolveOrCreateBrowserId(Request $request): string
    {
        $cookieName = (string) config('evoting.browser_cookie_name', 'evoting_browser_id');
        $existing = $request->cookie($cookieName);

        if (is_string($existing) && preg_match('/^[A-Za-z0-9_-]{24,64}$/', $existing)) {
            return $existing;
        }

        $sessionCached = $request->session()->get('_evoting_browser_id');
        if (is_string($sessionCached) && preg_match('/^[A-Za-z0-9_-]{24,64}$/', $sessionCached)) {
            $this->queueBrowserCookie($sessionCached, $request->isSecure());

            return $sessionCached;
        }

        $browserId = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $request->session()->put('_evoting_browser_id', $browserId);
        $this->queueBrowserCookie($browserId, $request->isSecure());

        return $browserId;
    }

    public static function shortBrowserLabel(?string $browserId, ?string $userAgent = null): string
    {
        $ua = (string) $userAgent;
        $browser = 'Browser';
        if (stripos($ua, 'Edg/') !== false) {
            $browser = 'Edge';
        } elseif (stripos($ua, 'Chrome/') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($ua, 'Firefox/') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($ua, 'Safari/') !== false) {
            $browser = 'Safari';
        }

        $os = '';
        if (stripos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $os = 'iOS';
        } elseif (stripos($ua, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'Mac OS') !== false) {
            $os = 'macOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }

        $shortId = $browserId ? strtoupper(substr(hash('sha256', $browserId), 0, 6)) : '------';
        $prefix = trim($browser.($os !== '' ? " {$os}" : ''));

        return "{$prefix} · #{$shortId}";
    }

    private function queueBrowserCookie(string $browserId, bool $secure): void
    {
        $cookieName = (string) config('evoting.browser_cookie_name', 'evoting_browser_id');
        $minutes = ((int) config('evoting.browser_cookie_days', 30)) * 24 * 60;

        Cookie::queue(cookie(
            name: $cookieName,
            value: $browserId,
            minutes: $minutes,
            path: '/',
            domain: null,
            secure: $secure,
            httpOnly: true,
            raw: false,
            sameSite: 'lax'
        ));
    }
}
