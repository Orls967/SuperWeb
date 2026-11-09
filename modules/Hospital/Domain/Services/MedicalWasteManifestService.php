<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;
use Illuminate\Support\Str;
use Modules\Hospital\Application\DTOs\WasteManifestRecordDTO;
use Modules\Hospital\Domain\Models\MedicalWasteManifest;
use Modules\Hospital\Domain\Models\WasteCustodyLog;
use Modules\Hospital\Domain\StateMachines\MedicalWasteStateMachine;

/**
 * Layanan Domain Pengelolaan Limbah Medis B3 & Integritas Kustodi Elektronik (Festronik Chain of Custody).
 * Menjamin pelacakan limbah medis berbahaya (infeksius, benda tajam, sitotoksik, patologis)
 * dari ruangan perawatan, penimbangan di TPS B3 internal, pengangkutan oleh transporter berizin KLHK,
 * hingga pemusnahan termal insinerator bersertifikat dengan bukti segel kriptografis SHA-256.
 */
class MedicalWasteManifestService
{
    /**
     * Daftarkan manifest limbah baru dari ruangan perawatan (Source Ward).
     */
    public function registerWasteFromWard(
        string $wasteCategory,
        float $weightKg,
        string $sourceWardCode,
        string $handlerStaffName
    ): WasteManifestRecordDTO {
        if ($weightKg <= 0.0) {
            throw new DomainException('Berat timbangan limbah medis harus lebih dari 0 kg.');
        }

        $manifestNo = 'MNF-'.date('Ymd').'-'.strtoupper(Str::random(8));
        $initialPrevHash = hash('sha256', "GENESIS-WASTE-{$manifestNo}");
        $nowIso = now()->toIso8601String();

        $manifest = MedicalWasteManifest::create([
            'manifest_number' => $manifestNo,
            'waste_category' => strtoupper($wasteCategory),
            'weight_kg' => $weightKg,
            'source_ward_code' => $sourceWardCode,
            'certified_vendor_party_id' => 1,
            'custody_hash' => $initialPrevHash,
            'status' => MedicalWasteStateMachine::STATUS_LOGGED_IN_WARD,
        ]);

        $currentHash = WasteCustodyLog::computeChainHash(
            prevHash: $initialPrevHash,
            manifestId: $manifest->id,
            eventType: 'GENERATED_AT_SOURCE',
            weightKg: $weightKg,
            handlerParty: $handlerStaffName,
            timestampIso: $nowIso
        );

        $manifest->custody_hash = $currentHash;
        $manifest->save();

        WasteCustodyLog::create([
            'manifest_id' => $manifest->id,
            'event_type' => 'GENERATED_AT_SOURCE',
            'handler_party_name' => $handlerStaffName,
            'weight_kg' => $weightKg,
            'storage_bin_code' => "BIN-{$sourceWardCode}-01",
            'prev_custody_hash' => $initialPrevHash,
            'current_custody_hash' => $currentHash,
            'event_at' => now(),
        ]);

        return new WasteManifestRecordDTO(
            manifestNumber: $manifestNo,
            wasteCategory: $wasteCategory,
            weightKg: $weightKg,
            sourceWardCode: $sourceWardCode,
            handlerPartyName: $handlerStaffName,
            transporterPlateNo: null,
            custodyHash: $currentHash,
            status: $manifest->status,
            timestampIso: $nowIso,
        );
    }

    /**
     * Catat penerimaan dan penimbangan ulang limbah di Tempat Penyimpanan Sementara (TPS) B3.
     */
    public function receiveAtInternalTps(
        MedicalWasteManifest $manifest,
        float $reweighedKg,
        string $tpsOfficerName,
        string $tpsBinCode
    ): WasteManifestRecordDTO {
        // Toleransi selisih timbangan ruangan vs TPS maksimal 5%
        $weightDiffPct = abs($reweighedKg - $manifest->weight_kg) / $manifest->weight_kg * 100;
        if ($weightDiffPct > 5.0) {
            MedicalWasteStateMachine::transition(
                $manifest,
                MedicalWasteStateMachine::STATUS_DISCREPANCY_FLAGGED,
                "Perbedaan berat melebihi ambang batas toleransi 5% ({$weightDiffPct}%)"
            );
            throw new DomainException("Anomali berat limbah B3: timbangan awal {$manifest->weight_kg}kg vs timbangan TPS {$reweighedKg}kg (selisih {$weightDiffPct}%).");
        }

        $prevHash = $manifest->custody_hash;
        $nowIso = now()->toIso8601String();

        $currentHash = WasteCustodyLog::computeChainHash(
            prevHash: $prevHash,
            manifestId: $manifest->id,
            eventType: 'RECEIVED_AT_TPS',
            weightKg: $reweighedKg,
            handlerParty: $tpsOfficerName,
            timestampIso: $nowIso
        );

        $manifest->custody_hash = $currentHash;
        $manifest->weight_kg = $reweighedKg;
        MedicalWasteStateMachine::transition($manifest, MedicalWasteStateMachine::STATUS_RECEIVED_AT_TPS);

        WasteCustodyLog::create([
            'manifest_id' => $manifest->id,
            'event_type' => 'RECEIVED_AT_TPS',
            'handler_party_name' => $tpsOfficerName,
            'weight_kg' => $reweighedKg,
            'storage_bin_code' => $tpsBinCode,
            'prev_custody_hash' => $prevHash,
            'current_custody_hash' => $currentHash,
            'event_at' => now(),
        ]);

        return new WasteManifestRecordDTO(
            manifestNumber: $manifest->manifest_number,
            wasteCategory: $manifest->waste_category,
            weightKg: $reweighedKg,
            sourceWardCode: $manifest->source_ward_code ?? 'TPS',
            handlerPartyName: $tpsOfficerName,
            transporterPlateNo: null,
            custodyHash: $currentHash,
            status: $manifest->status,
            timestampIso: $nowIso,
        );
    }

