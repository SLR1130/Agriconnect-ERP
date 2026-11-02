<?php

namespace App\Http\Controllers;

use App\Models\AssistanceApplication;
use App\Models\AssistanceClaimTicket;
use App\Services\AssistanceClaimTicketService;
use App\Services\ClaimTicketFeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AASMSClaimTicketController extends Controller
{
    public function index(Request $request, ClaimTicketFeatureGate $feature): View
    {
        $this->authorizeTicketAccess($request, $feature, 'modules.aasms.claim_ticket_view');
        $user = $request->user();
        $canIssue = $user->canErp('modules.aasms.claim_ticket_issue');

        $eligibleApplications = AssistanceApplication::query()
            ->with(['program', 'farmer.user', 'approvedSupplyItem'])
            ->where('status', 'approved')
            ->whereNotNull('allocation_confirmed_at')
            ->whereNotNull('approved_supply_item_id')
            ->where('approved_quantity', '>', 0)
            ->whereHas('farmer', fn ($query) => $query->where('registration_status', 'verified')->whereHas('user'))
            ->whereHas('approvedSupplyItem', fn ($query) => $query->where('status', 'active'))
            ->whereDoesntHave('distributions')
            ->whereDoesntHave('claimTickets')
            ->latest('id')
            ->paginate(15, ['*'], 'eligible_page')
            ->withQueryString();

        $tickets = AssistanceClaimTicket::query()
            ->with(['application.program', 'application.farmer.user', 'approvedSupplyItem', 'issuer'])
            ->latest('issued_at')
            ->paginate(15, ['*'], 'ticket_page')
            ->withQueryString();

        return view('modules.aasms.claim-tickets.index', [
            'eligibleApplications' => $eligibleApplications,
            'tickets' => $tickets,
            'canIssue' => $canIssue,
            'canVerify' => $user->canErp('modules.aasms.claim_ticket_verify'),
            'canRevoke' => $user->canErp('modules.aasms.claim_ticket_revoke'),
            'canReissue' => $user->canErp('modules.aasms.claim_ticket_reissue'),
            'canHistory' => $user->canErp('modules.aasms.claim_ticket_history'),
        ]);
    }

    public function verifyForm(Request $request, ClaimTicketFeatureGate $feature): View
    {
        $this->authorizeTicketAccess($request, $feature, 'modules.aasms.claim_ticket_verify');
        $verification = $request->session()->get('claim_ticket_verification');
        $verificationTicket = is_array($verification) && isset($verification['ticket_id'])
            ? AssistanceClaimTicket::query()->with(['application.program', 'application.farmer.user', 'approvedSupplyItem'])->find($verification['ticket_id'])
            : null;

        return view('modules.aasms.claim-tickets.verify', [
            'verification' => $verification,
            'verificationTicket' => $verificationTicket,
        ]);
    }

    public function show(Request $request, AssistanceClaimTicket $ticket, ClaimTicketFeatureGate $feature): View
    {
        $this->authorizeTicketAccess($request, $feature, 'modules.aasms.claim_ticket_view');
        $ticket->load(['application.program', 'application.farmer.user', 'approvedSupplyItem', 'issuer', 'revoker', 'supersedes', 'successor']);
        $canHistory = $request->user()->canErp('modules.aasms.claim_ticket_history');
        if ($canHistory) {
            $ticket->setRelation('events', $ticket->events()->with('actor')->paginate(30));
        }

        $verification = $request->session()->get('claim_ticket_verification');
        if (($verification['ticket_id'] ?? null) !== $ticket->id) {
            $verification = null;
        }

        return view('modules.aasms.claim-tickets.show', [
            'ticket' => $ticket,
            'canRevoke' => $request->user()->canErp('modules.aasms.claim_ticket_revoke'),
            'canReissue' => $request->user()->canErp('modules.aasms.claim_ticket_reissue'),
            'canHistory' => $canHistory,
            'verification' => $verification,
        ]);
    }

    public function issue(Request $request, AssistanceApplication $application, ClaimTicketFeatureGate $feature, AssistanceClaimTicketService $tickets): RedirectResponse
    {
        $this->authorizeTicketAccess($request, $feature, 'modules.aasms.claim_ticket_issue');
        $ticket = $tickets->issue($application, $request->user());

        return redirect()->route('modules.aasms.claim-tickets.show', $ticket)
            ->with('status', 'Claim ticket issued. No AISMS stock was reserved or deducted.')
            ->with('claim_ticket_verification', null);
    }

    public function verify(Request $request, ClaimTicketFeatureGate $feature, AssistanceClaimTicketService $tickets): RedirectResponse
    {
        $this->authorizeTicketAccess($request, $feature, 'modules.aasms.claim_ticket_verify');
        // Accept the verification secret only in the POST body, never a query
        // string where it could persist in browser history or access logs.
        $validator = Validator::make($request->request->all(), [
            'ticket_input' => ['required', 'string', 'max:256'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except('ticket_input'));
        }

        $verificationInput = (string) $validator->validated()['ticket_input'];
        // Keep the secret out of old input, request dumps and exception detail.
        $request->request->remove('ticket_input');
        $result = $tickets->verify($verificationInput, $request->user());
        unset($verificationInput);
        $request->session()->flash('claim_ticket_verification', [
            'outcome' => $result['outcome'],
            'method' => $result['method'],
            'ticket_id' => $result['ticket']?->id,
            'message' => $result['message'],
        ]);
        $request->session()->flash('status', $result['message']);

        return redirect()->route('modules.aasms.claim-tickets.verify-form');
    }

    public function revoke(Request $request, AssistanceClaimTicket $ticket, ClaimTicketFeatureGate $feature, AssistanceClaimTicketService $tickets): RedirectResponse
    {
        $this->authorizeTicketAccess($request, $feature, 'modules.aasms.claim_ticket_revoke');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $updated = $tickets->revoke($ticket, $request->user(), $data['reason']);

        return back()->with('status', $updated->status === AssistanceClaimTicket::STATUS_REVOKED
            ? 'Claim ticket revoked. Its stored QR token was invalidated; no inventory changed.'
            : 'This ticket had expired. Its active slot and stored QR token were cleared; no inventory changed.');
    }

    public function reissue(Request $request, AssistanceApplication $application, AssistanceClaimTicket $ticket, ClaimTicketFeatureGate $feature, AssistanceClaimTicketService $tickets): RedirectResponse
    {
        $this->authorizeTicketAccess($request, $feature, 'modules.aasms.claim_ticket_reissue');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $replacement = $tickets->reissue($application, $ticket, $request->user(), $data['reason']);

        return redirect()->route('modules.aasms.claim-tickets.show', $replacement)
            ->with('status', 'A replacement claim ticket was issued. The previous ticket is terminal and cannot be verified. No inventory changed.');
    }

    private function authorizeTicketAccess(Request $request, ClaimTicketFeatureGate $feature, string $permission): void
    {
        $feature->requireEnabled();
        abort_unless($request->user()?->canModuleAction('aasms', 'view'), 403);
        abort_unless($request->user()?->canErp($permission), 403);
    }
}
