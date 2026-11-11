<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Actions;

use Modules\EnterpriseFinance\Application\Services\GoodwillImpairmentAssessmentEngine;
use Modules\EnterpriseFinance\Domain\DTOs\GoodwillImpairmentResultDTO;

/**
 * Aksi Penelaahan Tahunan Penurunan Nilai Goodwill (54.8 - PSAK 48 / IAS 36).
 * Menguji apakah nilai tercatat goodwill pada Unit Penghasil Kas (CGU) mengalami penurunan
 * dibandingkan nilai pakai berbasis arus kas terdiskonto (Discounted Cash Flow).
 */
class ConductAnnualGoodwillImpairmentReviewAction
{
    public function __construct(
        protected readonly GoodwillImpairmentAssessmentEngine $impairmentEngine
    ) {}

    public function execute(
        string $cguCode,
        int $carryingAmountWithoutGoodwillMinor,
        int $carryingGoodwillMinor,
        array $projectedCashFlowsMinor,
        int $discountRateBps = 1000,
        int $terminalGrowthRateBps = 200
    ): GoodwillImpairmentResultDTO {
        $result = $this->impairmentEngine->assessImpairment(
            cguCode: $cguCode,
            carryingAmountWithoutGoodwillMinor: $carryingAmountWithoutGoodwillMinor,
            carryingGoodwillMinor: $carryingGoodwillMinor,
            projectedCashFlowsMinor: $projectedCashFlowsMinor,
            discountRateBps: $discountRateBps,
            terminalGrowthRateBps: $terminalGrowthRateBps
        );

        return new GoodwillImpairmentResultDTO(
            cguCode: $result['cgu_code'],
            carryingAmountTotalMinor: $result['carrying_amount_total_minor'],
            carryingGoodwillMinor: $result['carrying_goodwill_minor'],
            valueInUseMinor: $result['value_in_use_minor'],
            impairmentLossMinor: $result['impairment_loss_minor'],
            adjustedGoodwillMinor: $result['adjusted_goodwill_minor'],
            isImpaired: $result['is_impaired'],
            accountingTreatment: $result['accounting_treatment']
        );
    }
}