    /**
     * Serah terima limbah ke transporter berizin resmi (Handover to Certified Transporter).
     */
    public function handoverToTransporter(
        MedicalWasteManifest $manifest,
        string $transporterCompanyName,
        string $vehiclePlateNo,
        string $driverName,
        float $handoverWeightKg
    ): WasteManifestRecordDTO {
        $prevHash = $manifest->custody_hash;
        $nowIso = now()->toIso8601String();

        $currentHash = WasteCustodyLog::computeChainHash(
            prevHash: $prevHash,
            manifestId: $manifest->id,
            eventType: 'HANDED_TO_TRANSPORTER',
            weightKg: $handoverWeightKg,
            handlerParty: "{$transporterCompanyName} / {$driverName}",
            timestampIso: $nowIso
        );

        $manifest->custody_hash = $currentHash;
        MedicalWasteStateMachine::transition($manifest, MedicalWasteStateMachine::STATUS_HANDED_TO_TRANSPORTER);

        WasteCustodyLog::create([
            'manifest_id' => $manifest->id,
            'event_type' => 'HANDED_TO_TRANSPORTER',
            'handler_party_name' => "{$transporterCompanyName} / {$driverName}",
            'transporter_plate_no' => $vehiclePlateNo,
            'weight_kg' => $handoverWeightKg,
            'prev_custody_hash' => $prevHash,
            'current_custody_hash' => $currentHash,
            'event_at' => now(),
        ]);

        return new WasteManifestRecordDTO(
            manifestNumber: $manifest->manifest_number,
            wasteCategory: $manifest->waste_category,
            weightKg: $handoverWeightKg,
            sourceWardCode: $manifest->source_ward_code ?? 'TPS',
            handlerPartyName: "{$transporterCompanyName} ({$driverName})",
            transporterPlateNo: $vehiclePlateNo,
            custodyHash: $currentHash,
            status: $manifest->status,
            timestampIso: $nowIso,
        );
    }

    /**
     * Konfirmasi pemusnahan akhir (Certificate of Destruction) oleh fasilitas insinerator berizin.
     */
    public function certifyDestruction(
        MedicalWasteManifest $manifest,
        string $destructionFacilityName,
        string $destructionCertificateHash
    ): MedicalWasteManifest {
        if (empty(trim($destructionCertificateHash))) {
            throw new DomainException('Nomor atau hash sertifikat pemusnahan limbah B3 tidak boleh kosong.');
        }

        $manifest->destruction_certificate_hash = $destructionCertificateHash;
        MedicalWasteStateMachine::transition($manifest, MedicalWasteStateMachine::STATUS_COMPLETED);

        return $manifest;
    }

    /**
     * Memverifikasi keutuhan rantai hash kustodi Festronik (Chain-of-Custody Integrity).
     */
    public function verifyCustodyChainIntegrity(MedicalWasteManifest $manifest): bool
    {
        $logs = $manifest->custodyLogs()->orderBy('id')->get();
        if ($logs->isEmpty()) {
            return false;
        }

        $runningHash = $logs->first()->prev_custody_hash;

        foreach ($logs as $log) {
            $expectedHash = WasteCustodyLog::computeChainHash(
                prevHash: $runningHash,
                manifestId: $manifest->id,
                eventType: $log->event_type,
                weightKg: (float) $log->weight_kg,
                handlerParty: $log->handler_party_name,
                timestampIso: $log->event_at->toIso8601String()
            );

            if ($expectedHash !== $log->current_custody_hash) {
                return false;
            }

            $runningHash = $log->current_custody_hash;
        }

        return $runningHash === $manifest->custody_hash;
    }
}
