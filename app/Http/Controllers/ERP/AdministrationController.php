<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AgriculturalSeason;
use App\Models\Module;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserModuleAccess;
use App\Services\AgriculturalAnalyticsService;
use App\Services\ErpAuthorizationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdministrationController extends Controller
{
    public function __construct(private readonly ErpAuthorizationService $authorization) {}

    public function users(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        $modules = Module::query()->orderBy('sort_order')->orderBy('name')->get();
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $query = User::query()
            ->with(['roles.moduleAccess.module'])
            ->with('moduleAssignments.module')
            ->when($search, fn ($query) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true)->whereNull('suspended_at'))
            ->when($status === 'inactive', fn ($query) => $query->where(fn ($nested) => $nested->where('is_active', false)->orWhereNotNull('suspended_at')))
            ->orderBy('name');
        $rows = $query
            ->get()
            ->map(fn (User $user) => $this->accountSummary($user, $modules))
            ->values()
            ->all();

        return view('erp.users.index', [
            'rows' => $rows,
            'filters' => ['search' => $search, 'status' => $status],
            'staffRoles' => $this->staffRoleLabels(),
        ]);
    }

    public function createUser(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        return view('erp.users.form', [
            'user' => null,
            'staffRoles' => $this->staffRoleLabels(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys($this->staffRoleLabels()))],
        ]);
        $role = $this->staffRole($data['role']);

        $user = DB::transaction(function () use ($data, $role) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(80)),
                'is_active' => false,
                'suspended_at' => null,
                'activated_at' => null,
            ]);
            $user->roles()->attach($role->id);
            if ($role->slug === 'module_personnel') {
                $this->enableExplicitModulePersonnelAssignments($user);
            }

            return $user->fresh(['roles']);
        });

        $token = Password::broker()->createToken($user);
        ActivityLog::log('created', 'Account '.$user->email.' created for '.$role->name.'. Password setup is pending.', (string) $user->id, $request->user()->id, 'erp', null, [
            'user_id' => $user->id,
            'role' => $role->slug,
            'status' => 'inactive_until_password_setup',
        ]);

        return redirect()->route('erp.users.show', $user)
            ->with('status', 'Account created inactive. Share the one-time password setup link with the account owner.')
            ->with('setup_url', route('account.setup.create', ['token' => $token, 'email' => $user->email]));
    }

    public function showUser(Request $request, User $user): View
    {
        $this->authorizeSuperAdmin($request);

        $user->load(['roles.moduleAccess.module', 'moduleAssignments.module']);
        $modules = Module::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('erp.users.show', [
            'account' => $this->accountSummary($user, $modules),
            'activity' => ActivityLog::query()
                ->where('module', 'erp')
                ->where('action_target', (string) $user->id)
                ->with('causer')
                ->latest()
                ->limit(25)
                ->get(),
            'staffRoles' => $this->staffRoleLabels(),
        ]);
    }

    public function editUser(Request $request, User $user): View
    {
        $this->authorizeSuperAdmin($request);

        return view('erp.users.form', [
            'user' => $user->load(['roles', 'moduleAssignments.module']),
            'staffRoles' => $this->staffRoleLabels(),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);
        $oldValues = $user->only(['name', 'email']);
        $user->update($data);

        ActivityLog::log('updated', 'Account '.$user->email.' profile updated.', (string) $user->id, $request->user()->id, 'erp', $oldValues, $user->only(['name', 'email']));

        return redirect()->route('erp.users.show', $user)->with('status', 'Account profile updated.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        if ($user->roles()->where('slug', 'super_admin')->exists()) {
            throw ValidationException::withMessages(['role' => 'Super Administrator role assignments are protected and cannot be changed here.']);
        }
        if ($user->roles()->where('slug', 'farmer')->exists()) {
            throw ValidationException::withMessages(['role' => 'Farmer identities use the farmer registration workflow and cannot be converted from this staff assignment form.']);
        }

        $data = $request->validate(['role' => ['required', Rule::in(array_keys($this->staffRoleLabels()))]]);
        $role = $this->staffRole($data['role']);
        $oldRoles = $user->roles()->pluck('slug')->values()->all();

        DB::transaction(function () use ($user, $role) {
            $user->roles()->sync([$role->id]);
            if ($role->slug !== 'module_personnel') {
                $user->moduleAssignments()->delete();
            } else {
                $this->enableExplicitModulePersonnelAssignments($user);
            }
        });

        ActivityLog::log('updated', 'Account '.$user->email.' role assignment updated.', (string) $user->id, $request->user()->id, 'erp', ['roles' => $oldRoles], ['roles' => [$role->slug]]);

        return redirect()->route('erp.users.show', $user)->with('status', 'Role assignment updated.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $active = (bool) $data['is_active'];

        if (! $active && $user->isSuperAdministrator() && $this->activeSuperAdministratorCount() <= 1) {
            throw ValidationException::withMessages(['is_active' => 'The final active Super Administrator cannot be deactivated.']);
        }
        if ($active && ! $user->roles()->where('roles.is_active', true)->exists()) {
            throw ValidationException::withMessages(['is_active' => 'An account needs an active role before it can be activated.']);
        }

        $oldValues = $user->only(['is_active', 'suspended_at', 'activated_at']);
        $user->update([
            'is_active' => $active,
            'suspended_at' => $active ? null : now(),
            'activated_at' => $active ? ($user->activated_at ?: now()) : $user->activated_at,
        ]);
        if (! $active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        ActivityLog::log($active ? 'activated' : 'deactivated', 'Account '.$user->email.' '.($active ? 'activated.' : 'deactivated; active sessions revoked.'), (string) $user->id, $request->user()->id, 'erp', $oldValues, $user->only(['is_active', 'suspended_at', 'activated_at']));

        return redirect()->route('erp.users.show', $user)->with('status', $active ? 'Account activated.' : 'Account deactivated and active sessions revoked.');
    }

    public function updateModuleAccess(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        if (! $user->roles()->where('slug', 'module_personnel')->where('roles.is_active', true)->exists()) {
            throw ValidationException::withMessages(['assignments' => 'Per-account agricultural assignments are available only for active Module Personnel roles.']);
        }

        $assignments = $request->input('assignments', []);
        if (! is_array($assignments)) {
            throw ValidationException::withMessages(['assignments' => 'Assignments must be submitted as a structured list.']);
        }

        $allowedActions = $this->authorization->delegatedModuleActions();
        $modules = Module::query()->whereIn('slug', array_keys($allowedActions))->get()->keyBy('slug');
        $clean = [];
        foreach ($assignments as $slug => $permissions) {
            if (! isset($modules[$slug]) || ! is_array($permissions)) {
                throw ValidationException::withMessages(['assignments' => 'One or more requested module assignments is not recognized.']);
            }
            $permissions = array_values(array_unique(array_filter($permissions, 'is_string')));
            $invalid = array_values(array_diff($permissions, $allowedActions[$slug] ?? []));
            if ($invalid) {
                throw ValidationException::withMessages(['assignments' => 'The requested '.$slug.' action(s) are not delegable: '.implode(', ', $invalid).'. Approval and system administration remain restricted.']);
            }
            if ($permissions) {
                $clean[$slug] = $permissions;
            }
        }

        $oldAssignments = $user->moduleAssignments()->with('module')->get()->mapWithKeys(fn (UserModuleAccess $access) => [$access->module?->slug => $access->permissions])->all();
        DB::transaction(function () use ($user, $clean, $modules) {
            $user->moduleAssignments()->delete();
            foreach ($clean as $slug => $permissions) {
                UserModuleAccess::create([
                    'user_id' => $user->id,
                    'module_id' => $modules[$slug]->id,
                    'permissions' => $permissions,
                ]);
            }
            if ($clean === []) {
                $this->enableExplicitModulePersonnelAssignments($user, $modules['aisms'] ?? null);
            }
        });

        ActivityLog::log('updated', 'Delegated module assignments for '.$user->email.' updated.', (string) $user->id, $request->user()->id, 'erp', $oldAssignments, $clean);

        return redirect()->route('erp.users.show', $user)->with('status', 'Delegated module assignments saved and effective permissions recalculated.');
    }

    public function issuePasswordSetup(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $token = Password::broker()->createToken($user);
        ActivityLog::log('updated', 'Password setup link issued for '.$user->email.'. Existing credentials were not changed.', (string) $user->id, $request->user()->id, 'erp');

        return redirect()->route('erp.users.show', $user)
            ->with('status', 'A new one-time password setup link was issued. Existing credentials remain unchanged until it is used.')
            ->with('setup_url', route('account.setup.create', ['token' => $token, 'email' => $user->email]));
    }

    public function roles(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        $modules = Module::query()->orderBy('sort_order')->orderBy('name')->get();
        $rows = Role::query()
            ->with(['users', 'moduleAccess.module'])
            ->withCount(['users', 'moduleAccess'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->roleSummary($role, $modules))
            ->values()
            ->all();

        return view('erp.roles.index', [
            'rows' => $rows,
            'permissionCatalog' => $this->globalPermissionLabels(),
            'moduleActionCatalog' => $this->moduleActionLabels(),
        ]);
    }

    public function updateRoleStatus(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $active = (bool) $data['is_active'];

        if (! $active && $role->slug === 'super_admin') {
            throw ValidationException::withMessages(['is_active' => 'The Super Administrator policy cannot be deactivated from this interface.']);
        }

        $oldValues = ['is_active' => (bool) $role->is_active];
        $role->update(['is_active' => $active]);

        ActivityLog::log(
            $active ? 'activated' : 'deactivated',
            'Role '.$role->name.' policy '.($active ? 'activated.' : 'deactivated.'),
            'role:'.$role->id,
            $request->user()->id,
            'erp',
            $oldValues,
            ['is_active' => $active],
        );

        return back()->with('status', 'Role policy '.($active ? 'activated.' : 'deactivated; effective access revoked.'));
    }

    public function modules(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        return view('erp.modules.index', [
            'modules' => Module::query()->withCount('moduleAccess')->orderBy('sort_order')->get(),
        ]);
    }

    public function updateModule(Request $request, Module $module): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        $oldValues = $module->only(['is_enabled', 'is_active']);
        $module->update($validated);

        ActivityLog::log('updated', 'Module '.$module->name.' settings updated', $module->slug, $request->user()->id, $module->slug, $oldValues, $module->only(['is_enabled', 'is_active']));

        return back()->with('status', 'Module settings updated.');
    }

    public function reports(Request $request, AgriculturalAnalyticsService $analytics): View|StreamedResponse
    {
        $availableReports = $analytics->availableReports($request->user());
        abort_if($availableReports === [], 403);
        $reportKey = (string) $request->input('report', array_key_first($availableReports));
        abort_unless(array_key_exists($reportKey, $availableReports), 403);
        $statusOptions = match ($reportKey) {
            'ffrms' => ['pending', 'verified', 'rejected', 'returned'],
            'aasms' => ['submitted', 'approved', 'rejected', 'returned', 'completed', 'distributed'],
            'aeams' => ['available', 'issued', 'maintenance', 'archived', 'pending', 'approved', 'rejected', 'cancelled', 'returned'],
            'procurement' => ['submitted', 'under_review', 'approved', 'rejected', 'draft', 'ordered', 'partially_received', 'received', 'cancelled'],
            default => [],
        };
        $filters = $request->validate([
            'report' => ['nullable', Rule::in(array_keys($availableReports))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'season' => ['nullable', 'integer', 'exists:agricultural_seasons,id'],
            'status' => ['nullable', Rule::in($statusOptions)],
            'format' => ['nullable', Rule::in(['csv'])],
        ]);
        $to = CarbonImmutable::parse($filters['to'] ?? today()->toDateString())->startOfDay();
        $from = CarbonImmutable::parse($filters['from'] ?? $to->startOfMonth()->toDateString())->startOfDay();
        $seasonId = isset($filters['season']) && $request->user()->canModuleAction('apms', 'view')
            ? (int) $filters['season']
            : null;
        $report = $analytics->report($request->user(), $reportKey, $from, $to, $seasonId, $filters['status'] ?? null);

        if (($filters['format'] ?? null) === 'csv') {
            $module = $report['module'];
            abort_unless($request->user()->canModuleAction($module, 'export'), 403);
            $filename = 'agriconnect-'.$reportKey.'-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';
            $generatedAt = now()->format('Y-m-d H:i:s T');
            $safe = static function (mixed $value): string {
                $text = (string) ($value ?? '');

                return preg_match('/^[\x00-\x20]*[=+\-@]/u', $text) ? "'".$text : $text;
            };

            return response()->streamDownload(function () use ($report, $filters, $from, $to, $generatedAt, $safe): void {
                $stream = fopen('php://output', 'w');
                fputcsv($stream, ['Report', $report['title']]);
                fputcsv($stream, ['Date range', $from->toDateString(), $to->toDateString()]);
                fputcsv($stream, ['Agricultural season ID', $filters['season'] ?? 'All']);
                fputcsv($stream, ['Status filter', $filters['status'] ?? 'All']);
                fputcsv($stream, ['Generated at', $generatedAt]);
                fputcsv($stream, []);
                fputcsv($stream, $report['columns']);
                foreach ($report['rows'] as $row) {
                    fputcsv($stream, array_map($safe, $row));
                }
                fclose($stream);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('erp.reports.index', [
            'reports' => $availableReports,
            'report' => $report,
            'reportKey' => $reportKey,
            'statusOptions' => $statusOptions,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'season' => $seasonId,
                'status' => $filters['status'] ?? '',
            ],
            'seasons' => $request->user()->canModuleAction('apms', 'view')
                ? AgriculturalSeason::query()->orderByDesc('starts_on')->get(['id', 'name'])
                : collect(),
            'canExport' => $request->user()->canModuleAction($report['module'], 'export'),
            'generatedAt' => now(),
        ]);
    }

    public function activityLogs(Request $request): View
    {
        abort_unless($request->user()?->canErp('activity_logs.view'), 403);

        $rows = ActivityLog::query()->with('causer')->latest()->get()->map(fn (ActivityLog $activity) => [
            'action' => $activity->action_type,
            'description' => $activity->description,
            'user' => $activity->causer?->name ?? 'System',
            'module' => $activity->module ?: 'ERP Core',
            'created_at' => $activity->created_at?->format('M j, Y g:i A'),
        ])->all();

        return $this->table('Activity Logs', 'Audit records captured by ERP Core and its modules.', [
            ['label' => 'Action', 'key' => 'action'],
            ['label' => 'Description', 'key' => 'description'],
            ['label' => 'User', 'key' => 'user'],
            ['label' => 'Module', 'key' => 'module'],
            ['label' => 'When', 'key' => 'created_at'],
        ], $rows);
    }

    public function settings(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        $rows = SystemSetting::query()->orderBy('group')->orderBy('key')->get()->map(fn (SystemSetting $setting) => [
            'key' => $setting->key,
            'group' => $setting->group,
            'value' => $setting->value === null ? 'No value' : json_encode($setting->value),
            'visibility' => $setting->is_public ? 'Public' : 'Private',
        ])->all();

        return $this->table('System Settings', 'Configuration values stored in the shared ERP database.', [
            ['label' => 'Key', 'key' => 'key'],
            ['label' => 'Group', 'key' => 'group'],
            ['label' => 'Value', 'key' => 'value'],
            ['label' => 'Visibility', 'key' => 'visibility'],
        ], $rows);
    }

    private function table(string $title, string $description, array $columns, array $rows): View
    {
        return view('erp.index', compact('title', 'description', 'columns', 'rows'));
    }

    private function accountSummary(User $user, $modules): array
    {
        $effectiveModules = $this->effectiveModulesForUser($user, $modules);
        $effectiveGlobal = $this->effectiveGlobalForUser($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->isActive() ? 'Active' : 'Inactive',
            'status_tone' => $user->isActive() ? 'success' : 'warning',
            'created_at' => $user->created_at?->format('M j, Y') ?: 'Unknown',
            'roles' => $user->roles->map(fn (Role $role) => [
                'name' => $role->name,
                'slug' => $role->slug,
                'active' => (bool) $role->is_active,
            ])->values()->all(),
            'effective_global' => $effectiveGlobal,
            'effective_modules' => $effectiveModules,
            'effective_admin' => $this->effectiveAdminForUser($user),
            'can_delegate' => $user->hasRole('module_personnel'),
            'delegated_modules' => $this->delegatedModulesForUser($user, $modules),
            'stored_roles' => $user->roles->map(fn (Role $role) => [
                'name' => $role->name,
                'slug' => $role->slug,
                'active' => (bool) $role->is_active,
                'permissions' => array_values($role->permissions ?? []),
                'module_access' => $role->moduleAccess->map(fn ($access) => [
                    'name' => $access->module?->name ?: 'Unresolved module',
                    'slug' => $access->module?->slug ?: 'unresolved',
                    'permissions' => array_values($access->permissions ?? []),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    private function delegatedModulesForUser(User $user, $modules): array
    {
        $allowedActions = $this->authorization->delegatedModuleActions();
        $assigned = $user->moduleAssignments->keyBy(fn (UserModuleAccess $access) => $access->module?->slug);

        return $modules
            ->filter(fn (Module $module) => isset($allowedActions[$module->slug]))
            ->map(fn (Module $module) => [
                'name' => $module->name,
                'slug' => $module->slug,
                'actions' => $allowedActions[$module->slug],
                'selected' => array_values($assigned->get($module->slug)?->permissions ?? []),
            ])
            ->values()
            ->all();
    }

    private function enableExplicitModulePersonnelAssignments(User $user, ?Module $module = null): void
    {
        $module ??= Module::query()->where('slug', 'aisms')->first();

        if (! $module) {
            return;
        }

        UserModuleAccess::updateOrCreate(
            ['user_id' => $user->id, 'module_id' => $module->id],
            ['permissions' => []],
        );
    }

    private function roleSummary(Role $role, $modules): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'active' => (bool) $role->is_active,
            'default' => (bool) $role->is_default,
            'users_count' => $role->users_count,
            'module_count' => $role->module_access_count,
            'users' => $role->users->pluck('name')->values()->all(),
            'effective_global' => $this->effectiveGlobalForRole($role),
            'effective_modules' => $this->effectiveModulesForRole($role, $modules),
            'stored_permissions' => array_values($role->permissions ?? []),
            'stored_module_access' => $role->moduleAccess->map(fn ($access) => [
                'name' => $access->module?->name ?: 'Unresolved module',
                'slug' => $access->module?->slug ?: 'unresolved',
                'permissions' => array_values($access->permissions ?? []),
            ])->values()->all(),
        ];
    }

    private function effectiveGlobalForUser(User $user): array
    {
        return collect($this->globalPermissionLabels())
            ->filter(fn (string $label, string $permission) => $this->authorization->allows($user, $permission))
            ->map(fn (string $label, string $permission) => ['permission' => $permission, 'label' => $label])
            ->values()
            ->all();
    }

    private function effectiveGlobalForRole(Role $role): array
    {
        return collect($this->globalPermissionLabels())
            ->filter(fn (string $label, string $permission) => $this->authorization->roleAllows($role, $permission))
            ->map(fn (string $label, string $permission) => ['permission' => $permission, 'label' => $label])
            ->values()
            ->all();
    }

    private function effectiveAdminForUser(User $user): array
    {
        return collect($this->adminCapabilityLabels())
            ->map(fn (string $label, string $permission) => [
                'permission' => $permission,
                'label' => $label,
                'granted' => $this->authorization->allows($user, $permission),
            ])
            ->values()
            ->all();
    }

    private function effectiveModulesForUser(User $user, $modules): array
    {
        return $modules->map(function (Module $module) use ($user) {
            $actions = collect($this->moduleActionLabels())
                ->filter(fn (string $label, string $action) => $this->authorization->canModule($user, $module->slug, $action))
                ->map(fn (string $label, string $action) => ['action' => $action, 'label' => $label])
                ->values()
                ->all();

            return $actions ? ['name' => $module->name, 'slug' => $module->slug, 'actions' => $actions] : null;
        })->filter()->values()->all();
    }

    private function effectiveModulesForRole(Role $role, $modules): array
    {
        return $modules->map(function (Module $module) use ($role) {
            $actions = collect($this->moduleActionLabels())
                ->filter(fn (string $label, string $action) => $this->authorization->roleAllows($role, 'modules.'.$module->slug.'.'.$action))
                ->map(fn (string $label, string $action) => ['action' => $action, 'label' => $label])
                ->values()
                ->all();

            return $actions ? ['name' => $module->name, 'slug' => $module->slug, 'actions' => $actions] : null;
        })->filter()->values()->all();
    }

    private function globalPermissionLabels(): array
    {
        return [
            'dashboard.view' => 'Shared dashboard',
            'modules.view' => 'Module directory',
            'reports.view' => 'View reports',
            'reports.generate' => 'Generate reports',
            'activity_logs.view' => 'View activity logs',
            'farmer.verify' => 'Verify farmer records',
            'farmer.profile.manage' => 'Manage own farmer profile',
            'farmer.farms.manage' => 'Manage own farms',
            'farmer.assistance.apply' => 'Apply for assistance',
            'farmer.equipment.request' => 'Request equipment',
        ];
    }

    private function adminCapabilityLabels(): array
    {
        return [
            'users.manage' => 'User management',
            'roles.manage' => 'Role and permission administration',
            'modules.manage' => 'Module management',
            'settings.manage' => 'System settings',
        ];
    }

    private function moduleActionLabels(): array
    {
        return [
            'view' => 'View',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'review' => 'Review',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'receive' => 'Receive',
            'release' => 'Release',
            'adjust' => 'Adjust',
            'distribute' => 'Distribute',
            'issue' => 'Issue',
            'return' => 'Return',
            'maintenance' => 'Maintenance',
            'export' => 'Export',
            'manage' => 'Manage',
        ];
    }

    private function staffRoleLabels(): array
    {
        return [
            'mao_admin' => 'MAO Administrator',
            'municipal_agriculturist' => 'Municipal Agriculturist',
            'module_personnel' => 'Module Personnel',
            'authorized_viewer' => 'Auditor / Authorized Viewer',
        ];
    }

    private function staffRole(string $slug): Role
    {
        return Role::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->whereIn('slug', array_keys($this->staffRoleLabels()))
            ->firstOrFail();
    }

    private function activeSuperAdministratorCount(): int
    {
        return User::query()
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin')->where('roles.is_active', true))
            ->count();
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator(), 403);
    }
}
