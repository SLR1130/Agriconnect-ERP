<?php

namespace App\Http\Controllers;

use App\Services\SetupStatusService;
use Illuminate\View\View;

class SetupStatusController extends Controller
{
    public function home(): View
    {
        return view('home');
    }

    public function show(SetupStatusService $statusService): View
    {
        abort_unless(request()->user()?->isSuperAdministrator(), 403);

        return view('setup-test', [
            'status' => $statusService->summary(true),
        ]);
    }
}
