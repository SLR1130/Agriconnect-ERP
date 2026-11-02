<?php

namespace App\Services;

use App\Contracts\PrivateApplicationDocumentScanner;
use Symfony\Component\Process\Process;
use Throwable;

class ClamAvApplicationDocumentScanner implements PrivateApplicationDocumentScanner
{
    public function __construct(private readonly ApplicationDocumentUploadPolicy $uploadPolicy) {}

    /** Quick preflight used by upload and review screens; the scan itself is the compatibility probe. */
    public function available(): bool
    {
        return $this->preflight()['ready'];
    }

    /**
     * Full health check. It returns safe status values only; configured paths and
     * subprocess output are intentionally never returned to callers.
     *
     * @return array{ready:bool,checks:array<string,string>,signature_age_hours:?int,signature_max_age_hours:int,engine_version:?string}
     */
    public function health(): array
    {
        $preflight = $this->preflight();
        $checks = $preflight['checks'];
        $compatibility = 'not_checked';
        $version = null;

        if ($preflight['ready']) {
            [$compatibility, $version] = $this->probeEngine(
                (string) config('agricultural_documents.scanner_binary'),
                (string) config('agricultural_documents.scanner_database_directory'),
            );
        }

        $checks['engine_compatibility'] = $compatibility;

        return [
            'ready' => $preflight['ready'] && $compatibility === 'passed',
            'checks' => $checks,
            'signature_age_hours' => $preflight['signature_age_hours'],
            'signature_max_age_hours' => $preflight['signature_max_age_hours'],
            'engine_version' => $version,
        ];
    }

    public function scan(string $absolutePath): array
    {
        $preflight = $this->preflight();
        if (! $preflight['ready']) {
            return ['status' => 'unavailable', 'engine' => null];
        }

        $binary = (string) config('agricultural_documents.scanner_binary');
        $databaseDirectory = (string) config('agricultural_documents.scanner_database_directory');
        $maximumBytes = $this->uploadPolicy->maximumBytes();

        try {
            $process = new Process([
                $binary,
                '--database='.$databaseDirectory,
                '--max-filesize='.$maximumBytes,
                '--max-scansize='.$maximumBytes,
                '--no-summary',
                $absolutePath,
            ]);
            $process->setTimeout($this->timeoutSeconds());
            $process->run();

            return match ($process->getExitCode()) {
                0 => ['status' => 'clean', 'engine' => 'ClamAV'],
                1 => ['status' => 'infected', 'engine' => 'ClamAV'],
                default => ['status' => 'failed', 'engine' => 'ClamAV'],
            };
        } catch (Throwable) {
            return ['status' => 'failed', 'engine' => 'ClamAV'];
        }
    }

