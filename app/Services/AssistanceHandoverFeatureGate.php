<?php

namespace App\Services;

class AssistanceHandoverFeatureGate
{
    public function operational(): bool
    {
        return app(ClaimTicketFeatureGate::class)->operational();
    }

    public function enabled(): bool
    {
        return (bool) config('agricultural_claim_handover.enabled')
            && app(ClaimTicketFeatureGate::class)->enabled();
    }

    public function requireEnabled(): void
    {
        abort_unless($this->enabled(), 404);
    }
}
