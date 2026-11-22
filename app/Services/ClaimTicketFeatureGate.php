<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClaimTicketFeatureGate
{
    public function operational(): bool
    {
        return config('agricultural_claim_tickets.mode') === 'operational';
    }

    public function enabled(): bool
    {
        if (! config('agricultural_claim_tickets.enabled')) {
            return false;
        }

        $connectionName = (string) config('database.default');
        $connectionConfig = config('database.connections.'.$connectionName, []);

        if (filled($connectionConfig['url'] ?? null)) {
            return false;
        }

        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();

        if ($this->operational()) {
            if (app()->environment('testing') && $driver === 'sqlite' && $database === ':memory:') {
                return Schema::connection($connectionName)->hasTable('assistance_claim_tickets')
                    && Schema::connection($connectionName)->hasTable('assistance_handover_enrollments');
            }

            return $driver === 'mysql'
                && (bool) config('agricultural_claim_tickets.live_enabled')
                && $database !== ''
                && hash_equals((string) config('agricultural_claim_tickets.live_database'), $database)
                && Schema::connection($connectionName)->hasTable('assistance_claim_tickets')
                && Schema::connection($connectionName)->hasTable('assistance_handover_enrollments');
        }

        if (! app()->environment(['local', 'testing'])) {
            return false;
        }

        if ($driver === 'sqlite') {
            if (app()->environment('testing') && $database === ':memory:') {
                return Schema::connection($connectionName)->hasTable('assistance_claim_tickets');
            }

            return $this->normalizedPath($database) === $this->normalizedPath(
                (string) config('agricultural_claim_tickets.qa_sqlite_path'),
            )
                && str_contains($this->normalizedPath($database), '/storage/app/qa/')
                && Schema::connection($connectionName)->hasTable('assistance_claim_tickets');
        }

        if ($driver !== 'mysql') {
            return false;
        }

        $expectedDatabase = (string) config('agricultural_claim_tickets.qa_mysql_database');
        $expectedHost = (string) config('agricultural_claim_tickets.qa_mysql_host');
        $expectedPort = (string) config('agricultural_claim_tickets.qa_mysql_port');
        $expectedUuid = (string) config('agricultural_claim_tickets.qa_mysql_server_uuid');
        $expectedBackup = (string) config('agricultural_claim_tickets.qa_mysql_backup_sha256');
        if ($database === 'agriconnect_erp' || $database === '' || $database !== $expectedDatabase
            || ! str_starts_with($database, 'agriconnect_erp_claimtickets_qa_')
            || $expectedHost === '' || $expectedPort === '' || $expectedUuid === ''
            || ! preg_match('/\A[a-f0-9]{64}\z/i', $expectedBackup)
            || ! Schema::connection($connectionName)->hasTable('assistance_claim_tickets')
            || ! Schema::connection($connectionName)->hasTable('agricultural_qa_environment_attestations')) {
            return false;
        }

        try {
            $identity = (array) $connection->selectOne(
                'SELECT DATABASE() AS database_name, @@hostname AS hostname, @@port AS port, @@server_uuid AS server_uuid'
            );

            return $identity['database_name'] === $expectedDatabase
                && strcasecmp((string) $identity['hostname'], $expectedHost) === 0
                && (string) $identity['port'] === $expectedPort
                && hash_equals($expectedUuid, (string) $identity['server_uuid'])
                && DB::connection($connectionName)->table('agricultural_qa_environment_attestations')
                    ->where('driver', 'mysql')
                    ->where('database_name', $expectedDatabase)
                    ->where('server_hostname', $expectedHost)
                    ->where('server_port', (int) $expectedPort)
                    ->where('server_uuid', $expectedUuid)
                    ->where('source_backup_sha256', strtolower($expectedBackup))
                    ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function requireEnabled(): void
    {
        abort_unless($this->enabled(), 404);
    }

    public static function normalizedPath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));

        if ($normalized !== ':memory:' && ! str_starts_with($normalized, 'file:')) {
            $normalized = (string) (realpath($normalized) ?: $normalized);
        }

        // realpath() returns native Windows separators even when given a
        // forward-slash path. Normalize again after resolving so QA path
        // allowlists behave identically on Windows and Unix.
        return strtolower(rtrim(str_replace('\\', '/', $normalized), '/'));
    }
}
