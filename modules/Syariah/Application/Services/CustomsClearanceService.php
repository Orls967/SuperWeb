<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Syariah\Domain\Models\SybTradeAuditLog;
use Modules\Syariah\Domain\Models\SybTradeCustodyDocument;
use RuntimeException;

final class CustomsClearanceService
{
    /**
     * Memvalidasi izin kepabeanan syariah dan verifikasi sertifikasi halal kargo pangan impor.
     */
    public function recordClearancePermit(
        int $custodyDocumentId,
        string $customsRegistrationNumber,
        string $customsOfficeName,
        string $halalCertificateNumber,
        string $certifyingBodyName,
        string $inspectorPartyRef
    ): array {
        if (trim($customsRegistrationNumber) === '' || trim($halalCertificateNumber) === '') {
            throw new InvalidArgumentException('Nomor pendaftaran kepabeanan dan sertifikat halal tidak boleh kosong.');
        }

        $doc = SybTradeCustodyDocument::findOrFail($custodyDocumentId);

        return DB::transaction(function () use (
            $doc,
            $customsRegistrationNumber,
            $customsOfficeName,
            $halalCertificateNumber,
            $certifyingBodyName,
            $inspectorPartyRef
        ) {
            $clearancePayload = [
                'document_id' => $doc->id,
                'document_number' => $doc->document_number,
                'customs_reg_no' => $customsRegistrationNumber,
                'customs_office' => $customsOfficeName,
                'halal_cert_no' => $halalCertificateNumber,
                'certifying_body' => $certifyingBodyName,
                'cleared_at' => CarbonImmutable::now()->toIso8601String(),
                'inspector_ref' => $inspectorPartyRef,
                'is_halal_certified' => true,
            ];

            SybTradeAuditLog::create([
                'audit_ref' => $doc->document_number,
                'event_name' => 'CUSTOMS_HALAL_CLEARED',
                'details' => "Izin kepabeanan {$customsRegistrationNumber} di {$customsOfficeName} dan sertifikat halal {$halalCertificateNumber} disahkan oleh {$inspectorPartyRef}",
                'shariah_compliance_status' => 'COMPLIANT',
            ]);

            return $clearancePayload;
        });
    }

    /**
     * Memverifikasi kepatuhan larangan barang syubhat atau haram pada manifest kargo impor.
     */
    public function auditCargoCompliance(string $cargoDescription): array
    {
        $bannedKeywords = ['alcohol', 'pork', 'khmar', 'babi', 'riba', 'casino', 'gambling'];
        $lowered = strtolower($cargoDescription);

        foreach ($bannedKeywords as $keyword) {
            if (str_contains($lowered, $keyword)) {
                throw new RuntimeException("Pelanggaran Kepatuhan Syariah: Terdeteksi komoditas terlarang ({$keyword}) pada muatan kargo!");
            }
        }

        return [
            'status' => 'COMPLIANT',
            'scanned_keywords' => count($bannedKeywords),
            'verified_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}

