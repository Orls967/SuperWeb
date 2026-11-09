<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Telemedicine;

use Modules\Hospital\Domain\Models\ChronicSubscription;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;

class RegisterChronicSubscriptionAction
{
    /**
     * @param array{
     *     patient_id: int,
     *     pharmacy_branch_id: int,
     *     drug_code: string,
     *     drug_name: string,
     *     monthly_quantity: int,
     *     unit_price_idr: int,
     *     start_refill_date?: string|null
     * } $data
     */
    public function execute(array $data): ChronicSubscription
    {
        $patient = Patient::findOrFail((int) $data['patient_id']);
        $branch = PharmacyBranch::findOrFail((int) $data['pharmacy_branch_id']);

        $code = 'SUB-'.strtoupper(bin2hex(random_bytes(4)));

        return ChronicSubscription::create([
            'subscription_code' => $code,
            'patient_id' => $patient->id,
            'pharmacy_branch_id' => $branch->id,
            'drug_code' => $data['drug_code'],
            'drug_name' => $data['drug_name'],
            'monthly_quantity' => (int) $data['monthly_quantity'],
            'unit_price_idr' => (int) $data['unit_price_idr'],
            'next_refill_date' => $data['start_refill_date'] ?? now()->toDateString(),
            'status' => 'ACTIVE',
        ]);
    }
}
