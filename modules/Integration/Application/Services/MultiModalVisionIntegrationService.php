<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * MultiModalVisionIntegrationService (Fase 351)
 *
 * Implements:
 *  - 351.1 Document understanding: extraction with source document linkage and human verification
 *  - 351.2 Vision inspection: confidence scoring with human confirmation for consequential actions
 *  - 351.4 Tests: Low-confidence requires human review; source document linkage preserved
 *  - 351.5 Edge case: Financial document extractions strictly require human verification before posting to ledger
 *  - 351.6 Risk: Erroneous AI extraction prevented from polluting general ledger
 */
class MultiModalVisionIntegrationService
{
    /**
     * Process financial document extraction and post to ledger with mandatory human verification guard (351.1, 351.4, 351.5 Edge Case).
     */
    public function extractAndPostFinancialDocument(
        string $extractionCode,
        string $docType,
        string $sourceUrl,
        float $extractedAmountUsd,
        bool $humanVerified,
        bool $postToLedger = false
    ): object {
        $eCode = strtoupper($extractionCode);
        $dType = strtoupper($docType);

        // Edge case 351.5: Extractions require human verification before posting to financial ledger
        if ($postToLedger && ! $humanVerified) {
            throw new InvalidArgumentException("Financial posting violation: Extracted document fields must be human-verified before posting to financial ledger (351.5).");
        }

        $id = DB::table('multimodal_financial_document_extractions')->insertGetId([
            'extraction_code' => $eCode,
            'document_type' => $dType,
            'source_document_url' => $sourceUrl,
            'extracted_amount_usd' => $extractedAmountUsd,
            'human_verified_material_fields' => $humanVerified,
            'posted_to_financial_ledger' => $postToLedger,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('multimodal_financial_document_extractions')->find($id);
    }

    /**
     * Record vision inspection and execute consequential action requiring human review on low confidence (351.2 & 351.4).
     */
    public function executeVisionInspectionAction(
        string $inspectionCode,
        string $inspectionType,
        float $confidenceScore,
        bool $humanConfirmed,
        float $threshold = 0.8500
    ): object {
        $iCode = strtoupper($inspectionCode);
        $tType = strtoupper($inspectionType);

        // Core gate 351.4: Low confidence (< threshold) requires human confirmation for consequential actions
        $isLowConfidence = ($confidenceScore < $threshold);
        if ($isLowConfidence && ! $humanConfirmed) {
            throw new InvalidArgumentException("Vision inspection safety breach: Low confidence detection ({$confidenceScore} < {$threshold}) requires human confirmation (351.4).");
        }

        $id = DB::table('multimodal_vision_inspection_events')->insertGetId([
            'inspection_code' => $iCode,
            'inspection_type' => $tType,
            'confidence_score' => $confidenceScore,
            'confidence_threshold' => $threshold,
            'human_confirmation_obtained' => $humanConfirmed,
            'consequential_action_taken' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('multimodal_vision_inspection_events')->find($id);
    }

    /**
     * AI Multi-Modal Platform Audit (`ai:audit`) (351.4, 351.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Financial documents posted to ledger without human verification
        $unverifiedLedgerPosts = DB::table('multimodal_financial_document_extractions')
            ->where('posted_to_financial_ledger', true)
            ->where('human_verified_material_fields', false)
            ->count();

        // Discrepancy 2: Consequential actions taken on low confidence without human confirmation
        $unconfirmedVisionActions = DB::table('multimodal_vision_inspection_events')
            ->where('consequential_action_taken', true)
            ->whereRaw('confidence_score < confidence_threshold')
            ->where('human_confirmation_obtained', false)
            ->count();

        $discrepancies = $unverifiedLedgerPosts + $unconfirmedVisionActions;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_extractions' => DB::table('multimodal_financial_document_extractions')->count(),
            'total_inspections' => DB::table('multimodal_vision_inspection_events')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
