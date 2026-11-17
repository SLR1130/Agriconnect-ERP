<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StaffAssistedIntakePolicy
{
    /**
     * Assisted submissions are only executable in explicitly configured local
     * or test environments backed by SQLite and synthetic QA attestations.
     * No MySQL connection can activate this Phase 6C.3 implementation.
     */
    public function enabledForIsolatedQa(): bool
    {
        if (! config('agricultural_staff_assisted.enabled')
            || ! config('agricultural_staff_assisted.qa_isolated_database')
            || ! config('agricultural_staff_assisted.synthetic_qa_attestations')
            || ! app()->environment(['local', 'testing'])) {
            return false;
        }

        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite') {
            return false;
        }

        $database = (string) $connection->getConfig('database');
        if ($database === ':memory:') {
            return app()->environment('testing');
        }

        $configuredQaPath = config('agricultural_staff_assisted.qa_database_path');
        if (! is_string($configuredQaPath) || trim($configuredQaPath) === '') {
            return false;
        }

        $actualPath = $this->normalizedPath($database);
        $expectedPath = $this->normalizedPath($configuredQaPath);
        $qaRoot = $this->normalizedPath(storage_path('app/qa'));

        return $actualPath === $expectedPath
            && str_starts_with(strtolower($actualPath), strtolower(rtrim($qaRoot, '/').'/'))
            && strtolower(basename($actualPath)) === 'agriconnect-phase6c3-qa.sqlite';
    }

    public function assertEnabledForIsolatedQa(): void
    {
        if (! $this->enabledForIsolatedQa()) {
            throw new HttpException(503, 'Staff-assisted applications are disabled. They are available only in an explicitly configured isolated SQLite QA environment until MAO identity and consent procedures are approved.');
        }
    }

    /** @return list<string> */
    public function identityConfirmationMethods(): array
    {
        if (! $this->enabledForIsolatedQa()) {
            return [];
        }

        return array_values(array_unique(array_filter(
            config('agricultural_staff_assisted.identity_confirmation_methods', []),
            fn ($method): bool => is_string($method) && $method === 'synthetic_qa',
        )));
    }

    public function attestationPolicyVersion(): string
    {
        return (string) (config('agricultural_staff_assisted.consent_policy_version') ?: 'synthetic-qa-only');
    }

    private function normalizedPath(string $path): string
    {
        $absolute = str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
            ? $path
            : base_path($path);
        $resolved = realpath($absolute) ?: $absolute;

        return strtolower(str_replace('\\', '/', rtrim($resolved, '\\/')));
    }
}
