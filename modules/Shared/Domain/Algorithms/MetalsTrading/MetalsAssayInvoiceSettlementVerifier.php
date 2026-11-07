<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

final class MetalsAssayInvoiceSettlementVerifier
{
    public function verify(MetalsAssayInvoiceSettlementResult $result, AssaySplitComparison $input): bool
    {
        $expectedDiff = $result->finalPayableValueIdr - $result->provisionalInvoiceIdr;
        if ($expectedDiff !== $result->settlementDifferentialIdr) {
            return false;
        }

        if (($result->sellerUmpireCostAllocationIdr + $result->buyerUmpireCostAllocationIdr) !== ($result->isUmpireResolved ? $input->umpireServiceFeeIdr : 0)) {
            return false;
        }

        $expectedHash = hash('sha256', sprintf(
            '%.3f:%d:%d:%d:%d:%s',
            $result->agreedFinalNiGradePct,
            $result->provisionalInvoiceIdr,
            $result->finalPayableValueIdr,
            $result->settlementDifferentialIdr,
            $result->sellerUmpireCostAllocationIdr + $result->buyerUmpireCostAllocationIdr,
            $result->umpireFeeBearingParty
        ));

        return hash_equals($expectedHash, $result->verificationHash);
    }
}
