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
     * @return array{ready:bool,checks:array<string,string>,signature_age_hours:?int,signature_max_age_hours:int,signature_freshness_source:string,engine_version:?string}
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
            'signature_freshness_source' => $preflight['signature_freshness_source'],
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

        if (DIRECTORY_SEPARATOR === '\\') {
            $binary = str_replace('/', '\\', $binary);
            $databaseDirectory = str_replace('/', '\\', $databaseDirectory);
            $absolutePath = str_replace('/', '\\', $absolutePath);
        }

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

    /** @return array{ready:bool,checks:array<string,string>,signature_age_hours:?int,signature_max_age_hours:int,signature_freshness_source:string} */
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
            $checks['signature_freshness_source'] = 'unknown';

            return ['ready' => false, 'checks' => $checks, 'signature_age_hours' => null, 'signature_max_age_hours' => $maxAge, 'signature_freshness_source' => 'unknown'];
        }

        $requiredDatabases = config('agricultural_documents.required_signature_databases', ['main', 'daily', 'bytecode']);
        if (! is_array($requiredDatabases) || $requiredDatabases === []) {
            $checks['signature_freshness_source'] = 'unknown';

            return ['ready' => false, 'checks' => $checks, 'signature_age_hours' => null, 'signature_max_age_hours' => $maxAge, 'signature_freshness_source' => 'unknown'];
        }

        foreach ($requiredDatabases as $database) {
            if (! is_string($database) || ! preg_match('/^[a-z0-9_-]+$/i', $database)) {
                $checks['signature_freshness_source'] = 'unknown';

                return ['ready' => false, 'checks' => $checks, 'signature_age_hours' => null, 'signature_max_age_hours' => $maxAge, 'signature_freshness_source' => 'unknown'];
            }
            $files = $this->databaseFiles($databaseDirectory, $database);
            if ($files === []) {
                $checks['signature_freshness_source'] = 'unknown';

                return ['ready' => false, 'checks' => $checks, 'signature_age_hours' => null, 'signature_max_age_hours' => $maxAge, 'signature_freshness_source' => 'unknown'];
            }
        }
        $checks['signature_databases'] = 'ready';

        $dailyFiles = $this->databaseFiles($databaseDirectory, 'daily');
        $binary = is_string($binary) ? $binary : '';
        $metadata = $this->signatureBuildTimestamp($binary, $dailyFiles);
        $freshnessSource = $metadata['source'];
        $buildTimestamp = $metadata['timestamp'];

        if ($freshnessSource === 'file_modified_time') {
            $modifiedTimes = array_filter(array_map(static fn (string $file): int|false => @filemtime($file), $dailyFiles), static fn ($time): bool => $time !== false);
            $buildTimestamp = $modifiedTimes === [] ? null : max($modifiedTimes);
        }

        $checks['signature_freshness_source'] = $freshnessSource;

        if ($buildTimestamp === null || $maxAge < 1) {
            $checks['signature_freshness'] = 'unknown';
            return ['ready' => false, 'checks' => $checks, 'signature_age_hours' => null, 'signature_max_age_hours' => $maxAge, 'signature_freshness_source' => $freshnessSource];
        }

        $ageSeconds = time() - $buildTimestamp;
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
            'signature_freshness_source' => $freshnessSource,
        ];
    }

    /**
     * Read the build time embedded in ClamAV's signed database metadata.
     * Modification time is a clearly labeled fallback for installations
     * without sigtool; it can reflect a copy time instead of signature age.
     *
     * @param list<string> $dailyFiles
     * @return array{source:string,timestamp:?int}
     */
    private function signatureBuildTimestamp(string $scannerBinary, array $dailyFiles): array
    {
        $configuredTool = config('agricultural_documents.scanner_signature_tool');
        $tool = is_string($configuredTool) && $configuredTool !== ''
            ? $configuredTool
            : dirname($scannerBinary).DIRECTORY_SEPARATOR.(DIRECTORY_SEPARATOR === '\\' ? 'sigtool.exe' : 'sigtool');

        if (! is_file($tool) || ! is_executable($tool)) {
            return ['source' => 'file_modified_time', 'timestamp' => null];
        }

        $dailyFile = $dailyFiles[0] ?? null;
        if ($dailyFile === null) {
            return ['source' => 'database_build_time', 'timestamp' => null];
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $tool = str_replace('/', '\\', $tool);
            $dailyFile = str_replace('/', '\\', $dailyFile);
        }

        try {
            $process = new Process([$tool, '--info', $dailyFile]);
            $process->setTimeout(10);
            $process->run();

            if ($process->getExitCode() !== 0
                || preg_match('/^Build time:\\s*(\\d{1,2}\\s+[A-Za-z]{3}\\s+\\d{4}\\s+\\d{2}:\\d{2}\\s+[+-]\\d{4})\\s*$/m', $process->getOutput(), $matches) !== 1) {
                return ['source' => 'database_build_time', 'timestamp' => null];
            }

            $buildTime = \DateTimeImmutable::createFromFormat('!j M Y H:i O', $matches[1]);
            $parseErrors = \DateTimeImmutable::getLastErrors();

            if ($buildTime === false || ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0))) {
                return ['source' => 'database_build_time', 'timestamp' => null];
            }

            return ['source' => 'database_build_time', 'timestamp' => $buildTime->getTimestamp()];
        } catch (Throwable) {
            return ['source' => 'database_build_time', 'timestamp' => null];
        }
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
