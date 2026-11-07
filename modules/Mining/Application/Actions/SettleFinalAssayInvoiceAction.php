<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use DomainException;
use Modules\Mining\Domain\Models\MiningSmelterQualityAssay;
use Modules\Mining\Ledger\MiningFase121LedgerPostingService;
use Modules\Shared\Domain\Algorithms\MetalsTrading\AssaySplitComparison;
use Modules\Shared\Domain\Algorithms\MetalsTrading\MetalsAssayInvoiceSettlementCalculator;
use Modules\Shared\Domain\Algorithms\MetalsTrading\MetalsAssayInvoiceSettlementResult;
use Modules\Shared\Domain\Algorithms\MetalsTrading\MetalsAssayInvoiceSettlementVerifier;

final class SettleFinalAssayInvoiceAction
{
    public function __construct(
        private readonly MetalsAssayInvoiceSettlementCalculator $calculator,
        private readonly MetalsAssayInvoiceSettlementVerifier $verifier,
        private readonly MiningFase121LedgerPostingService $ledgerPostingService,
    ) {}

    public function execute(string $lotNumber, AssaySplitComparison $comparison): MetalsAssayInvoiceSettlementResult
    {
        $assay = MiningSmelterQualityAssay::query()->where('lot_number', $lotNumber)->first();
        if (! $assay) {
            throw new DomainException("Sertifikat assay mutu untuk lot {$lotNumber} tidak ditemukan.");
        }

        $result = $this->calculator->calculate($comparison);

        $isValid = $this->verifier->verify($result, $comparison);
        if (! $isValid) {
            throw new DomainException("Verifikasi hasil settlement assay lot {$lotNumber} gagal.");
        }

        // Post ledger adjustment
        $journalId = $this->ledgerPostingService->postFinalAssayAdjustment(
            lotCode: $lotNumber,
            differentialIdr: $result->settlementDifferentialIdr,
            sellerUmpireCostIdr: $result->sellerUmpireCostAllocationIdr,
            description: "Settlement invoice final assay lot {$lotNumber} (grade agreed: {$result->agreedFinalNiGradePct}%)"
        );

        $meta = (array) ($assay->metadata ?? []);
        $meta['final_assay_settlement'] = $result->toArray();
        $meta['final_settlement_journal_id'] = $journalId;
        $meta['is_umpire_resolved'] = $result->isUmpireResolved;
        $meta['umpire_fee_bearing_party'] = $result->umpireFeeBearingParty;

        $assay->update([
            'status' => 'FINAL_SETTLED',
            'metadata' => $meta,
        ]);

        return $result;
    }
}
