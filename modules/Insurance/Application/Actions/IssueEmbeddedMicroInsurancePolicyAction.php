<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsuranceProduct;
use Modules\Shared\Application\BaseAction;

class IssueEmbeddedMicroInsurancePolicyAction extends BaseAction
{
    /**
     * Issue an embedded micro-insurance policy at point-of-sale / transaction.
     *
     * @return array{policy: InsurancePolicy, policy_number: string, premium_paid_idr: int, coverage_limit_idr: int}
     */
    public function handle(
        int $productId,
        int $userId,
        string $subjectRefType,
        string $subjectRefId,
        int $durationDays = 30
    ): array {
        return $this->transaction(function () use (
            $productId,
            $userId,
            $subjectRefType,
            $subjectRefId,
            $durationDays
        ) {
            $product = InsuranceProduct::where('id', $productId)->firstOrFail();

            $policyNumber = 'POL-'.strtoupper(substr($product->product_code, 0, 4)).'-'.date('YmdHis').'-'.rand(100, 999);
            $idempotencyKey = hash('sha256', "ins:policy:issue:{$productId}:{$userId}:{$subjectRefType}:{$subjectRefId}");

            $startsAt = now();
            $expiresAt = $startsAt->copy()->addDays($durationDays);

            $policy = InsurancePolicy::create([
                'policy_number' => $policyNumber,
                'product_id' => $productId,
                'user_id' => $userId,
                'subject_ref_type' => $subjectRefType,
                'subject_ref_id' => $subjectRefId,
                'premium_paid_idr' => $product->premium_amount_idr,
                'coverage_limit_idr' => $product->max_payout_idr,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
            ]);

            $this->audit(
                action: 'insurance.policy.issued',
                auditable: $policy,
                oldValues: null,
                newValues: [
                    'policy_number' => $policyNumber,
                    'product_code' => $product->product_code,
                    'premium_paid_idr' => $product->premium_amount_idr,
                    'coverage_limit_idr' => $product->max_payout_idr,
                ],
                correlationId: "ins_pol_{$policyNumber}",
                impactType: 'embedded_underwriting'
            );

            $this->outbox(
                eventType: 'InsurancePolicyIssued',
                payload: [
                    'policy_id' => $policy->id,
                    'policy_number' => $policyNumber,
                    'user_id' => $userId,
                    'premium_paid_idr' => $product->premium_amount_idr,
                    'coverage_limit_idr' => $product->max_payout_idr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'policy' => $policy,
                'policy_number' => $policyNumber,
                'premium_paid_idr' => $product->premium_amount_idr,
                'coverage_limit_idr' => $product->max_payout_idr,
            ];
        });
    }
}
