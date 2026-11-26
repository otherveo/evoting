<?php

namespace App\Http\Middleware;

use App\Services\SecurityAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSameOriginRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = config('security.origin_mode');
        if ($mode === 'off' || $request->isMethodSafe()) {
            return $next($request);
        }

        $origin = $request->headers->get('Origin');
        $actual = self::origin($origin ?? $request->headers->get('Referer'), $origin === null);
        $expected = self::origin(config('app.url'), true);
        if (app()->environment('local') && $this->isLoopback($request->server('REMOTE_ADDR'))
            && $this->isLoopback($request->getHost())) {
            $expected = self::origin($request->getSchemeAndHttpHost(), false);
        }

        if ($actual === null || $actual !== $expected || $request->headers->get('Sec-Fetch-Site') === 'cross-site') {
            app(SecurityAudit::class)->record('origin_rejected', $request);
            abort_unless($mode === 'report', 403);
        }

        return $next($request);
    }

    private function isLoopback(?string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
    }

    private static function origin(?string $url, bool $allowPath): ?array
    {
        $parts = $url !== null ? parse_url($url) : false;
        if (! $parts || ! isset($parts['host'], $parts['scheme']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)
            || (! $allowPath && (isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])))) {
            return null;
        }

        return [$scheme, strtolower($parts['host']), $parts['port'] ?? ($scheme === 'https' ? 443 : 80)];
    }
}
