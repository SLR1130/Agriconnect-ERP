<?php

namespace App\Http\Controllers;

use App\Models\AssistanceClaimTicket;
use App\Services\AssistanceClaimTicketService;
use App\Services\ClaimTicketFeatureGate;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class FarmerClaimTicketController extends Controller
{
    public function show(Request $request, AssistanceClaimTicket $ticket, ClaimTicketFeatureGate $feature): Response
    {
        $this->assertOwner($request, $ticket, $feature);
        $ticket->load(['application.program', 'application.farmer', 'approvedSupplyItem', 'issuer', 'supersedes', 'successor']);
        $events = $ticket->events()->paginate(25);

        return response()->view('farmer.claim-ticket', [
            'ticket' => $ticket,
            'events' => $events,
            'qrAvailable' => app(AssistanceClaimTicketService::class)->qrPayload($ticket) !== null,
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function print(Request $request, AssistanceClaimTicket $ticket, ClaimTicketFeatureGate $feature): Response
    {
        $this->assertOwner($request, $ticket, $feature);
        $ticket->load(['application.program', 'application.farmer', 'approvedSupplyItem']);
        $active = $ticket->isEffectivelyIssued()
            && app(AssistanceClaimTicketService::class)->qrPayload($ticket) !== null;

        return response()->view('farmer.claim-ticket-print', ['ticket' => $ticket, 'qrAvailable' => $active])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function qr(Request $request, AssistanceClaimTicket $ticket, ClaimTicketFeatureGate $feature, AssistanceClaimTicketService $tickets): Response
    {
        $this->assertOwner($request, $ticket, $feature);
        $payload = $tickets->qrPayload($ticket);
        abort_unless($payload, 404);

        $renderer = new ImageRenderer(new RendererStyle(260, 2), new SvgImageBackEnd());
        $svg = (new Writer($renderer))->writeString($payload);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:",
        ]);
    }

    private function assertOwner(Request $request, AssistanceClaimTicket $ticket, ClaimTicketFeatureGate $feature): void
    {
        $feature->requireEnabled();
        $farmer = $request->user()->farmer;
        abort_unless($farmer && (int) $ticket->application()->value('farmer_id') === (int) $farmer->id, 404);
    }
}
