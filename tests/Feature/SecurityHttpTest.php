<?php

namespace Tests\Feature;

use App\Services\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityHttpTest extends TestCase
{
    public function test_origin_and_whitelist_enforce_report_and_off(): void
    {
        config(['app.url' => 'https://evoting.test']);
        $this->mock(SecurityAudit::class, function ($audit) {
            $audit->shouldReceive('record')->with('origin_rejected', \Mockery::type(Request::class))->twice();
            $audit->shouldReceive('record')->with('whitelist_shape', \Mockery::type(Request::class))->once();
        });

        $this->post(route('logout'))->assertForbidden();
        $this->post(route('logout'), [], ['Origin' => 'https://other.test'])->assertForbidden();
        $url = route('logout').'?unknown=1';
        $this->post($url, [], ['Origin' => 'https://evoting.test'])->assertForbidden();

        config(['security.whitelist_mode' => 'report']);
        $this->post(route('logout'), ['_token' => 'test'], ['Referer' => 'https://evoting.test/login'])->assertRedirect();
        config(['security.whitelist_mode' => 'off', 'security.origin_mode' => 'off']);
        $this->post($url)->assertRedirect();
    }

    public function test_livewire_whitelist_checks_shape_and_values_before_hydration(): void
    {
        $this->mock(SecurityAudit::class, function ($audit) {
            $audit->shouldReceive('record')->with('whitelist_shape', \Mockery::type(Request::class))->once();
            $audit->shouldReceive('record')->with('whitelist_value', \Mockery::type(Request::class))->once();
        });
        $request = fn ($method, $params) => $this->postJson(Livewire::getUpdateUri(), [
            'components' => [[
                'snapshot' => json_encode(['memo' => ['name' => 'voter.ballot-page']]),
                'updates' => [],
                'calls' => [['method' => $method, 'params' => $params, 'metadata' => ['type' => 'action']]],
            ]],
        ], ['X-Livewire' => 'true', 'Origin' => config('app.url')]);

        $request('unlistedAction', [])->assertForbidden();
        $request('confirmVote', ['not-an-id'])->assertUnprocessable();
    }

    public function test_client_ip_comes_only_from_a_trusted_cloudflare_peer(): void
    {
        config(['security.cloudflare_ranges' => ['192.0.2.0/24']]);
        Route::get('/security-test-ip', fn (Request $request) => $request->ip());
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
            ->get('/security-test-ip', ['CF-Connecting-IP' => '203.0.113.10'])->assertSee('203.0.113.10');
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
            ->get('/security-test-ip', ['CF-Connecting-IP' => '203.0.113.10'])->assertSee('198.51.100.1');
    }

    public function test_route_coverage_command_and_response_headers(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Content-Security-Policy-Report-Only')
            ->assertHeader('X-Request-ID')
            ->assertHeaderMissing('Content-Security-Policy');

        $this->artisan('security:whitelist-check')->assertSuccessful();
        Route::post('/security-test-unlisted', fn () => 'unlisted')->name('security-test-unlisted');
        $this->artisan('security:whitelist-check')->assertFailed();
    }

    public function test_origin_and_whitelist_do_not_replace_csrf_validation(): void
    {
        $this->app->instance('env', 'local');
        $this->mock(SecurityAudit::class, function ($audit) {
            $audit->shouldReceive('record')->with('csrf_rejected', \Mockery::type(Request::class))->once();
        });
        $this->withSession(['_token' => 'fixture-session-token'])
            ->post(route('logout'), ['_token' => 'incorrect'], ['Origin' => config('app.url')])
            ->assertStatus(419);
        $this->withSession(['_token' => 'fixture-session-token'])
            ->post(route('logout'), ['_token' => 'fixture-session-token'], ['Origin' => config('app.url')])
            ->assertRedirect();
    }

    public function test_qr_rate_limit_allows_other_tokens_using_the_same_ip(): void
    {
        Route::get('/security-test-rate/{token}', fn () => 'ok')->middleware('throttle:voter-access');
        $this->mock(SecurityAudit::class, function ($audit) {
            $audit->shouldReceive('record')->with('rate_limited', \Mockery::type(Request::class))->once();
        });
        for ($index = 0; $index < 30; $index++) {
            $this->get('/security-test-rate/same-token')->assertOk();
        }
        $this->get('/security-test-rate/same-token')->assertTooManyRequests()->assertHeader('Retry-After');
        $this->get('/security-test-rate/different-token')->assertOk();
    }
}
