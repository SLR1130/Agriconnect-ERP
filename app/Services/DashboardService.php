<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Module;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** @return array<string, array{value: int, description: string}> */
    public function getSystemStats(): array
    {
        return [
            'total_users' => $this->stat(User::query()->count(), 'Accounts, including farmer portal users.'),
            'active_users' => $this->stat(User::query()->where('is_active', true)->whereNull('suspended_at')->count(), 'Accounts currently permitted to sign in.'),
            'active_roles' => $this->stat(DB::table('roles')->where('is_active', true)->count(), 'Role definitions enabled for assignment.'),
            'enabled_modules' => $this->stat(Module::query()->where('is_enabled', true)->count(), 'Modules marked enabled in the shared registry.'),
            'activity_logs' => $this->stat(ActivityLog::query()->count(), 'Audit events recorded by the application.'),
        ];
    }

    /** @return array{role_label: string, dashboard_title: string, eyebrow: string, headline: string, description: string} */
    public function getRoleContext(User $user): array
    {
        if ($user->isSuperAdministrator()) {
            return [
                'role_label' => 'Super Administrator',
                'dashboard_title' => 'Administrator Command Center',
                'eyebrow' => 'ERP-wide administration',
                'headline' => 'A shared operational view of municipal agriculture.',
                'description' => 'Review connected services, administrative activity, and authorized operational summaries from one AgriConnect workspace.',
            ];
        }

        if ($user->hasRole('mao_admin')) {
            return [
                'role_label' => 'MAO Administrator',
                'dashboard_title' => 'MAO Operations Dashboard',
                'eyebrow' => 'Municipal operations',
                'headline' => 'Coordinate the work that keeps agricultural services moving.',
                'description' => 'Monitor current queues and service records across the agricultural modules available to your role.',
            ];
        }

        if ($user->hasRole('municipal_agriculturist')) {
            return [
                'role_label' => 'Municipal Agriculturist',
                'dashboard_title' => 'MAO Operations Dashboard',
                'eyebrow' => 'Technical agriculture',
                'headline' => 'Use field records to guide timely agricultural decisions.',
                'description' => 'Review farmer, production, monitoring, and technical assistance information within your assigned authority.',
            ];
        }

        if ($user->hasRole('module_personnel')) {
            return [
                'role_label' => 'Module Personnel',
                'dashboard_title' => 'Assigned Operations Dashboard',
                'eyebrow' => 'Assigned operations',
                'headline' => 'Work from the agricultural responsibilities assigned to you.',
                'description' => 'The summaries and shortcuts below follow your effective module and action permissions.',
            ];
        }

        if ($user->hasRole('authorized_viewer')) {
            return [
                'role_label' => 'Auditor',
                'dashboard_title' => 'Agricultural Oversight Dashboard',
                'eyebrow' => 'Read-only oversight',
                'headline' => 'Review authorized operational and financial records.',
                'description' => 'This workspace provides read-only summaries and reports for the records available to your account.',
            ];
        }

        return [
            'role_label' => 'ERP Staff',
            'dashboard_title' => 'Agricultural Management Dashboard',
            'eyebrow' => 'Authorized operations',
            'headline' => 'Connected agricultural operations in one system.',
            'description' => 'Your dashboard content follows the effective permissions assigned to this account.',
        ];
    }

    /** Return only a sanitized database engine label for the authenticated dashboard. */
    public function getDatabaseLabel(): string
    {
        try {
            $connection = DB::connection();

            return match ($connection->getDriverName()) {
                'mysql', 'mariadb' => 'MySQL',
                'sqlite' => $connection->getDatabaseName() === ':memory:' ? 'Isolated SQLite' : 'SQLite',
                'pgsql' => 'PostgreSQL',
                'sqlsrv' => 'SQL Server',
                default => 'Configured database',
            };
        } catch (\Throwable) {
            return 'Configured database';
        }
    }

    /** @return array<string, array{name: string, description: string, enabled: bool}> */
    public function getModuleStatus(): array
    {
        return Module::query()
            ->orderBy('sort_order')
            ->get(['name', 'slug', 'description', 'is_enabled'])
            ->mapWithKeys(fn (Module $module) => [
                $module->slug => [
                    'name' => $module->name,
                    'description' => $module->description,
                    'enabled' => $module->is_enabled,
                ],
            ])
            ->all();
    }

    /** @return array{connected: bool, database: string|null, checked_at: Carbon} */
    public function getSystemHealth(): array
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();

            return [
                'connected' => true,
                'database' => match ($connection->getDriverName()) {
                    'mysql', 'mariadb' => 'MySQL',
                    'sqlite' => $connection->getDatabaseName() === ':memory:' ? 'Isolated SQLite' : 'SQLite',
                    'pgsql' => 'PostgreSQL',
                    'sqlsrv' => 'SQL Server',
                    default => 'Configured database',
                },
                'checked_at' => now(),
            ];
        } catch (\Throwable) {
            return [
                'connected' => false,
                'database' => null,
                'checked_at' => now(),
            ];
        }
    }

    /** @return list<array{description: string, user: string, time: string, module: string}> */
    public function getRecentActivities(User $user, int $limit = 8): array
    {
        if (! $user->canErp('activity_logs.view')) {
            return [];
        }

        return ActivityLog::query()
            ->with('causer')
            ->latest()
            ->limit(max(1, min($limit, 20)))
            ->get()
            ->map(fn (ActivityLog $activity) => [
                'description' => $activity->description,
                'user' => $activity->causer?->name ?? 'System',
                'time' => $activity->created_at?->diffForHumans() ?? 'Unknown time',
                'module' => $activity->module ?: 'ERP Core',
            ])
            ->all();
    }

    /** @return list<array{title: string, deadline: Carbon, priority: string}> */
    public function getUpcomingDeadlines(User $user, int $limit = 5): array
    {
        $query = WorkflowRequest::query()
            ->whereIn('status', ['pending', 'in_review', 'returned'])
            ->whereNotNull('due_at')
            ->where('due_at', '>=', now());

        if (! $user->isSuperAdministrator()) {
            $moduleIds = Module::query()->whereIn('slug', app(ErpAuthorizationService::class)->visibleModules($user))->pluck('id');
            if ($moduleIds->isEmpty()) {
                return [];
            }

            $query->whereIn('module_id', $moduleIds);
        }

        return $query->orderBy('due_at')->limit(max(1, min($limit, 20)))
            ->get(['title', 'due_at', 'status'])
            ->map(fn (WorkflowRequest $request) => [
                'title' => $request->title,
                'deadline' => $request->due_at,
                'priority' => $request->due_at->diffInHours(now()) <= 24 ? 'high' : 'normal',
            ])
            ->all();
    }

    /** @return array{value: int, description: string} */
    private function stat(int $value, string $description): array
    {
        return compact('value', 'description');
    }
}
