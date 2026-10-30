<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\AgriculturalAnalyticsService;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly AgriculturalAnalyticsService $analytics,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->canErp('dashboard.view'), 403);

        return $this->managementDashboard($request);
    }

    public function overview()
    {
        return redirect()->route('dashboard');
    }

    public function admin(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdministrator(), 403);

        return $this->managementDashboard($request);
    }

    public function mao(Request $request): View
    {
        abort_unless($request->user()?->canErp('dashboard.view') && ! $request->user()->hasRole('farmer'), 403);

        return $this->managementDashboard($request);
    }

    private function managementDashboard(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'season' => ['nullable', 'integer', 'exists:agricultural_seasons,id'],
        ]);
        $to = CarbonImmutable::parse($filters['to'] ?? today()->toDateString())->startOfDay();
        $from = CarbonImmutable::parse($filters['from'] ?? $to->startOfMonth()->toDateString())->startOfDay();
        $user = $request->user();
        $modules = array_filter(
            $this->dashboardService->getModuleStatus(),
            fn (array $module, string $slug) => $user->canModuleAction($slug, 'view'),
            ARRAY_FILTER_USE_BOTH,
        );
        $reportCount = count($this->analytics->availableReports($user));

        return view('dashboard.management', [
            'roleContext' => $this->dashboardService->getRoleContext($user),
            'databaseLabel' => $this->dashboardService->getDatabaseLabel(),
            'analytics' => $this->analytics->dashboard($user, $from, $to, isset($filters['season']) ? (int) $filters['season'] : null),
            'modules' => $modules,
            'systemStats' => $user->isSuperAdministrator() ? $this->dashboardService->getSystemStats() : [],
            'health' => $user->isSuperAdministrator() ? $this->dashboardService->getSystemHealth() : null,
            'activities' => $this->dashboardService->getRecentActivities($user),
            'deadlines' => $this->dashboardService->getUpcomingDeadlines($user),
            'canViewReports' => $reportCount > 0,
            'reportCount' => $reportCount,
            'dashboardRoute' => $request->route()?->getName() ?: 'dashboard',
        ]);
    }
}
