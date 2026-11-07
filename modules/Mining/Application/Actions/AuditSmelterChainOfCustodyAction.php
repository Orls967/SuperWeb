<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;
use Modules\Mining\Domain\Models\MiningSmelterQualityAssay;

final class AuditSmelterChainOfCustodyAction
{
    /**
     * @return array{
     *     run_code: string,
     *     furnace_hmac_valid: bool,
     *     assay_number: ?string,
     *     assay_hmac_valid: bool,
     *     receipt_number: ?string,
     *     receipt_seal_valid: bool,
     *     is_chain_intact: bool,
     *     audit_log: list<string>
     * }
     */
    public function execute(MiningSmelterFurnaceRun $run): array
    {
        $log = [];

        // 1. Audit run signature
        $expectedRunSig = hash('sha256', "RUN:{$run->run_code}:{$run->ore_feed_wmt}");
        $furnaceValid = ! empty($run->hmac_signature) && hash_equals($expectedRunSig, (string) $run->hmac_signature);
        $log[] = $furnaceValid ? 'RUN_SIGNATURE_VERIFIED' : 'RUN_SIGNATURE_TAMPERED';

        // 2. Audit assay
        $assay = MiningSmelterQualityAssay::where('furnace_run_id', $run->id)->first();
        $assayValid = false;
        if ($assay) {
            $expectedAssaySig = hash('sha256', sprintf(
                'ASSAY:%s:%.3f:%.3f:%s',
                $assay->assay_number,
                (float) $assay->primary_metal_grade_pct,
                (float) $assay->iron_fe_pct,
                $assay->meets_lme_specification ? '1' : '0'
            ));
            $assayValid = ! empty($assay->hmac_signature) && hash_equals($expectedAssaySig, (string) $assay->hmac_signature);
            $log[] = $assayValid ? 'ASSAY_SIGNATURE_VERIFIED' : 'ASSAY_SIGNATURE_TAMPERED';
        } else {
            $log[] = 'ASSAY_RECORD_MISSING';
        }

        // 3. Audit warehouse receipt
        $receipt = $assay ? MiningMetalsWarehouseReceipt::where('assay_id', $assay->id)->first() : null;
        $receiptValid = false;
        if ($receipt) {
            $expectedReceiptSig = hash('sha256', "SRG:{$receipt->receipt_number}:{$receipt->max_financing_limit_idr}");
            $receiptValid = ! empty($receipt->hmac_signature) && hash_equals($expectedReceiptSig, (string) $receipt->hmac_signature);
            $log[] = $receiptValid ? 'SRG_SIGNATURE_VERIFIED' : 'SRG_SIGNATURE_TAMPERED';
        } else {
            $log[] = 'SRG_RECORD_OPTIONAL_OR_MISSING';
        }

        $intact = $furnaceValid && ($assay ? $assayValid : true) && ($receipt ? $receiptValid : true);

        return [
            'run_code' => $run->run_code,
            'furnace_hmac_valid' => $furnaceValid,
            'assay_number' => $assay?->assay_number,
            'assay_hmac_valid' => $assayValid,
            'receipt_number' => $receipt?->receipt_number,
            'receipt_seal_valid' => $receiptValid,
            'is_chain_intact' => $intact,
            'audit_log' => $log,
        ];
    }
}
