<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Application\Support\InsuranceNumbering;
use Modules\Insurance\Domain\Models\EmbedPolicy;
use Modules\Insurance\Domain\Models\EmbedProduct;
use Modules\Insurance\Domain\Models\MarketBroker;
use Modules\Insurance\Domain\Models\MarketCommission;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

final class IssueEmbeddedPolicyAction
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly IdempotentRequestGuard $idempotency,
        private readonly DocumentNumberingInterface $numbering,
        private readonly ?Ledger $ledger = null,
    ) {}

    public function execute(
        string $eventKey,
        string $productCode,
        string $customerPartyId,
        string $contextType,
        string $contextRef,
        ?int $customSumInsuredIdr = null,
        ?string $brokerPartyId = null,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $expiresAt = null,
    ): EmbedPolicy {
        $payload = [
            'product_code' => $productCode,
            'customer' => $customerPartyId,
            'context' => "{$contextType}:{$contextRef}",
        ];

        $policy = $this->idempotency->run(
            key: $eventKey,
            scope: 'ins:embedded:issue',
            payload: $payload,
            actorId: null,
            operation: function () use (
                $eventKey,
                $productCode,
                $customerPartyId,
                $contextType,
                $contextRef,
                $customSumInsuredIdr,
                $brokerPartyId,
                $startsAt,
                $expiresAt
            ) {
                return DB::transaction(function () use (
                    $eventKey,
                    $productCode,
                    $customerPartyId,
                    $contextType,
                    $contextRef,
                    $customSumInsuredIdr,
                    $brokerPartyId,
                    $startsAt,
                    $expiresAt
                ) {
                    $product = EmbedProduct::query()->where('code', $productCode)->first();
                    if ($product === null || ! $product->is_active) {
                        throw new DomainException("Produk embedded {$productCode} tidak ditemukan atau tidak aktif.");
                    }

                    $sumInsured = $customSumInsuredIdr ?? $product->default_sum_insured_idr;
                    $calculatedPremium = (int) intdiv($sumInsured * $product->premium_rate_bps, 10000);
                    $premium = max($calculatedPremium, $product->min_premium_idr);

                    $now = CarbonImmutable::instance($this->clock->now());
                    $start = $startsAt ?? $now;
                    $expire = $expiresAt ?? $start->addYear();

                    $broker = null;
                    $commissionIdr = 0;
                    if ($brokerPartyId !== null) {
                        $broker = MarketBroker::query()->where('party_id', $brokerPartyId)->first();
                        if ($broker !== null && $broker->status === 'active') {
                            $commissionIdr = (int) intdiv($premium * $broker->commission_rate_bps, 10000);
                        }
                    }

                    $policyNumber = InsuranceNumbering::next($this->numbering, 'POL-EMB-'.strtoupper($product->line_domain), 6);

                    $policy = EmbedPolicy::create([
                        'policy_number' => $policyNumber,
                        'embedded_product_id' => $product->id,
                        'customer_party_id' => $customerPartyId,
                        'broker_party_id' => $brokerPartyId,
                        'context_type' => $contextType,
                        'context_ref' => $contextRef,
                        'sum_insured_idr' => $sumInsured,
                        'premium_idr' => $premium,
                        'broker_commission_idr' => $commissionIdr,
                        'status' => 'active',
                        'starts_at' => $start,
                        'expires_at' => $expire,
                    ]);

                    if ($broker !== null && $commissionIdr > 0) {
                        MarketCommission::create([
                            'broker_id' => $broker->id,
                            'embedded_policy_id' => $policy->id,
                            'premium_idr' => $premium,
                            'commission_bps' => $broker->commission_rate_bps,
                            'commission_amount_idr' => $commissionIdr,
                            'status' => 'pending',
                        ]);

                        $broker->increment('total_gross_written_idr', $premium);
                        $broker->increment('total_commission_earned_idr', $commissionIdr);
                    }

                    // Jurnal ledger premi asuransi embedded
                    if ($this->ledger !== null) {
                        $lines = Posting::lines();
                        $lines->debit('ins:reserve_pool:IDR', $premium);
                        $lines->credit('ins:unearned_premium:IDR', $premium);

                        $tx = $this->ledger->post(new PostingDTO(
                            type: InsuranceTransactionTypes::INSURANCE_PREMIUM->value,
                            description: "Penerimaan premi embedded {$policyNumber} ({$product->name})",
                            idempotencyKey: 'emb:pol:prem:'.$eventKey,
                            entries: $lines->toEntries('IDR'),
                            referenceType: 'ins_embed_policy',
                            referenceId: (string) $policy->id,
                        ));
                        $policy->update(['ledger_transaction_id' => $tx->id]);
                    }

                    return $policy;
                });
            });

        return $policy ?? EmbedPolicy::query()->where('context_type', $contextType)->where('context_ref', $contextRef)->latest('id')->firstOrFail();
    }
}
