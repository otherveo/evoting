<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class RestoreCloudflareClientIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $peer = $request->server('REMOTE_ADDR');
        $client = $request->headers->get('CF-Connecting-IP');
        if (is_string($peer) && filter_var($client, FILTER_VALIDATE_IP)
            && IpUtils::checkIp($peer, config('security.cloudflare_ranges', []))) {
            $request->server->set('REMOTE_ADDR', $client);
        }

        return $next($request);
    }
}
