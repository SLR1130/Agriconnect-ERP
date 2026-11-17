<?php

namespace App\Http\Controllers;

use App\Models\AssistanceApplication;
use App\Models\AssistanceClaimTicket;
use App\Models\AssistanceHandoverAcknowledgment;
use App\Models\AssistanceIdentityConfirmation;
use App\Models\SupplyBatch;
use App\Services\AssistanceHandoverFeatureGate;
use App\Services\AssistanceHandoverService;
use App\Services\AssistanceClaimTicketService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AASMSHandoverController extends Controller
{
    public function confirmIdentity(
        Request $request,
        AssistanceClaimTicket $ticket,
        AssistanceHandoverFeatureGate $feature,
        AssistanceHandoverService $handover,
    ): RedirectResponse {
        $feature->requireEnabled();
        $data = $request->validate([
            'verification_event_id' => ['required', 'integer', 'min:1'],
            'outcome' => ['required', 'in:confirmed,not_confirmed'],
        ]);
        $confirmation = $handover->confirmSyntheticIdentity(
            $ticket,
            (int) $data['verification_event_id'],
            $data['outcome'],
            $request->user(),
        );

        $request->session()->flash('claim_ticket_verification', [
            'outcome' => 'valid',
            'method' => $confirmation->verificationEvent->verification_method,
            'ticket_id' => $ticket->id,
            'verification_event_id' => $confirmation->verification_event_id,
            'identity_confirmation_id' => $confirmation->id,
            'message' => $confirmation->outcome === 'confirmed'
                ? 'Synthetic QA identity confirmation recorded. This is not legal identification, consent, a biometric check, or a farmer signature.'
                : 'Synthetic QA identity did not match. No physical handover is authorized.',
        ]);

        if ($confirmation->outcome === 'confirmed'
            && $request->user()->canModuleAction('aasms', 'distribute')
            && $request->user()->canModuleAction('aisms', 'release')
            && $request->user()->canErp('modules.aasms.handover_distribute')) {
            return redirect()->route('modules.aasms.handovers.review', [
                'ticket' => $ticket,
                'confirmation' => $confirmation->id,
            ])->with('status', 'Synthetic QA identity confirmation recorded. Review the allocation and selected batch before any QA handover.');
        }

        return redirect()->route('modules.aasms.claim-tickets.verify-form')
            ->with('status', $confirmation->outcome === 'confirmed'
                ? 'Synthetic confirmation is recorded. A staff member with both distribution permissions and the exact handover grant must continue. No stock has changed.'
                : 'Identity was not confirmed in the synthetic QA scenario. No stock was changed.');
    }

    public function review(
        Request $request,
        AssistanceClaimTicket $ticket,
        AssistanceHandoverFeatureGate $feature,
        AssistanceClaimTicketService $tickets,
    ): View {
        $feature->requireEnabled();
        $this->authorizeHandover($request);
        $ticket->load(['application.program', 'application.farmer.user', 'approvedSupplyItem']);
        abort_unless($ticket->application?->status === 'approved'
            && $ticket->status === AssistanceClaimTicket::STATUS_ISSUED
            && $ticket->isEffectivelyIssued()
            && $tickets->matchesCurrentApproval($ticket), 404);

        $confirmation = AssistanceIdentityConfirmation::query()
            ->whereKey($request->integer('confirmation'))
            ->where('application_id', $ticket->application_id)
            ->where('claim_ticket_id', $ticket->id)
            ->where('outcome', 'confirmed')
            ->where('method', AssistanceIdentityConfirmation::QA_METHOD)
            ->firstOrFail();

        $batches = SupplyBatch::query()
            ->with(['item', 'location'])
            ->where('supply_item_id', $ticket->approved_supply_item_id)
            ->where('status', 'available')
            ->where('quantity_available', '>=', $ticket->approved_quantity)
            ->where(fn ($query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))
            ->orderBy('expires_on')
            ->orderBy('batch_number')
            ->get();

        return view('modules.aasms.claim-tickets.handover', [
            'ticket' => $ticket,
            'application' => $ticket->application,
            'confirmation' => $confirmation,
            'batches' => $batches,
            'qaLabel' => config('agricultural_claim_handover.qa_label'),
        ]);
    }

    public function distribute(
        Request $request,
        AssistanceApplication $application,
        AssistanceClaimTicket $ticket,
        AssistanceHandoverFeatureGate $feature,
        AssistanceHandoverService $handover,
        InventoryService $inventory,
    ): RedirectResponse {
        $feature->requireEnabled();
        $this->authorizeHandover($request);
        $data = $request->validate([
            'identity_confirmation_id' => ['required', 'integer', 'exists:assistance_identity_confirmations,id'],
            'supply_batch_id' => ['required', 'integer', 'exists:supply_batches,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'confirm_physical_handover' => ['required', 'accepted'],
        ]);

        $distribution = $handover->handover(
            $application,
            $ticket,
            AssistanceIdentityConfirmation::query()->findOrFail($data['identity_confirmation_id']),
            $data,
            $request->user(),
            $inventory,
        );

        return redirect()->route('modules.aasms.handovers.acknowledgment', $distribution->acknowledgment)
            ->with('status', 'QA handover committed atomically. One AISMS movement and one distribution are recorded; this is not evidence of actual farmer receipt.');
    }

    public function staffAcknowledgment(
        Request $request,
        AssistanceHandoverAcknowledgment $acknowledgment,
        AssistanceHandoverFeatureGate $feature,
    ): View {
        $feature->requireEnabled();
        abort_unless($request->user()->canModuleAction('aasms', 'view')
            && $request->user()->canErp('modules.aasms.claim_ticket_history'), 403);

        return view('modules.aasms.claim-tickets.acknowledgment', [
            'acknowledgment' => $acknowledgment->load(['application', 'ticket', 'distribution.batch', 'distributor']),
            'qaLabel' => config('agricultural_claim_handover.qa_label'),
        ]);
    }

    public function farmerAcknowledgment(
        Request $request,
        AssistanceHandoverAcknowledgment $acknowledgment,
        AssistanceHandoverFeatureGate $feature,
        AssistanceHandoverService $handover,
    ): Response {
        $feature->requireEnabled();
        $acknowledgment = $handover->acknowledgmentForFarmer($acknowledgment, $request->user());

        return response()->view('farmer.handover-acknowledgment', ['acknowledgment' => $acknowledgment])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function authorizeHandover(Request $request): void
    {
        $user = $request->user();
        abort_unless($user?->canModuleAction('aasms', 'view'), 403);
        abort_unless($user?->canModuleAction('aasms', 'distribute'), 403);
        abort_unless($user?->canModuleAction('aisms', 'release'), 403);
        abort_unless($user?->canErp('modules.aasms.handover_distribute'), 403);
        abort_unless($user?->canErp('modules.aasms.claim_ticket_verify'), 403);
    }
}
