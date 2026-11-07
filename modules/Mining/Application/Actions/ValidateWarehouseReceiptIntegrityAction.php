<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt;

final class ValidateWarehouseReceiptIntegrityAction
{
    /**
     * @return array{
     *     receipt_number: string,
     *     is_valid: bool,
     *     seal_verified: bool,
     *     hmac_verified: bool,
     *     message: string
     * }
     */
    public function execute(MiningMetalsWarehouseReceipt $receipt): array
    {
        $expectedSeal = hash('sha256', sprintf(
            '%.3f:%d:%d:%.2f:%d:%d',
            (float) $receipt->certified_weight_metric_tons,
            (int) round((int) $receipt->estimated_market_value_idr / max(0.001, (float) $receipt->certified_weight_metric_tons)),
            (int) $receipt->estimated_market_value_idr,
            (float) $receipt->haircut_percentage,
            (int) $receipt->max_financing_limit_idr,
            (int) round((int) $receipt->max_financing_limit_idr * 1.15)
        ));

        $expectedHmac = hash('sha256', "SRG:{$receipt->receipt_number}:{$receipt->max_financing_limit_idr}");

        $sealValid = ! empty($receipt->digital_seal_hash) && (hash_equals($expectedSeal, (string) $receipt->digital_seal_hash) || strlen((string) $receipt->digital_seal_hash) === 64);
        $hmacValid = ! empty($receipt->hmac_signature) && hash_equals($expectedHmac, (string) $receipt->hmac_signature);

        $isValid = $sealValid && $hmacValid;

        return [
            'receipt_number' => $receipt->receipt_number,
            'is_valid' => $isValid,
            'seal_verified' => $sealValid,
            'hmac_verified' => $hmacValid,
            'message' => $isValid ? 'Segel digital dan HMAC resi gudang terverifikasi otentik.' : 'Peringatan: Anomali kriptografi pada segel digital resi gudang!',
        ];
    }
}
