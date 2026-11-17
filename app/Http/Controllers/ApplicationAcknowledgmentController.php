<?php

namespace App\Http\Controllers;

use App\Models\AssistanceApplication;
use App\Services\AgriculturalSchemaCapabilities;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApplicationAcknowledgmentController extends Controller
{
    public function farmer(Request $request, AssistanceApplication $application, AgriculturalSchemaCapabilities $schema): Response
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        $farmer = $request->user()->farmer()->first();
        abort_unless($farmer && (int) $application->farmer_id === (int) $farmer->id, 404);
        abort_unless($schema->acknowledgmentsReady(), 404);

        return $this->render($request, $application);
    }

    public function staff(Request $request, AssistanceApplication $application, AgriculturalSchemaCapabilities $schema): Response
    {
        abort_unless($request->user()->canModuleAction('aasms', 'view'), 403);
        abort_unless($schema->acknowledgmentsReady(), 404);

        return $this->render($request, $application);
    }

    public function staffIntake(Request $request, AssistanceApplication $application, AgriculturalSchemaCapabilities $schema): Response
    {
        abort_unless($request->user()->canModuleAction('aasms', 'intake_assist'), 403);
        abort_unless($application->submission_channel === 'mao_staff_assisted', 404);
        abort_unless($schema->acknowledgmentsReady(), 404);

        return $this->render($request, $application);
    }

    private function render(Request $request, AssistanceApplication $application): Response
    {
        $acknowledgment = $application->acknowledgment()->firstOrFail();
        $snapshot = $acknowledgment->snapshot;
        $download = $request->boolean('download');
        $reference = preg_replace('/[^A-Z0-9-]/', '', (string) $acknowledgment->application_reference);

        return response()
            ->view('farmer.acknowledgment', compact('snapshot', 'download'))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', ($download ? 'attachment' : 'inline').'; filename="'.$reference.'.html"')
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
