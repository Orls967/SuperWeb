<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\LabOutsourcingManifest;
use Modules\Hospital\Domain\Models\LabPartnerContract;
use Modules\Hospital\Domain\Models\LabResult;
use Modules\Hospital\Domain\Models\LabSpecimen;
use RuntimeException;

/**
 * Gerbang Integrasi Jaringan Laboratorium Rekanan Eksternal & HL7/FHIR Bridging (Fase 105).
 */
class OutsourcedLabBridgingGateway
{
    /**
     * Menerbitkan manifest pengiriman rujukan spesimen ke lab rekanan.
     *
     * @param  list<LabSpecimen>  $specimens
     */
    public function dispatchManifest(
        LabPartnerContract $contract,
        array $specimens,
        string $courierBatchBarcode,
        float $departureTempCelsius = 4.0
    ): LabOutsourcingManifest {
        return DB::transaction(function () use (
            $contract,
            $specimens,
            $courierBatchBarcode,
            $departureTempCelsius
        ) {
            if (empty($specimens)) {
                throw new RuntimeException('Manifest rujukan tidak dapat diterbitkan tanpa spesimen.');
            }

            $manifestNumber = 'MAN-OUT-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
            $specimenBarcodes = array_map(fn ($s) => $s->specimen_barcode, $specimens);
            sort($specimenBarcodes);

            $handoverPayload = implode('|', [
                $manifestNumber,
                $contract->contract_code,
                implode(',', $specimenBarcodes),
                number_format($departureTempCelsius, 2, '.', ''),
                now()->toIso8601String(),
            ]);
            $handoverHash = hash('sha256', $handoverPayload);

            $manifest = LabOutsourcingManifest::create([
                'manifest_number' => $manifestNumber,
                'contract_id' => $contract->id,
                'specimen_count' => count($specimens),
                'courier_batch_barcode' => $courierBatchBarcode,
                'departure_temperature_celsius' => $departureTempCelsius,
                'handover_hash' => $handoverHash,
                'status' => 'DISPATCHED',
            ]);

            // Perbarui status spesimen menjadi IN_TRANSIT
            foreach ($specimens as $s) {
                $s->update(['status' => 'IN_TRANSIT']);
            }

            return $manifest;
        });
    }

    /**
     * Memproses penerimaan pesan HL7 v2.5 ORU^R01 dari lab rekanan dan memetakan ke hasil lab lokal.
     *
     * @param  array{message_control_id: string, obr_order_num: string, obx_segments: list<array{loinc_code: string, value: float|string, units: string, abnormal_flag: string}>}  $hl7Payload
     * @return list<LabResult>
     */
    public function ingestHl7ObservationResults(LabSpecimen $specimen, array $hl7Payload): array
    {
        $createdResults = [];

        foreach ($hl7Payload['obx_segments'] as $obx) {
            $isCritical = in_array(strtoupper($obx['abnormal_flag'] ?? ''), ['LL', 'HH', 'CRITICAL', 'PANIC'], true);
            $numericVal = is_numeric($obx['value']) ? (float) $obx['value'] : null;
            $textVal = ! is_numeric($obx['value']) ? (string) $obx['value'] : null;

            $result = LabResult::create([
                'result_code' => 'RES-HL7-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2))),
                'specimen_id' => $specimen->id,
                'test_code' => $obx['loinc_code'],
                'numeric_value' => $numericVal,
                'text_value' => $textVal,
                'is_critical' => $isCritical,
                'status' => $isCritical ? 'CRITICAL_HOLD' : 'AUTO_VERIFIED',
            ]);

            $createdResults[] = $result;
        }

        return $createdResults;
    }
}
