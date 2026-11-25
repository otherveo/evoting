<?php

namespace App\Services;

use App\Http\Middleware\EnforceRequestWhitelist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class SecurityAudit
{
    private const EVENTS = [
        'origin_rejected', 'whitelist_shape', 'whitelist_value', 'csrf_rejected', 'rate_limited',
        'login_failed', 'totp_failed', 'qr_invalid', 'qr_expired', 'qr_used', 'qr_device_limit',
    ];

    public static function requestId(Request $request): string
    {
        $id = $request->attributes->get('_security_request_id');
        if ($id === null) {
            $id = (string) Str::uuid();
            $request->attributes->set('_security_request_id', $id);
        }

        return $id;
    }

    public function record(string $event, Request $request): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            return;
        }

        try {
            $route = $request->route() ? EnforceRequestWhitelist::routeKey($request->route()) : 'unmatched';
            if (! array_key_exists($route, config('request_whitelist.routes'))) {
                $route = 'unmatched';
            }
            $ip = filter_var($request->ip(), FILTER_VALIDATE_IP) ?: null;
            $session = $request->hasSession() && $request->session()->isStarted()
                ? hash_hmac('sha256', $request->session()->getId(), (string) config('app.key'))
                : null;
            $key = hash('sha256', $event.'|'.$route.'|'.($session ?? $ip ?? 'unknown'));
            $limit = 'security:event:global';
            if (RateLimiter::tooManyAttempts($limit, 60)
                || ! Cache::add('security:event:'.$key, true, 30)
                || RateLimiter::hit($limit, 60) > 60) {
                return;
            }

            $agent = (string) $request->userAgent();
            $browser = match (true) {
                str_contains($agent, 'Edg/') => 'Edge',
                str_contains($agent, 'Chrome/') => 'Chrome',
                str_contains($agent, 'Firefox/') => 'Firefox',
                str_contains($agent, 'Safari/') => 'Safari',
                default => 'Other',
            };
            $details = [
                'event' => $event,
                'route' => $route,
                'method' => in_array($request->method(), ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], true)
                    ? $request->method() : 'OTHER',
                'ip' => $ip,
                'session_hash' => $session,
                'browser' => $browser,
                'request_id' => self::requestId($request),
            ];

            try {
                DB::connection('pgsql')->select('select evoting.fn_record_security_event(?::jsonb)', [
                    json_encode($details, JSON_THROW_ON_ERROR),
                ]);
            } catch (Throwable) {
                Log::warning('Security event persistence unavailable', $details);
            }
        } catch (Throwable) {
            // Telemetry failures must not interrupt authentication or voting.
        }
    }
}
