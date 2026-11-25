<?php

use App\Http\Middleware\EnforceRequestWhitelist;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('security:whitelist-check', function () {
    $rules = config('request_whitelist.routes');
    $missing = collect(Route::getRoutes()->getRoutes())->flatMap(function ($route) use ($rules) {
        $key = EnforceRequestWhitelist::routeKey($route);
        $allowed = match ($rules[$key][0] ?? null) {
            'GET' => ['GET', 'HEAD'],
            null => [],
            default => [$rules[$key][0]],
        };

        return array_map(fn ($method) => $method.' '.$key, array_diff($route->methods(), $allowed));
    });
    if ($missing->isNotEmpty()) {
        $missing->each(fn ($route) => $this->error($route));

        return 1;
    }

    $this->info('All registered routes and methods have a whitelist entry.');

    return 0;
})->purpose('Check request whitelist coverage for all routes');

Artisan::command('audit:verify', function () {
    $result = DB::connection('staff')->selectOne('select * from evoting.fn_verify_security_events()');
    if ($result->first_bad_id !== null) {
        $this->error('Security audit mismatch at event '.$result->first_bad_id);

        return 1;
    }

    $this->info("Security audit verified: {$result->records} records. Head: ".($result->head ?? '(empty)'));

    return 0;
})->purpose('Verify the security audit hash chain');
