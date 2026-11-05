<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Syariah\Domain\Models\SybTradeAuditLog;
use Modules\Syariah\Domain\Models\SybTradeCustodyDocument;
use Modules\Syariah\Domain\Models\SybTradeCustodyInspection;

final class TradeCustodyService
{
    public function registerDocument(
        string $documentType,
        string $documentNumber,
        string $issuerName,
        string $carrierName,
        string $cargoDescription,
        string $rawContentForHash
    ): SybTradeCustodyDocument {
        if (trim($documentNumber) === '' || trim($issuerName) === '') {
            throw new InvalidArgumentException('Nomor dokumen dan penerbit dokumen tidak boleh kosong.');
        }

        $hash = hash('sha256', $rawContentForHash);

        return SybTradeCustodyDocument::create([
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'issuer_name' => $issuerName,
            'carrier_name' => $carrierName,
            'cargo_description' => $cargoDescription,
            'document_hash_sha256' => $hash,
            'verified_qabdh_at' => null,
            'verifier_officer_ref' => null,
        ]);
    }

    public function recordInspection(
        int $documentId,
        string $inspectorPartyRef,
        string $physicalLocation,
        string $conditionGrade,
        ?string $sealNumber = null,
        ?string $inspectionNotes = null
    ): SybTradeCustodyInspection {
        $doc = SybTradeCustodyDocument::findOrFail($documentId);

        return SybTradeCustodyInspection::create([
            'custody_document_id' => $doc->id,
            'inspector_party_ref' => $inspectorPartyRef,
            'physical_location' => $physicalLocation,
            'seal_number' => $sealNumber,
            'condition_grade' => $conditionGrade,
            'inspection_notes' => $inspectionNotes,
            'inspected_at' => CarbonImmutable::now(),
        ]);
    }

    public function verifyQabdh(int $documentId, string $verifierOfficerRef): SybTradeCustodyDocument
    {
        $doc = SybTradeCustodyDocument::findOrFail($documentId);

        return DB::transaction(function () use ($doc, $verifierOfficerRef) {
            $doc->update([
                'verified_qabdh_at' => CarbonImmutable::now(),
                'verifier_officer_ref' => $verifierOfficerRef,
            ]);

            SybTradeAuditLog::create([
                'audit_ref' => $doc->document_number,
                'event_name' => 'QABDH_VERIFIED',
                'details' => "Penguasaan dan verifikasi fisik kepemilikan komoditas (Qabdh) disahkan oleh {$verifierOfficerRef}",
                'shariah_compliance_status' => 'COMPLIANT',
            ]);

            return $doc->fresh();
        });
    }
}
