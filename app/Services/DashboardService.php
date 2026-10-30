<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AssistanceApplication;
use App\Models\AssistanceDistribution;
use App\Models\CropProductionRecord;
use App\Models\EquipmentRequest;
use App\Models\Farmer;
use App\Models\FarmMonitoringRecord;
use App\Models\InventoryMovement;
use App\Models\Module;
use App\Models\ProcurementRequest;
use App\Models\SupplyBatch;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getDashboardStats(): array
    {
        $pendingItems = WorkflowRequest::query()
            ->whereIn('status', ['pending', 'in_review', 'returned'])
            ->count();

        return [
            'total_users' => $this->stat(User::query()->count(), 'System users registered'),
            'active_roles' => $this->stat(DB::table('roles')->where('is_active', true)->count(), 'Active user roles configured'),
            'enabled_modules' => $this->stat(DB::table('modules')->where('is_enabled', true)->count(), 'Agricultural modules enabled'),
            'activity_logs' => $this->stat(DB::table('activity_logs')->count(), 'System activities recorded'),
            'pending_items' => $this->stat($pendingItems, 'Workflow requests awaiting action'),
        ];
    }

    public function getRoleContext(User $user): array
    {
        if ($user->hasRole('mao_admin')) {
            return [
                'eyebrow' => 'MAO management queue',
                'headline' => 'Coordinate the decisions that keep municipal agriculture moving.',
                'description' => 'Review operational queues, manage assistance programs, and monitor connected agricultural services.',
                'cards' => [
                    $this->roleCard('Farmer reviews', Farmer::whereIn('registration_status', ['pending', 'returned'])->count(), 'FFRMS registrations awaiting action'),
                    $this->roleCard('Assistance queue', AssistanceApplication::where('status', 'submitted')->count(), 'AASMS applications awaiting review'),
                    $this->roleCard('Procurement queue', ProcurementRequest::whereIn('status', ['submitted', 'under_review'])->count(), 'ABPMS requests under review'),
                    $this->roleCard('Equipment queue', EquipmentRequest::where('status', 'pending')->count(), 'AEAMS requests awaiting decision'),
                ],
            ];
        }

        if ($user->hasRole('municipal_agriculturist')) {
            return [
                'eyebrow' => 'Technical agriculture workspace',
                'headline' => 'Turn field records into timely agricultural decisions.',
                'description' => 'Focus on farmer assessments, production monitoring, and technical eligibility recommendations.',
                'cards' => [
                    $this->roleCard('Verified farmers', Farmer::where('registration_status', 'verified')->count(), 'Authoritative FFRMS identities'),
                    $this->roleCard('Crop records', CropProductionRecord::count(), 'APMS production observations'),
                    $this->roleCard('Monitoring visits', FarmMonitoringRecord::count(), 'Recorded farm monitoring activity'),
                    $this->roleCard('Technical reviews', AssistanceApplication::where('status', 'submitted')->count(), 'Assistance applications for assessment'),
                ],
            ];
        }

        if ($user->hasRole('module_personnel')) {
            $cards = [];

            if ($user->canModuleAction('aisms')) {
                $cards[] = $this->roleCard('Available batches', SupplyBatch::where('quantity_available', '>', 0)->count(), 'AISMS batches with stock');
                $cards[] = $this->roleCard('Inventory movements', InventoryMovement::count(), 'AISMS movement history');
            }

            if ($user->canModuleAction('apms')) {
                $cards[] = $this->roleCard('Production records', CropProductionRecord::count(), 'APMS records assigned to this role');
            }

            return [
                'eyebrow' => 'Assigned operations',
                'headline' => 'Work only the modules assigned to your personnel role.',
                'description' => 'Your shortcuts and operational queues are limited by explicit module assignments and action permissions.',
                'cards' => $cards ?: [$this->roleCard('Assigned modules', 0, 'No operational assignment is active')],
            ];
        }

        if ($user->hasRole('authorized_viewer')) {
            return [
                'eyebrow' => 'Read-only oversight',
                'headline' => 'Inspect ERP activity without changing operational records.',
                'description' => 'This account can review authorized module pages, reports, and audit history only.',
                'cards' => [
                    $this->roleCard('Activity events', ActivityLog::count(), 'Read-only audit history'),
                    $this->roleCard('Registered farmers', Farmer::count(), 'FFRMS records'),
                    $this->roleCard('Assistance distributions', AssistanceDistribution::count(), 'AASMS completed history'),
                    $this->roleCard('Inventory movements', InventoryMovement::count(), 'AISMS transaction history'),
                ],
            ];
        }

        return [
            'eyebrow' => 'ERP workspace',
            'headline' => 'Connected agricultural operations in one system.',
            'description' => 'Live records and authorized module workspaces are available from the shared ERP shell.',
            'cards' => array_values(array_map(
                fn (array $stat) => $this->roleCard($stat['title'], $stat['value'], $stat['description']),
                [
                    ['title' => 'Registered users', 'value' => User::count(), 'description' => 'System users registered'],
                    ['title' => 'Active roles', 'value' => DB::table('roles')->where('is_active', true)->count(), 'description' => 'Active role definitions'],
                    ['title' => 'Enabled modules', 'value' => DB::table('modules')->where('is_enabled', true)->count(), 'description' => 'Agricultural modules enabled'],
                    ['title' => 'Activity events', 'value' => ActivityLog::count(), 'description' => 'System activities recorded'],
                ],
            )),
        ];
    }

    public function getRecentActivities(int $limit = 10): array
    {
        return ActivityLog::query()
            ->with('causer')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (ActivityLog $activity) => [
                'type' => $activity->action_type,
                'description' => $activity->description,
                'user' => $activity->causer?->name ?? 'System',
                'time' => $activity->created_at?->diffForHumans() ?? 'Unknown time',
                'icon' => $this->getIconForAction($activity->action_type),
            ])
            ->all();
    }

    public function getModuleStatus(): array
    {
        return Module::query()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Module $module) => [
                $module->slug => [
                    'name' => $module->name,
                    'developer' => $module->developer,
                    'description' => $module->description,
                    'enabled' => $module->is_enabled,
                    'status' => $module->is_active ? 'active' : 'in-development',
                    'progress' => $module->development_progress,
                ],
            ])
            ->all();
    }

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
        } catch (\Throwable $exception) {
            return [
                'connected' => false,
                'database' => null,
                'checked_at' => now(),
            ];
        }
    }

    public function getUpcomingDeadlines(int $limit = 5): array
    {
        return WorkflowRequest::query()
            ->whereIn('status', ['pending', 'in_review', 'returned'])
            ->whereNotNull('due_at')
            ->where('due_at', '>=', now())
            ->orderBy('due_at')
            ->limit($limit)
            ->get(['title', 'due_at', 'status'])
            ->map(fn (WorkflowRequest $request) => [
                'title' => $request->title,
                'deadline' => $request->due_at,
                'priority' => $request->due_at->diffInHours(now()) <= 24 ? 'high' : 'normal',
            ])
            ->all();
    }

    private function stat(int $value, string $description): array
    {
        return [
            'value' => $value,
            'description' => $description,
        ];
    }

    private function roleCard(string $label, int $value, string $description): array
    {
        return compact('label', 'value', 'description');
    }

    private function getIconForAction(?string $actionType): string
    {
        return match ($actionType) {
            'created' => 'plus-circle',
            'updated' => 'edit',
            'deleted' => 'trash',
            'approved' => 'check-circle',
            'rejected' => 'x-circle',
            default => 'activity',
        };
    }
}
