<?php

namespace Tests;

use App\Services\VotingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PDO;

trait UsesLocalVotingDatabase
{
    protected PDO $fixtureDb;

    protected function setUp(): void
    {
        parent::setUp();
        $port = filter_var(getenv('EVOTING_TEST_PORT'), FILTER_VALIDATE_INT);
        if (! $port || ! getenv('EVOTING_TEST_OWNER_PASSWORD')) {
            $this->markTestSkipped('Siapkan template PostgreSQL lokal sesuai README untuk pengujian integrasi.');
        }

        $dsn = "pgsql:host=127.0.0.1;port={$port};sslmode=disable;dbname=";
        $ownerPassword = getenv('EVOTING_TEST_OWNER_PASSWORD');
        $owner = new PDO($dsn.'postgres', 'postgres', $ownerPassword);
        $name = 'evoting_test_'.bin2hex(random_bytes(8));
        $owner->exec("create database {$name} template evoting_test_template");
        $this->beforeApplicationDestroyed(function () use ($owner, $name) {
            DB::purge('pgsql');
            DB::purge('staff');
            unset($this->fixtureDb);
            $owner->exec("drop database {$name} with (force)");
        });

        $this->fixtureDb = new PDO($dsn.$name, 'postgres', $ownerPassword);
        foreach (['pgsql' => 'app', 'staff' => 'staff'] as $connection => $role) {
            config(["database.connections.{$connection}" => array_merge(config("database.connections.{$connection}"), [
                'url' => null, 'host' => '127.0.0.1', 'port' => $port, 'database' => $name,
                'username' => 'evoting_'.$role, 'password' => getenv('EVOTING_TEST_'.strtoupper($role).'_PASSWORD'),
                'sslmode' => 'disable', 'options' => [],
            ])]);
        }
        config(['database.default' => 'pgsql', 'evoting.supabase.url' => 'https://storage.test',
            'evoting.supabase.secret_key' => 'test-only', 'evoting.supabase.bucket' => 'paslon-foto']);
        VotingService::$scheduleCache = [];
        VotingService::$dbOffsetMs = 0;
        Http::preventStrayRequests();
        Http::fake(['https://storage.test/*' => Http::response('', 200, ['Content-Type' => 'image/png'])]);
        // actingAs bypasses LoginPage, which normally binds the credential version.
        $this->withSession(['staff_session_version' => 1]);
    }
}
