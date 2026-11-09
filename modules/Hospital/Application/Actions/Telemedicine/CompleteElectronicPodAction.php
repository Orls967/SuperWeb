<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Telemedicine;

use Modules\Hospital\Domain\Models\MedicineCourierDelivery;
use Modules\Hospital\Domain\Services\ColdChainMedicineDeliveryTracker;

class CompleteElectronicPodAction
{
    public function __construct(
        protected ColdChainMedicineDeliveryTracker $tracker
    ) {}

    public function execute(
        MedicineCourierDelivery $delivery,
        string $recipientNik,
        string $rawSignatureOrToken
    ): MedicineCourierDelivery {
        return $this->tracker->completeProofOfDelivery(
            delivery: $delivery,
            recipientNik: $recipientNik,
            rawSignatureOrToken: $rawSignatureOrToken
        );
    }
}
