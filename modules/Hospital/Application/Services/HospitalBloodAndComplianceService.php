<?php

namespace Modules\Hospital\Application\Services;

use Modules\Banking\Application\Services\LedgerService;
use Modules\Hospital\Domain\Models\BloodBag;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Models\MedicalEquipment;
use Modules\Hospital\Domain\Models\MedicalWasteManifest;
use RuntimeException;

class HospitalBloodAndComplianceService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 109.1 Blood Bank Bag Registration
     */
    public function registerBloodBag(
        string $bloodType,
        string $componentType,
        string $expiryDate,
        string $donorScreeningHash
    ): BloodBag {
        $serial = 'BLD-'.strtoupper(bin2hex(random_bytes(6)));

        return BloodBag::create([
            'bag_serial_number' => $serial,
            'blood_type' => $bloodType,
            'component_type' => $componentType,
            'volume_ml' => 350,
            'expiry_date' => $expiryDate,
            'donor_screening_hash' => $donorScreeningHash,
            'status' => 'AVAILABLE',
        ]);
    }

    /**
     * 109.1 & 109.6 (a) Reserve blood bag for surgical encounter: expired bags cannot be reserved
     */
    public function reserveBloodBag(BloodBag $bag, Encounter $encounter): BloodBag
    {
        if (strtotime($bag->expiry_date) < strtotime(date('Y-m-d'))) {
            throw new RuntimeException("Blood reservation rejected: Blood bag {$bag->bag_serial_number} is expired ({$bag->expiry_date})");
        }

        if ($bag->status !== 'AVAILABLE') {
            throw new RuntimeException("Blood reservation rejected: Blood bag is not available (Status: {$bag->status})");
        }

        $bag->update([
            'reserved_for_encounter_id' => $encounter->id,
            'status' => 'RESERVED',
        ]);

        return $bag;
    }

    /**
     * 109.1 Recall contaminated blood bag and trace recipients
     */
    public function recallBloodBag(BloodBag $bag): array
    {
        $bag->update(['status' => 'RECALLED']);

        $recipientEncounterId = $bag->issued_to_encounter_id ?? $bag->reserved_for_encounter_id;

        return [
            'recalled_bag' => $bag->bag_serial_number,
            'status' => 'RECALLED',
            'affected_encounter_id' => $recipientEncounterId,
            'urgent_clinical_alert' => $recipientEncounterId ? 'EMERGENCY_RECIPIENT_TRACED' : 'QUARANTINED_IN_BANK',
        ];
    }

    /**
     * 109.3 & 109.6 (c) Verify Equipment Calibration before Clinical Procedure
     */
    public function verifyEquipmentUsability(MedicalEquipment $equipment): bool
    {
        if (strtotime($equipment->calibration_expires_at) < strtotime(date('Y-m-d'))) {
            $equipment->update(['status' => 'CALIBRATION_EXPIRED']);
            throw new RuntimeException("Clinical procedure blocked: Medical equipment {$equipment->equipment_code} calibration expired on {$equipment->calibration_expires_at}");
        }

        return true;
    }

    /**
     * 109.5 Hazardous Medical Waste Manifest (B3) and Chain of Custody
     */
    public function dispatchMedicalWaste(
        string $wasteCategory,
        float $weightKg,
        string $vendorPartyId
    ): MedicalWasteManifest {
        $count = MedicalWasteManifest::count() + 1;
        $manifestNumber = sprintf('HAZ-MED-%06d', $count);
        $custodyHash = hash('sha256', "HAZARD-{$manifestNumber}-{$wasteCategory}-{$weightKg}-{$vendorPartyId}");

        return MedicalWasteManifest::create([
            'manifest_number' => $manifestNumber,
            'waste_category' => $wasteCategory,
            'weight_kg' => $weightKg,
            'certified_vendor_party_id' => $vendorPartyId,
            'custody_hash' => $custodyHash,
            'status' => 'DISPATCHED',
        ]);
    }
}
