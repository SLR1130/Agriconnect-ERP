<?php

namespace App\Http\Controllers;

use App\Models\ProductionSubmission;
use App\Services\ProductionSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionReviewController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canModuleAction('apms', 'view'), 403);
        $status = $request->string('status')->toString();
        abort_unless(in_array($status, ['', 'pending', 'approved', 'returned', 'rejected'], true), 404);

        return view('apms.submissions', [
            'submissions' => ProductionSubmission::query()->with(['farmer.user', 'farm'])->when($status !== '', fn ($query) => $query->where('status', $status))->latest()->paginate(20)->withQueryString(),
            'status' => $status,
            'canReview' => $request->user()->canModuleAction('apms', 'update'),
        ]);
    }

    public function show(Request $request, ProductionSubmission $submission): View
    {
        abort_unless($request->user()->canModuleAction('apms', 'view'), 403);

        return view('apms.submission-detail', [
            'submission' => $submission->load(['farmer.user', 'farm', 'history.actor', 'reviewer']),
            'canReview' => $request->user()->canModuleAction('apms', 'update'),
        ]);
    }

    public function review(Request $request, ProductionSubmission $submission, ProductionSubmissionService $service): RedirectResponse
    {
        abort_unless($request->user()->canModuleAction('apms', 'update'), 403);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,returned,rejected'],
            'review_notes' => ['required_if:decision,returned,rejected', 'nullable', 'string', 'max:2000'],
        ]);
        $service->review($submission, $request->user(), $data['decision'], $data['review_notes'] ?? null);

        return redirect()->route('modules.apms.submissions.show', $submission)->with('status', 'Production submission reviewed.');
    }
}
