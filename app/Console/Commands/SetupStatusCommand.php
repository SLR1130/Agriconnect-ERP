<?php

namespace App\Console\Commands;

use App\Services\SetupStatusService;
use Illuminate\Console\Command;

class SetupStatusCommand extends Command
{
    protected $signature = 'agriconnect:status';
    protected $description = 'Display the live AgriConnect ERP setup status';

    public function handle(SetupStatusService $statusService): int
    {
        $status = $statusService->summary();

        $this->info('AgriConnect ERP status');
        $this->line('Laravel: '.$status['framework']['version']);
        $this->line('Directories: '.($status['directories']['ready'] ? 'Ready' : $status['directories']['description']));
        $this->line('Database: '.$status['database']['description']);
        $this->line('Migrations: '.$status['migrations']['description']);
        $this->line('Modules: '.$status['modules']['description']);

        if (! $status['database']['connected']) {
            $this->warn($status['database']['error']);
        }

        foreach ($status['modules']['modules'] as $module) {
            $this->line(sprintf(
                '  - %s: %s',
                $module['slug'],
                $module['registered'] ? ($module['enabled'] ? 'registered and enabled' : 'registered but disabled') : 'not registered',
            ));
        }

        return $status['overall_ready'] ? 0 : 1;
    }
}
