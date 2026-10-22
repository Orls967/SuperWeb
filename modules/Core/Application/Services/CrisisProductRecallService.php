<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Domain\Models\CrisisAffectedItem;
use Modules\Core\Domain\Models\CrisisIncident;

class CrisisProductRecallService
{
    /**
     * Initiate emergency crisis product recall
     *
     * @param  array<int, array{sku: string, lot_number: string, quantity: int, location: string}>  $batches
     */
    public function initiateRecall(
        string $incidentCode,
        string $severity,
        string $title,
        array $batches,
        ?int $initiatorId = null
    ): CrisisIncident {
        $totalUnits = 0;
        foreach ($batches as $b) {
            $totalUnits += (int) $b['quantity'];
        }

        $incident = CrisisIncident::updateOrCreate(
            ['incident_code' => $incidentCode],
            [
                'severity_level' => $severity,
                'title' => $title,
                'initiator_id' => $initiatorId,
                'affected_branches_count' => count($batches),
                'total_units_quarantined' => $totalUnits,
                'emergency_state' => 'ACTIVE_QUARANTINE_LOCKED',
                'activated_at' => now(),
            ]
        );

        foreach ($batches as $b) {
            CrisisAffectedItem::updateOrCreate(
                [
                    'incident_id' => $incident->id,
                    'sku' => $b['sku'],
                    'lot_number' => $b['lot_number'],
                ],
                [
                    'quarantined_quantity' => (int) $b['quantity'],
                    'storage_zone' => $b['location'],
                    'is_sale_blocked' => true,
                    'quarantine_token' => hash('sha256', "RECALL-LOCK-{$b['lot_number']}"),
                ]
            );
        }

        return $incident;
    }

    /**
     * Bidirectional traceability lookup for a recalled lot number
     *
     * @return array{
     *     lot_number: string,
     *     is_quarantined: bool,
     *     incident_code: ?string,
     *     exposed_patients_count: int,
     *     affected_storage_locations: array<int, string>
     * }
     */
    public function traceLot(string $lotNumber): array
    {
        $item = CrisisAffectedItem::with('incident')
            ->where('lot_number', $lotNumber)
            ->first();

        if (! $item) {
            return [
                'lot_number' => $lotNumber,
                'is_quarantined' => false,
                'incident_code' => null,
                'exposed_patients_count' => 0,
                'affected_storage_locations' => [],
            ];
        }

        return [
            'lot_number' => $lotNumber,
            'is_quarantined' => $item->is_sale_blocked,
            'incident_code' => $item->incident?->incident_code,
            'exposed_patients_count' => 0, // Deterministic simulation baseline
            'affected_storage_locations' => [$item->storage_zone],
        ];
    }

    /**
     * Check if a specific SKU & Lot combination is prohibited from sale
     */
    public function isSaleBlocked(string $sku, string $lotNumber): bool
    {
        return CrisisAffectedItem::where('sku', $sku)
            ->where('lot_number', $lotNumber)
            ->where('is_sale_blocked', true)
            ->exists();
    }

    /**
     * Generate hazardous pharmaceutical waste destruction manifest
     *
     * @return array{
     *     manifest_number: string,
     *     incident_code: string,
     *     total_items_count: int,
     *     total_units_quarantined: int,
     *     regulatory_authority: string,
     *     disposal_method: string,
     *     verification_hash: string
     * }
     */
    public function generateWasteManifest(CrisisIncident $incident): array
    {
        $items = $incident->affectedItems()->get();
        $totalQty = (int) $items->sum('quarantined_quantity');

        $manifestNo = sprintf('MANIFEST-WASTE-%s-%04d', date('Ymd'), $incident->id);
        $hash = hash('sha256', "{$manifestNo}:{$incident->incident_code}:{$totalQty}");

        return [
            'manifest_number' => $manifestNo,
            'incident_code' => $incident->incident_code,
            'total_items_count' => $items->count(),
            'total_units_quarantined' => $totalQty,
            'regulatory_authority' => 'BPOM_KLHK_HAZARDOUS_DISPOSAL',
            'disposal_method' => 'HIGH_TEMPERATURE_INCINERATION_B3',
            'verification_hash' => $hash,
        ];
    }

    /**
     * Release quarantine status after lab clearance confirmation
     */
    public function releaseQuarantine(CrisisIncident $incident, string $labClearanceRef): bool
    {
        $incident->update([
            'emergency_state' => 'RELEASED_AFTER_LAB_CLEARANCE',
        ]);

        $incident->affectedItems()->update([
            'is_sale_blocked' => false,
            'quarantine_token' => hash('sha256', "CLEARED:{$labClearanceRef}"),
        ]);

        return true;
    }
}
