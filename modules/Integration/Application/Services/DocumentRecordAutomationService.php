<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * DocumentRecordAutomationService (Fase 368)
 *
 * Implements:
 *  - 368.2 Document extraction pipeline with confidence threshold
 *  - 368.3 Legal hold connections preventing document disposal
 *  - 368.4 Tests: Low-confidence financial field cannot auto-post; legal hold prevents deletion; document:audit clean
 *  - 368.5 Edge case: Low-confidence extraction on financial document mandates human verification before posting
 *  - 368.6 Risk: Disposal of records under active legal hold strictly prevented
 */
class DocumentRecordAutomationService
{
    /**
     * Process document extraction and evaluate auto-posting eligibility (368.2, 368.4, 368.5 Edge Case).
     */
    public function processExtraction(
        string $documentCode,
        string $documentType,
        float $confidence,
        float $minThreshold = 0.9500,
        bool $humanVerified = false
    ): object {
        $dCode = strtoupper($documentCode);
        $type = strtoupper($documentType);

        $isFinancial = str_contains($type, 'FINANCIAL');
        $lowConfidence = ($confidence < $minThreshold);

        // Edge case 368.5: Low confidence on financial document mandates human verification
        if ($isFinancial && $lowConfidence && ! $humanVerified) {
            DB::table('platform_document_extractions')->insert([
                'document_code' => $dCode,
                'document_type' => $type,
                'extraction_confidence' => $confidence,
                'min_confidence_threshold' => $minThreshold,
                'human_verified' => false,
                'auto_posting_permitted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("Posting gate breach: Low-confidence financial document extraction ({$confidence} < {$minThreshold}) requires human verification (368.5).");
        }

        $id = DB::table('platform_document_extractions')->insertGetId([
            'document_code' => $dCode,
            'document_type' => $type,
            'extraction_confidence' => $confidence,
            'min_confidence_threshold' => $minThreshold,
            'human_verified' => $humanVerified,
            'auto_posting_permitted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_document_extractions')->find($id);
    }

    /**
     * Place legal hold on document and prevent deletion (368.3, 368.4, 368.6 Risk).
     */
    public function placeLegalHold(string $holdCode, string $documentCode): object
    {
        $hCode = strtoupper($holdCode);
        $dCode = strtoupper($documentCode);

        $id = DB::table('platform_document_legal_holds')->insertGetId([
            'hold_code' => $hCode,
            'document_code' => $dCode,
            'is_legal_hold_active' => true,
            'deletion_prevented' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('platform_document_legal_holds')->find($id);
    }

    /**
     * Attempt document disposal with legal hold check (368.4 & 368.6 Risk).
     */
    public function attemptDocumentDisposal(string $documentCode): bool
    {
        $dCode = strtoupper($documentCode);

        $hold = DB::table('platform_document_legal_holds')
            ->where('document_code', $dCode)
            ->where('is_legal_hold_active', true)
            ->first();

        if ($hold) {
            throw new InvalidArgumentException("Legal hold violation: Document '{$documentCode}' is under active legal hold and cannot be deleted (368.6).");
        }

        return true;
    }

    /**
     * Platform Document & Legal Hold Audit (`document:audit`) (368.4, 368.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Low-confidence financial documents auto-posted without human verification
        $unverifiedFinancialPosts = DB::table('platform_document_extractions')
            ->where('auto_posting_permitted', true)
            ->where('human_verified', false)
            ->whereColumn('extraction_confidence', '<', 'min_confidence_threshold')
            ->count();

        // Discrepancy 2: Active legal holds marked deletion_prevented = false
        $unprotectedHolds = DB::table('platform_document_legal_holds')
            ->where('is_legal_hold_active', true)
            ->where('deletion_prevented', false)
            ->count();

        $discrepancies = $unverifiedFinancialPosts + $unprotectedHolds;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_extractions' => DB::table('platform_document_extractions')->count(),
            'total_holds' => DB::table('platform_document_legal_holds')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
