<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\BloodBag;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Models\MedicalEquipment;
use Modules\Hospital\Domain\Models\MedicalWasteManifest;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class TrackB3MedicalWasteManifestAndBloodBankCrossmatchAction extends BaseAction
{
    /**
     * Track B3 medical waste custody manifest, reserve crossmatched blood bag, and verify medical equipment calibration.
     *
     * @return array{blood_bag: ?BloodBag, equipment: ?MedicalEquipment, waste_manifest: ?MedicalWasteManifest}
     */
    public function handle(
        ?int $bloodBagId = null,
        ?int $encounterId = null,
        ?int $equipmentId = null,
        string $wasteCategory = 'INFECTIOUS',
        float $wasteWeightKg = 15.5,
        string $vendorPartyId = 'VEND-B3-ENV-01'
    ): array {
        return DB::transaction(function () use (
            $bloodBagId,
            $encounterId,
            $equipmentId,
            $wasteCategory,
            $wasteWeightKg,
            $vendorPartyId
        ) {
            $bloodBag = null;
            if ($bloodBagId !== null) {
                $bloodBag = BloodBag::lockForUpdate()->findOrFail($bloodBagId);

                // 109.1 & 109.6 (a) Blood expiration validation
                if (strtotime($bloodBag->expiry_date) < strtotime(date('Y-m-d'))) {
                    throw new RuntimeException("Blood reservation rejected: Blood bag {$bloodBag->bag_serial_number} is expired ({$bloodBag->expiry_date})");
                }

                if ($bloodBag->status !== 'AVAILABLE') {
                    throw new RuntimeException("Blood reservation rejected: Blood bag is not available (Status: {$bloodBag->status})");
                }

                if ($encounterId !== null) {
                    $encounter = Encounter::lockForUpdate()->findOrFail($encounterId);
                    $bloodBag->update([
                        'reserved_for_encounter_id' => $encounter->id,
                        'status' => 'RESERVED',
                    ]);
                }
            }

            $equipment = null;
            if ($equipmentId !== null) {
                $equipment = MedicalEquipment::lockForUpdate()->findOrFail($equipmentId);

                // 109.3 & 109.6 (c) Equipment calibration verification
                if (strtotime($equipment->calibration_expires_at) < strtotime(date('Y-m-d'))) {
                    $equipment->update(['status' => 'CALIBRATION_EXPIRED']);
                    throw new RuntimeException("Clinical procedure blocked: Medical equipment {$equipment->equipment_code} calibration expired on {$equipment->calibration_expires_at}");
                }
            }

            // 109.5 Gapless B3 Hazardous Medical Waste Manifest with Cryptographic Custody Hash
            $manifestCount = MedicalWasteManifest::count() + 1;
            $manifestNumber = sprintf('HAZ-MED-%06d', $manifestCount);
            $hmacKey = (string) config('app.key', 'hsp-custody-b3-key');
            $custodyHash = hash_hmac('sha256', "HAZARD-{$manifestNumber}-{$wasteCategory}-{$wasteWeightKg}-{$vendorPartyId}", $hmacKey);

            $wasteManifest = MedicalWasteManifest::create([
                'manifest_number' => $manifestNumber,
                'waste_category' => $wasteCategory,
                'weight_kg' => $wasteWeightKg,
                'certified_vendor_party_id' => $vendorPartyId,
                'custody_hash' => $custodyHash,
                'status' => 'DISPATCHED',
            ]);

            $this->audit(
                action: 'hospital.waste_and_blood.tracked',
                auditable: $wasteManifest,
                oldValues: null,
                newValues: [
                    'manifest_number' => $manifestNumber,
                    'waste_category' => $wasteCategory,
                    'weight_kg' => $wasteWeightKg,
                    'blood_bag_serial' => $bloodBag?->bag_serial_number,
                    'equipment_code' => $equipment?->equipment_code,
                ],
                correlationId: "hsp_waste_{$manifestNumber}",
                impactType: 'medical_waste_blood_compliance'
            );

            return [
                'blood_bag' => $bloodBag,
                'equipment' => $equipment,
                'waste_manifest' => $wasteManifest,
            ];
        });
    }
}
