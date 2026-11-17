<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class TokenService
{
    /**
     * Menghasilkan pasangan token acak 256-bit (base64url) dan hash SHA-256 hex 64 karakter (NFR-002).
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

    public function renderQrSvg(string $url, int $size = 240): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }

    /**
     * Memastikan browser memiliki Pengenal Browser di cookie HttpOnly (MOD-005, FR-024, NFR-003).
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
