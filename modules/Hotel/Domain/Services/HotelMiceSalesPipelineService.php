<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Algorithms\EventMarginCalculator;
use Modules\Hotel\Domain\Models\HtlEventProposal;
use Modules\Hotel\Domain\Models\HtlMiceLead;

final class HotelMiceSalesPipelineService
{
    public function __construct(
        private readonly EventMarginCalculator $marginCalculator
    ) {}

    /**
     * @param  array<string, int>  $revenueBreakdown
     * @param  array<string, int>  $costBreakdown
     */
    public function evaluateAndCreateProposal(
        HtlMiceLead $lead,
        string $title,
        array $revenueBreakdown,
        array $costBreakdown,
        int $validityDays = 14,
        float $guardrailMinMargin = 40.0,
        string $operatorReference = 'USR-SALES-01'
    ): array {
        $proposalCode = 'PROP-'.strtoupper(bin2hex(random_bytes(4)));

        $certificate = $this->marginCalculator->calculate(
            proposalId: $proposalCode,
            revenueBreakdown: $revenueBreakdown,
            costBreakdown: $costBreakdown,
            guardrailMinMarginPercent: $guardrailMinMargin
        );

        $now = new DateTimeImmutable;
        $expiresAt = $now->modify("+{$validityDays} days");

        $proposal = HtlEventProposal::create([
            'id' => (string) Str::uuid(),
            'proposal_code' => $proposalCode,
            'lead_id' => $lead->id,
            'title' => $title,
            'hall_rental_price_idr' => $revenueBreakdown['hall_rental'] ?? 0,
            'fnb_price_idr' => $revenueBreakdown['fnb'] ?? 0,
            'av_price_idr' => $revenueBreakdown['av'] ?? 0,
            'room_price_idr' => $revenueBreakdown['room'] ?? 0,
            'booth_price_idr' => $revenueBreakdown['booth'] ?? 0,
            'other_price_idr' => $revenueBreakdown['other'] ?? 0,
            'total_revenue_idr' => $certificate->totalRevenueIdr,
            'food_cogs_idr' => $costBreakdown['food_cogs'] ?? 0,
            'bev_cogs_idr' => $costBreakdown['bev_cogs'] ?? 0,
            'labor_cost_idr' => $costBreakdown['labor'] ?? 0,
            'av_cost_idr' => $costBreakdown['av'] ?? 0,
            'utility_cost_idr' => $costBreakdown['utility'] ?? 0,
            'other_cost_idr' => $costBreakdown['other'] ?? 0,
            'total_direct_cost_idr' => $certificate->totalDirectCostIdr,
            'gross_margin_percent' => $certificate->grossMarginPercent,
            'status' => $certificate->isCompliant ? 'APPROVED_OFFER' : 'GUARDRAIL_BREACH',
            'timelock_expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'signature_hash' => $certificate->signatureHash,
            'created_by_reference' => $operatorReference,
        ]);

        return [
            'proposal' => $proposal,
            'certificate' => $certificate,
        ];
    }

    public function checkTimelockValidity(HtlEventProposal $proposal): bool
    {
        if ($proposal->timelock_expires_at === null) {
            return true;
        }

        $now = new DateTimeImmutable;

        return $now <= $proposal->timelock_expires_at;
    }
}
