<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Telemedicine;

use Modules\Hospital\Contracts\PatientReferralBooker;
use Modules\Hospital\Domain\Models\MedicineCourierDelivery;

class DispatchColdChainCourierAction
{
    public function __construct(
        protected PatientReferralBooker $booker
    ) {}

    /**
     * @param array{
     *     order_id: int,
     *     courier_service?: string,
     *     courier_name?: string,
     *     courier_phone?: string,
     *     recipient_name: string,
     *     recipient_phone: string,
     *     destination_address: string,
     *     destination_latitude?: float|null,
     *     destination_longitude?: float|null,
     *     requires_cold_chain?: bool,
     *     min_temp_c?: float,
     *     max_temp_c?: float,
     *     initial_temp_c?: float
     * } $data
     */
    public function execute(array $data): MedicineCourierDelivery
    {
        return $this->booker->dispatchMedicineDelivery($data);
    }
}
