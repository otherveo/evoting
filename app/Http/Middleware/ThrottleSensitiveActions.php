<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleSensitiveActions
{
    private const GROUPS = [
        'startVoting' => 'ballot', 'confirmVote' => 'ballot',
        'approveSharedDevice' => 'staff', 'rejectSharedDevice' => 'staff',
        'resetVoterSession' => 'staff', 'reissueVoterQr' => 'staff', 'registerVoter' => 'registration',
        'confirmDeleteElection' => 'staff', 'executeConfirmedAction' => 'staff',
        'blockDevice' => 'staff', 'unblockDevice' => 'staff',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        foreach ((array) $request->json('components', []) as $component) {
            foreach ((array) ($component['calls'] ?? []) as $call) {
                $method = $call['method'] ?? null;
                $group = is_string($method) ? self::GROUPS[$method] ?? null : null;
                if ($group === null) {
                    continue;
                }

                $identity = $group !== 'ballot' ? $request->user('staff')?->getAuthIdentifier() : null;
                $key = 'security:action:'.$group.':'.hash('sha256', (string) ($identity ?? $request->session()->getId()));
                $maximum = match ($group) {
                    'ballot' => 16,
                    'registration' => 90,
                    default => 45,
                };
                if (RateLimiter::tooManyAttempts($key, $maximum)) {
                    abort(429, '', ['Retry-After' => (string) max(1, RateLimiter::availableIn($key))]);
                }
                RateLimiter::hit($key, 60);
            }
        }

        return $next($request);
    }
}
