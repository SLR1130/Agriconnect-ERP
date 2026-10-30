<?php

namespace App\Services;

use App\Models\Module;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class SetupStatusService
{
    public function summary(bool $detailed = false): array
    {
        $database = $this->database($detailed);
        $directories = $this->directories();
        $migrations = $this->migrations($database['connected']);
        $modules = $this->modules($database['connected']);

        return [
            'framework' => [
                'ready' => true,
                'version' => app()->version(),
                'description' => 'Laravel '.app()->version().' installed and running',
            ],
            'directories' => $directories,
            'database' => $database,
            'migrations' => $migrations,
            'modules' => $modules,
            'overall_ready' => $directories['ready']
                && $database['connected']
                && $migrations['ready']
                && $modules['ready'],
        ];
    }

    private function directories(): array
    {
        $required = [
            'app',
            'config',
            'database/migrations',
            'resources/views',
            'routes',
            'storage',
        ];

        $missing = collect($required)
            ->reject(fn (string $path) => File::isDirectory(base_path($path)))
            ->values()
            ->all();

        return [
            'ready' => $missing === [],
            'required' => count($required),
            'missing' => $missing,
            'description' => $missing === []
                ? 'Required project directories are available'
                : count($missing).' required project director'.(count($missing) === 1 ? 'y is' : 'ies are').' missing',
        ];
    }

    private function database(bool $detailed): array
    {
        $driver = (string) config('database.default');
        $driverLabel = [
            'mysql' => 'MySQL',
            'pgsql' => 'PostgreSQL',
            'sqlite' => 'SQLite',
            'sqlsrv' => 'SQL Server',
        ][$driver] ?? strtoupper($driver);

        try {
            $connection = DB::connection();
            $connection->getPdo();
            $connection->select('select 1 as connected');

            $status = [
                'connected' => true,
                'driver' => $driver,
                'driver_label' => $driverLabel,
                'name' => $connection->getDatabaseName(),
                'description' => 'Connected to '.$driverLabel,
            ];

            if ($detailed) {
                $status['details'] = [
                    'driver' => $driver,
                    'database' => $connection->getDatabaseName(),
                    'host' => (string) config('database.connections.'.$driver.'.host'),
                    'port' => (string) config('database.connections.'.$driver.'.port'),
                    'username' => (string) config('database.connections.'.$driver.'.username'),
                ];
            }

            return $status;
        } catch (Throwable) {
            Log::warning('AgriConnect database health check failed.', [
                'driver' => $driver,
            ]);

            return [
                'connected' => false,
                'driver' => $driver,
                'driver_label' => $driverLabel,
                'name' => null,
                'description' => 'Database connection unavailable',
                'error' => 'Unable to connect to the configured database.',
            ];
        }
    }

    private function migrations(bool $databaseConnected): array
    {
        $files = collect(File::files(database_path('migrations')))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->map(fn ($file) => $file->getFilenameWithoutExtension())
            ->values();

        if (! $databaseConnected) {
            return [
                'ready' => false,
                'checked' => false,
                'total' => $files->count(),
                'applied' => 0,
                'pending' => $files->all(),
                'description' => 'Migration status unavailable until the database connects',
            ];
        }

        try {
            $applied = DB::table(config('database.migrations.table', 'migrations'))
                ->pluck('migration')
                ->all();
            $pending = $files->diff($applied)->values()->all();

            return [
                'ready' => $pending === [],
                'checked' => true,
                'total' => $files->count(),
                'applied' => $files->count() - count($pending),
                'pending' => $pending,
                'description' => $pending === []
                    ? $files->count().' migrations applied; no pending migrations'
                    : count($pending).' migration'.(count($pending) === 1 ? '' : 's').' pending',
            ];
        } catch (Throwable) {
            Log::warning('AgriConnect migration health check failed.');

            return [
                'ready' => false,
                'checked' => false,
                'total' => $files->count(),
                'applied' => 0,
                'pending' => $files->all(),
                'description' => 'Migration status could not be read',
            ];
        }
    }

    private function modules(bool $databaseConnected): array
    {
        $configured = config('agriconnect.modules', []);
        $slugs = array_keys($configured);
        $registered = collect();

        if ($databaseConnected) {
            try {
                $registered = Module::query()
                    ->whereIn('slug', $slugs)
                    ->get(['slug', 'is_enabled', 'is_active', 'development_progress'])
                    ->keyBy('slug');
            } catch (Throwable) {
                Log::warning('AgriConnect module health check failed.');
            }
        }

        $modules = collect($configured)->map(function (array $config, string $slug) use ($registered) {
            $record = $registered->get($slug);

            return [
                'slug' => $slug,
                'name' => $config['name'],
                'developer' => $config['developer'],
                'description' => $config['description'],
                'registered' => $record !== null,
                'enabled' => (bool) ($record?->is_enabled ?? false),
                'active' => (bool) ($record?->is_active ?? false),
                'progress' => (int) ($record?->development_progress ?? 0),
            ];
        })->values();

        $registeredCount = $modules->where('registered', true)->count();
        $expectedCount = count($slugs);

        return [
            'ready' => $databaseConnected && $registeredCount === $expectedCount,
            'expected' => $expectedCount,
            'registered' => $registeredCount,
            'modules' => $modules->all(),
            'description' => $databaseConnected
                ? $registeredCount.'/'.$expectedCount.' ERP modules registered in the database'
                : 'Module registration status unavailable until the database connects',
        ];
    }
}
