<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Hospital\Application\DTOs\PrescriptionItemDTO;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientPrescription;

class CreateOutpatientPrescriptionAction
{
    public function __construct(
        protected DocumentNumberingInterface $numbering,
        protected ClockInterface $clock,
    ) {}

    public function execute(OutpatientEncounter $encounter, PrescriptionItemDTO $dto, ?User $doctorUser = null): OutpatientPrescription
    {
        if ($doctorUser !== null && ! in_array($doctorUser->role, ['doctor', 'admin'], true)) {
            throw new AuthorizationException('Hanya dokter yang berwenang menerbitkan resep obat.');
        }

        $prescriptionCode = $this->numbering->nextNumber('HSP', 'RX');
        $subtotal = $dto->quantity * $dto->unitPriceIdr;

        $prescription = OutpatientPrescription::create([
            'prescription_code' => $prescriptionCode,
            'encounter_id' => $encounter->id,
            'doctor_id' => $encounter->doctor_id,
            'drug_code' => $dto->drugCode,
            'drug_name' => $dto->drugName,
            'quantity' => $dto->quantity,
            'dosage_instructions' => $dto->dosageInstructions,
            'unit_price_idr' => $dto->unitPriceIdr,
            'subtotal_idr' => $subtotal,
            'status' => 'PRESCRIBED',
        ]);

        $encounter->update(['status' => 'PRESCRIBED']);

        return $prescription;
    }
}
