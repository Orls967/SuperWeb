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
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\EmbedPolicy;
use Modules\Insurance\Domain\Models\ParametricEvent;
use Modules\Insurance\Domain\Models\ParametricTrigger;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

final class ExecuteParametricTriggerAction
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly IdempotentRequestGuard $idempotency,
        private readonly ?Ledger $ledger = null,
    ) {}

    /**
     * @param  array<string, mixed>  $oraclePayload
     */
    public function execute(
        string $eventKey,
        string $triggerCode,
        int $observedValue,
        array $oraclePayload,
        ?string $oracleSignature = null,
    ): ParametricEvent {
        $payload = [
            'trigger' => $triggerCode,
            'observed' => $observedValue,
        ];

        $event = $this->idempotency->run(
            key: $eventKey,
            scope: 'ins:param:execute',
            payload: $payload,
            actorId: null,
            operation: function () use (
                $eventKey,
                $triggerCode,
                $observedValue,
                $oraclePayload,
                $oracleSignature
            ) {
                return DB::transaction(function () use (
                    $eventKey,
                    $triggerCode,
                    $observedValue,
                    $oraclePayload,
                    $oracleSignature
                ) {
                    $trigger = ParametricTrigger::query()->where('trigger_code', $triggerCode)->first();
                    if ($trigger === null || ! $trigger->is_active) {
                        throw new DomainException("Trigger parametrik {$triggerCode} tidak ditemukan atau non-aktif.");
                    }

                    $isTriggered = match ($trigger->comparison_operator) {
                        '<' => $observedValue < $trigger->threshold_value,
                        '<=' => $observedValue <= $trigger->threshold_value,
                        '>' => $observedValue > $trigger->threshold_value,
                        '>=' => $observedValue >= $trigger->threshold_value,
                        default => false,
                    };

                    if (! $isTriggered) {
                        throw new DomainException(sprintf(
                            'Kondisi parametrik tidak terpenuhi: nilai observasi %d tidak %s %d.',
                            $observedValue,
                            $trigger->comparison_operator,
                            $trigger->threshold_value
                        ));
                    }

                    $now = CarbonImmutable::instance($this->clock->now());

                    // Cari seluruh polis embedded aktif yang relevan dengan region/plot ini
                    $policies = EmbedPolicy::query()
                        ->where('status', 'active')
                        ->where(function ($query) use ($trigger): void {
                            $query->where('context_ref', 'LIKE', '%'.$trigger->region_or_plot.'%')
                                ->orWhereHas('product', function ($pQuery): void {
                                    $pQuery->where('coverage_type', 'PARAMETRIC');
                                });
                        })
                        ->get();

                    $totalPayout = 0;
                    $paidCount = 0;

                    foreach ($policies as $pol) {
                        $payout = (int) intdiv($pol->sum_insured_idr * $trigger->payout_percentage_bps, 10000);
                        $totalPayout += $payout;
                        $paidCount++;
                        $pol->update(['status' => 'claimed']);
                    }

                    $txId = null;
                    if ($this->ledger !== null && $totalPayout > 0) {
                        $lines = Posting::lines();
                        $lines->debit('ins:claim_expense:IDR', $totalPayout);
                        $lines->credit('ins:claim_clearing:IDR', $totalPayout);

                        $tx = $this->ledger->post(new PostingDTO(
                            type: InsuranceTransactionTypes::INSURANCE_CLAIM_PAYOUT->value,
                            description: "Pembayaran santunan parametrik otomatis {$triggerCode} ({$paidCount} polis)",
                            idempotencyKey: 'param:payout:'.$eventKey,
                            entries: $lines->toEntries('IDR'),
                            referenceType: 'ins_param_event',
                            referenceId: $eventKey,
                        ));
                        $txId = (int) $tx->id;
                    }

                    return ParametricEvent::create([
                        'event_key' => $eventKey,
                        'trigger_id' => $trigger->id,
                        'observed_value' => $observedValue,
                        'oracle_payload' => $oraclePayload,
                        'oracle_signature' => $oracleSignature,
                        'policies_evaluated' => $policies->count(),
                        'policies_paid' => $paidCount,
                        'total_payout_idr' => $totalPayout,
                        'triggered_at' => $now,
                        'ledger_transaction_id' => $txId,
                    ]);
                });
            });

        return $event ?? ParametricEvent::query()->where('event_key', $eventKey)->firstOrFail();
    }
}
