<?php

use App\Http\Middleware\EnforceRequestWhitelist;
use App\Http\Middleware\EnsureSameOriginRequest;
use App\Http\Middleware\EnsureStaffAuthenticated;
use App\Http\Middleware\EnsureStaffRole;
use App\Http\Middleware\RestoreCloudflareClientIp;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Services\SecurityAudit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeadersMiddleware::class);
        $middleware->prepend(RestoreCloudflareClientIp::class);
        $middleware->web(append: [EnsureSameOriginRequest::class, EnforceRequestWhitelist::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->alias([
            'staff.auth' => EnsureStaffAuthenticated::class,
            'staff.role' => EnsureStaffRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (QueryException $error): void {
            Log::error('Database operation failed', [
                'incident_id' => (string) Str::uuid(),
                'sql_state' => $error->getCode(),
            ]);
        })->stop();

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->respond(function (Response $response, Throwable $error, Request $request): Response {
            $event = match ($response->getStatusCode()) {
                419 => 'csrf_rejected',
                429 => 'rate_limited',
                default => null,
            };
            if ($event !== null) {
                app(SecurityAudit::class)->record($event, $request);
            }

            return SecurityHeadersMiddleware::apply($request, $response);
        });
    })->create();
