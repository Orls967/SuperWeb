<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Insurance\Application\Actions\IssueEmbeddedPolicyAction;
use Modules\Insurance\Contracts\EventCoverBinder;
use Modules\Insurance\Domain\Models\EmbedPolicy;
use Modules\Insurance\Domain\Models\EmbedProduct;
use Modules\Insurance\Domain\Models\InsuranceClaim;

final class EventCoverBinderService implements EventCoverBinder
{
    public function __construct(
        private readonly IssueEmbeddedPolicyAction $issueAction,
    ) {}

    public function bind(
        int $eventId,
        int $sumInsuredMinor,
        int $attendanceCap,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $idempotencyKey
    ): array {
        $product = EmbedProduct::firstOrCreate(
            ['code' => 'PROD-EVENT-WEATHER'],
            [
                'line_domain' => 'VENUE',
                'name' => 'Event Weather & Force Majeure Cancellation Cover',
                'coverage_type' => 'EVENT_CANCELLATION',
                'default_sum_insured_idr' => $sumInsuredMinor,
                'premium_rate_bps' => 150, // 1.5%
                'min_premium_idr' => 1_000_000,
                'is_active' => true,
            ]
        );

        $premiumMinor = max($product->min_premium_idr, intdiv($sumInsuredMinor * $product->premium_rate_bps, 10000));

        $policy = $this->issueAction->execute(
            eventKey: $idempotencyKey,
            productCode: $product->code,
            customerPartyId: (string) $eventId,
            contextType: 'EVENT',
            contextRef: (string) $eventId,
            customSumInsuredIdr: $sumInsuredMinor,
            brokerPartyId: null,
            startsAt: $from,
            expiresAt: $to
        );

        return [
            'policy_id' => $policy->id,
            'premium_minor' => $premiumMinor,
        ];
    }

    public function claimCancellation(
        int $policyId,
        string $reasonCode,
        int $lossMinor,
        string $idempotencyKey
    ): int {
        $policy = EmbedPolicy::find($policyId);
        $claimNo = 'CLM-EVT-'.strtoupper(substr(hash('sha256', "{$policyId}:{$idempotencyKey}"), 0, 10));

        $claim = InsuranceClaim::firstOrCreate(
            ['claim_number' => $claimNo],
            [
                'policy_id' => $policyId,
                'claimant_party_id' => $policy ? $policy->customer_party_id : 'VENUE-EVENT',
                'loss_date' => now(),
                'cause_of_loss' => $reasonCode,
                'claimed_amount_idr' => $lossMinor,
                'approved_amount_idr' => $lossMinor,
                'status' => 'APPROVED',
            ]
        );

        return $claim->id;
    }
}
