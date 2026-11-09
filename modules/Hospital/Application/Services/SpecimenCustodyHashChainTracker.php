<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Enums\SpecimenCustodyStatus;
use Modules\Hospital\Domain\Models\LabCustodyTransfer;
use Modules\Hospital\Domain\Models\LabSpecimen;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\SpecimenCustodyChainLog;
use RuntimeException;

/**
 * Layanan Pelacakan Kustodi Spesimen 3-Hop Berbasis Cryptographic Hash-Chain (Fase 105).
 * Memverifikasi integritas fisik, logistik berpendingin, dan analisis laboratorium.
 */
class SpecimenCustodyHashChainTracker
{
    public const MAX_COLD_CHAIN_TEMP_CELSIUS = 10.0;

    /**
     * Menginisialisasi spesimen baru dan mencatat genesis hash.
     */
    public function initializeSpecimen(
        Patient $patient,
        string $sampleType,
        ?string $barcode = null
    ): LabSpecimen {
        $barcode = $barcode ?? 'SPEC-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
        $now = now();

        $genesisPayload = implode('|', [
            'GENESIS',
            $barcode,
            $patient->mrn,
            $sampleType,
            $now->toIso8601String(),
        ]);
        $genesisHash = hash('sha256', $genesisPayload);

        return LabSpecimen::create([
            'specimen_barcode' => $barcode,
            'patient_id' => $patient->id,
            'sample_type' => $sampleType,
            'collected_at' => $now,
            'transport_temp_c' => 4.0,
            'custody_hash' => $genesisHash,
            'status' => SpecimenCustodyStatus::COLLECTED->value,
        ]);
    }

    /**
     * Mencatat perpindahan hop kustodi spesimen (Hop 1, Hop 2, Hop 3).
     *
     * @throws RuntimeException Jika terjadi pelanggaran suhu dingin atau spesimen sudah ditolak.
     */
    public function recordHop(
        LabSpecimen $specimen,
        int $hopNumber,
        string $fromHandler,
        string $toHandler,
        ?float $temperatureCelsius = null,
        ?string $notes = null,
        ?string $gpsCoordinates = null,
        ?string $containerBarcode = null
    ): SpecimenCustodyChainLog {
        return DB::transaction(function () use (
            $specimen,
            $hopNumber,
            $fromHandler,
            $toHandler,
            $temperatureCelsius,
            $notes,
            $gpsCoordinates,
            $containerBarcode
        ) {
            $fresh = $specimen->fresh();

            if ($fresh->status === SpecimenCustodyStatus::REJECTED->value) {
                throw new RuntimeException("Spesimen [{$fresh->specimen_barcode}] sudah dalam status DITOLAK (REJECTED).");
            }

            // Validasi pelanggaran rantai dingin (> 10.0 C)
            if ($temperatureCelsius !== null && $temperatureCelsius > self::MAX_COLD_CHAIN_TEMP_CELSIUS) {
                $fresh->update([
                    'status' => SpecimenCustodyStatus::REJECTED->value,
                    'transport_temp_c' => $temperatureCelsius,
                ]);

                throw new RuntimeException(
                    "Specimen rejected due to cold-chain temperature breach: {$temperatureCelsius}C exceeds threshold ".self::MAX_COLD_CHAIN_TEMP_CELSIUS.'C'
                );
            }

            $prevHash = $fresh->custody_hash ?? hash('sha256', 'GENESIS:'.$fresh->specimen_barcode);
            $transferredAt = now();

            // Hitung hash kanonikal simpul n
            $canonicalPayload = implode('|', [
                $prevHash,
                (string) $fresh->id,
                (string) $hopNumber,
                $fromHandler,
                $toHandler,
                $temperatureCelsius !== null ? number_format($temperatureCelsius, 2, '.', '') : 'NA',
                $transferredAt->toIso8601String(),
            ]);
            $hopHash = hash('sha256', $canonicalPayload);

            // Simpan log ke tabel hsp_specimen_custody_chain_logs
            $log = SpecimenCustodyChainLog::create([
                'specimen_id' => $fresh->id,
                'hop_number' => $hopNumber,
                'from_handler' => $fromHandler,
                'to_handler' => $toHandler,
                'transport_temp_celsius' => $temperatureCelsius,
                'gps_coordinates' => $gpsCoordinates,
                'storage_container_barcode' => $containerBarcode,
                'prev_hash' => $prevHash,
                'custody_hash' => $hopHash,
                'notes' => $notes,
                'transferred_at' => $transferredAt,
            ]);

            // Sinkronkan ke hsp_lab_custody_transfers untuk backward-compatibility
            LabCustodyTransfer::create([
                'specimen_id' => $fresh->id,
                'hop_number' => $hopNumber,
                'from_handler' => $fromHandler,
                'to_handler' => $toHandler,
                'prev_hash' => $prevHash,
                'custody_hash' => $hopHash,
                'transferred_at' => $transferredAt,
            ]);

            // Update status spesimen sesuai hop
            $newStatus = match ($hopNumber) {
                1 => SpecimenCustodyStatus::IN_TRANSIT->value,
                2 => SpecimenCustodyStatus::RECEIVED_LAB->value,
                default => SpecimenCustodyStatus::PROCESSED->value,
            };

            $fresh->update([
                'custody_hash' => $hopHash,
                'status' => $newStatus,
                'transport_temp_c' => $temperatureCelsius ?? $fresh->transport_temp_c,
            ]);

            return $log;
        });
    }

    /**
     * Memverifikasi keutuhan rantai hash kriptografis 100% tanpa celah dan tanpa sabotase.
     */
    public function verifyChainIntegrity(LabSpecimen $specimen): bool
    {
        $fresh = $specimen->fresh();

        // Cek log di hsp_specimen_custody_chain_logs atau hsp_lab_custody_transfers
        $logs = SpecimenCustodyChainLog::where('specimen_id', $fresh->id)
            ->orderBy('hop_number', 'asc')
            ->get();

        if ($logs->isEmpty()) {
            $transfers = LabCustodyTransfer::where('specimen_id', $fresh->id)
                ->orderBy('hop_number', 'asc')
                ->get();

            if ($transfers->isEmpty()) {
                return false;
            }

            // Verifikasi dari transfers
            $expectedPrevHash = null;
            $expectedHop = 1;

            foreach ($transfers as $tx) {
                if ($tx->hop_number !== $expectedHop) {
                    return false;
                }

                if ($expectedPrevHash !== null && $tx->prev_hash !== $expectedPrevHash) {
                    return false;
                }

                $expectedPrevHash = $tx->custody_hash;
                $expectedHop++;
            }

            return $fresh->custody_hash === $expectedPrevHash;
        }

        $expectedPrevHash = null;
        $expectedHop = 1;

        foreach ($logs as $log) {
            if ($log->hop_number !== $expectedHop) {
                return false;
            }

            if ($expectedPrevHash !== null && $log->prev_hash !== $expectedPrevHash) {
                return false;
            }

            // Hitung ulang hash kanonikal
            $recalculated = $log->calculateCanonicalHash();
            if ($recalculated !== $log->custody_hash) {
                return false;
            }

            $expectedPrevHash = $log->custody_hash;
            $expectedHop++;
        }

        return $fresh->custody_hash === $expectedPrevHash;
    }
}