    /** @return array{ready:bool,checks:array<string,string>,signature_age_hours:?int,signature_max_age_hours:int} */
    private function preflight(): array
    {
        $binary = config('agricultural_documents.scanner_binary');
        $databaseDirectory = config('agricultural_documents.scanner_database_directory');
        $maxAge = (int) config('agricultural_documents.signature_max_age_hours', 36);
        $checks = [
            'scanner_executable' => is_string($binary) && $binary !== '' && is_file($binary) && is_executable($binary) ? 'ready' : 'missing',
            'signature_databases' => 'missing',
            'signature_freshness' => 'unknown',
        ];

        if ($checks['scanner_executable'] !== 'ready'
            || ! is_string($databaseDirectory)
            || $databaseDirectory === ''
            || ! is_dir($databaseDirectory)
            || ! is_readable($databaseDirectory)) {
            return ['ready' => false, 'checks' => $checks, 'signature_age_hours' => null, 'signature_max_age_hours' => $maxAge];
        }

        $requiredDatabases = config('agricultural_documents.required_signature_databases', ['main', 'daily', 'bytecode']);
        if (! is_array($requiredDatabases) || $requiredDatabases === []) {
            return ['ready' => false, 'checks' => $checks, 'signature_age_hours' => null, 'signature_max_age_hours' => $maxAge];
        }

        foreach ($requiredDatabases as $database) {
            if (! is_string($database) || ! preg_match('/^[a-z0-9_-]+$/i', $database)) {
                return ['ready' => false, 'checks' => $checks, 'signature_age_hours' => null, 'signature_max_age_hours' => $maxAge];
            }
            $files = $this->databaseFiles($databaseDirectory, $database);
            if ($files === []) {
                return ['ready' => false, 'checks' => $checks, 'signature_age_hours' => null, 'signature_max_age_hours' => $maxAge];
            }
        }
        $checks['signature_databases'] = 'ready';

        $dailyFiles = $this->databaseFiles($databaseDirectory, 'daily');
        $modifiedTimes = array_filter(array_map(static fn (string $file): int|false => @filemtime($file), $dailyFiles), static fn ($time): bool => $time !== false);
        if ($modifiedTimes === [] || $maxAge < 1) {
            $checks['signature_freshness'] = 'unknown';
            return ['ready' => false, 'checks' => $checks, 'signature_age_hours' => null, 'signature_max_age_hours' => $maxAge];
        }

        $ageSeconds = time() - max($modifiedTimes);
        $ageHours = (int) floor(max(0, $ageSeconds) / 3600);
        $fresh = $ageSeconds >= 0 && $ageSeconds <= $maxAge * 3600;
        $checks['signature_freshness'] = $fresh ? 'fresh' : 'stale';

        return [
            'ready' => $checks['scanner_executable'] === 'ready'
                && $checks['signature_databases'] === 'ready'
                && $checks['signature_freshness'] === 'fresh',
            'checks' => $checks,
            'signature_age_hours' => $ageHours,
            'signature_max_age_hours' => $maxAge,
        ];
    }

    /** @return list<string> */
    private function databaseFiles(string $directory, string $name): array
    {
        $files = [];
        foreach (['cvd', 'cld'] as $extension) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR.'\\/').DIRECTORY_SEPARATOR.$name.'.'.$extension;
            if (is_file($path) && is_readable($path) && (int) @filesize($path) > 0) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /** @return array{0:string,1:?string} */
    private function probeEngine(string $binary, string $databaseDirectory): array
    {
        $probePath = tempnam(sys_get_temp_dir(), 'agri-clamav-health-');
        if ($probePath === false) {
            return ['failed', null];
        }

        try {
            if (file_put_contents($probePath, "AgriConnect scanner readiness probe\n") === false) {
                return ['failed', null];
            }

            $probe = new Process([$binary, '--database='.$databaseDirectory, '--no-summary', $probePath]);
            $probe->setTimeout($this->timeoutSeconds());
            $probe->run();
            $probeOutput = $probe->getOutput()."\n".$probe->getErrorOutput();
            $compatible = $probe->getExitCode() === 0 && preg_match('/:\\s*OK(?:\\s|$)/i', $probeOutput) === 1;

            $versionProcess = new Process([$binary, '--version']);
            $versionProcess->setTimeout($this->timeoutSeconds());
            $versionProcess->run();
            $versionOutput = $versionProcess->getOutput()."\n".$versionProcess->getErrorOutput();
            $version = $versionProcess->getExitCode() === 0
                && preg_match('/\\bClamAV\\s+([0-9][A-Za-z0-9.+-]*)/i', $versionOutput, $matches) === 1
                    ? $matches[1]
                    : null;

            return [$compatible ? 'passed' : 'failed', $version];
        } catch (Throwable) {
            return ['failed', null];
        } finally {
            @unlink($probePath);
        }
    }

    private function timeoutSeconds(): int
    {
        $timeout = max(1, min(600, (int) config('agricultural_documents.scan_timeout_seconds', 60)));
        $phpTimeout = (int) ini_get('max_execution_time');
        if ($phpTimeout > 0) {
            $timeout = min($timeout, max(1, $phpTimeout - 5));
        }

        return $timeout;
    }
}
