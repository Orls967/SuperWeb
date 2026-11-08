<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HtlMiceLead;
use Modules\Hotel\Domain\Services\HotelMiceSalesPipelineService;

final class CreateMiceProposalWithMarginGuardAction
{
    public function __construct(
        private readonly HotelMiceSalesPipelineService $pipelineService
    ) {}

    /**
     * @param  array<string, int>  $revenueBreakdown
     * @param  array<string, int>  $costBreakdown
     */
    public function execute(
        HtlMiceLead $lead,
        string $title,
        array $revenueBreakdown,
        array $costBreakdown,
        int $validityDays = 14,
        float $guardrailMinMargin = 40.0,
        string $operatorReference = 'USR-SALES-01'
    ): array {
        $result = $this->pipelineService->evaluateAndCreateProposal(
            lead: $lead,
            title: $title,
            revenueBreakdown: $revenueBreakdown,
            costBreakdown: $costBreakdown,
            validityDays: $validityDays,
            guardrailMinMargin: $guardrailMinMargin,
            operatorReference: $operatorReference
        );

        return $result;
    }
}
