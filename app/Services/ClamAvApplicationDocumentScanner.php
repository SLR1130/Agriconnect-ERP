<?php

namespace App\Services;

use App\Contracts\PrivateApplicationDocumentScanner;
use Symfony\Component\Process\Process;
use Throwable;

class ClamAvApplicationDocumentScanner implements PrivateApplicationDocumentScanner
{
    public function available(): bool
    {
        $binary = config('agricultural_documents.scanner_binary');

        return is_string($binary) && $binary !== '' && is_file($binary) && is_executable($binary);
    }

    public function scan(string $absolutePath): array
    {
        $binary = config('agricultural_documents.scanner_binary');
        if (! $this->available()) {
            return ['status' => 'unavailable', 'engine' => null];
        }

        try {
            $process = new Process([$binary, '--no-summary', $absolutePath]);
            $process->setTimeout((int) config('agricultural_documents.scan_timeout_seconds', 30));
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
}
