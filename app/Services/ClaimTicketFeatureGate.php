<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClaimTicketFeatureGate
{
    public function enabled(): bool
    {
        if (! config('agricultural_claim_tickets.enabled') || ! app()->environment(['local', 'testing'])) {
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
        $prefix = 'agriconnect_erp_claimtickets_qa_';

        return $database !== 'agriconnect_erp'
            && $database !== ''
            && $database === $expectedDatabase
            && str_starts_with($database, $prefix)
            && Schema::connection($connectionName)->hasTable('assistance_claim_tickets');
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

        return strtolower(rtrim($normalized, '/'));
    }
}
