<?php

namespace App\Http\Middleware;

use App\Services\SecurityAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($request, $next($request));
    }

    public static function apply(Request $request, Response $response): Response
    {
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Request-ID', SecurityAudit::requestId($request));

        if ($request->isSecure() && config('security.hsts_max_age') >= 0) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.config('security.hsts_max_age'));
        }
        $mode = config('security.csp_mode');
        if (in_array($mode, ['report', 'enforce'], true) && str_starts_with($response->headers->get('Content-Type', ''), 'text/html')) {
            $policy = "default-src 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'; form-action 'self'; ";
            // Inline Blade scripts and Alpine expressions require these sources until migrated.
            $policy .= "script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ";
            $policy .= "font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob: https:; connect-src 'self';";
            $response->headers->set($mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only', $policy);
        }

        if ($request->is('v/*', 'admin*', 'operator*', 'auth*', 'pantau-rekap*', 'login', 'logout',
            'livewire-*/update', 'livewire-*/upload-file', 'livewire-*/preview-file/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }
}
