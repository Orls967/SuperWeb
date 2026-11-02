<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Application\Services;

use Modules\Mobility\Ev\Application\DTOs\SmartChargingPlanDTO;

class FlowCutCertificateVerifier
{
    public function verify(SmartChargingPlanDTO $plan): bool
    {
        $cert = $plan->certificate;
        if (! isset($cert['cut_capacity_wh'], $cert['max_flow_wh'])) {
            return false;
        }

        $cutCapacity = (int) $cert['cut_capacity_wh'];
        $maxFlow = (int) $cert['max_flow_wh'];

        if ($cutCapacity !== $maxFlow) {
            return false;
        }

        if ($maxFlow !== $plan->totalAllocatedWh()) {
            return false;
        }

        return true;
    }

    public function verifyOrThrow(SmartChargingPlanDTO $plan): void
    {
        if (! $this->verify($plan)) {
            throw new \LogicException('Min-cut certificate is invalid');
        }
    }
}
