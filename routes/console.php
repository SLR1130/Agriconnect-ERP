<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\ClamAvApplicationDocumentScanner;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('agricultural-documents:scanner-health', function (ClamAvApplicationDocumentScanner $scanner) {
    $health = $scanner->health();
    $this->table(
        ['Readiness check', 'Result'],
        collect($health['checks'])->map(fn (string $result, string $check): array => [str($check)->replace('_', ' ')->headline(), $result])->values()->all(),
    );
    $this->line('Signature age: '.($health['signature_age_hours'] === null ? 'unknown' : $health['signature_age_hours'].' hours'));
    $this->line('Allowed signature age: '.$health['signature_max_age_hours'].' hours');
    $this->line('ClamAV engine: '.($health['engine_version'] ?? 'unavailable'));

    if (! $health['ready']) {
        $this->error('Scanner is not ready. Document uploads must remain disabled.');

        return 1;
    }

    $this->info('Scanner readiness checks passed.');

    return 0;
})->purpose('Check ClamAV executable, signature freshness, and database compatibility without exposing configured paths');
