<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index(Request $request)
    {
        abort_unless($this->canOpenStaffOverview($request->user()), 403);

        $stats = $this->dashboardService->getDashboardStats();
        $activities = $this->dashboardService->getRecentActivities();
        $modules = $this->dashboardService->getModuleStatus();
        $health = $this->dashboardService->getSystemHealth();
        $deadlines = $this->dashboardService->getUpcomingDeadlines();

        return view('dashboard.index', [
            'stats' => $stats,
            'activities' => $activities,
            'modules' => $modules,
            'health' => $health,
            'deadlines' => $deadlines,
            'roleContext' => $this->dashboardService->getRoleContext($request->user()),
        ]);
    }

    public function overview()
    {
        return redirect()->route('dashboard');
    }

    public function admin(Request $request)
    {
        abort_unless($request->user()->isSuperAdministrator(), 403);
        return view('dashboard.admin', $this->data());
    }

    public function mao(Request $request)
    {
        abort_unless($request->user()->canErp('dashboard.view') && ! $request->user()->hasRole('farmer'), 403);
        return view('dashboard.mao', $this->data($request->user()));
    }

    private function data($user = null): array
    {
        return ['stats'=>$this->dashboardService->getDashboardStats(),'activities'=>$this->dashboardService->getRecentActivities(),'modules'=>$this->dashboardService->getModuleStatus(),'health'=>$this->dashboardService->getSystemHealth(),'deadlines'=>$this->dashboardService->getUpcomingDeadlines(),'roleContext'=>$user ? $this->dashboardService->getRoleContext($user) : null];
    }

    private function canOpenStaffOverview($user): bool
    {
        return $user && $user->canErp('dashboard.view');
    }
}
