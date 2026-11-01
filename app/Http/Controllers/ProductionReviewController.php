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
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($filters['search'] ?? '');
        $statusCounts = ProductionSubmission::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('apms.submissions', [
            'submissions' => ProductionSubmission::query()->with(['farmer.user', 'farm'])
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($filtered) use ($search): void {
                        $filtered->where('category', 'like', '%'.$search.'%')
                            ->orWhereHas('farmer.user', fn ($users) => $users->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                            ->orWhereHas('farm', fn ($farms) => $farms->where('name', 'like', '%'.$search.'%')->orWhere('barangay', 'like', '%'.$search.'%'));
                    });
                })
                ->latest()->paginate(20)->withQueryString(),
            'status' => $status,
            'search' => $search,
            'statusCounts' => $statusCounts,
            'canReview' => $request->user()->canModuleAction('apms', 'update'),
        ]);
    }

    public function show(Request $request, ProductionSubmission $submission): View
    {
        abort_unless($request->user()->canModuleAction('apms', 'view'), 403);

        return view('apms.submission-detail', [
            'submission' => $submission->load(['farmer.user', 'farm', 'history' => fn ($query) => $query->with('actor')->orderBy('created_at')->orderBy('id'), 'reviewer']),
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

        $queueContext = [];
        $queueStatus = $request->input('queue_status');
        $queueSearch = $request->input('queue_search');
        if (is_string($queueStatus) && in_array($queueStatus, ['pending', 'approved', 'returned', 'rejected'], true)) {
            $queueContext['status'] = $queueStatus;
        }
        if (is_string($queueSearch) && trim($queueSearch) !== '') {
            $queueContext['search'] = mb_substr(trim($queueSearch), 0, 100);
        }

        return redirect()->route('modules.apms.submissions.show', ['submission' => $submission] + $queueContext)
            ->with('status', 'Production submission reviewed.');
    }
}
