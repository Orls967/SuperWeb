<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Application\Services\CessionEngine;
use Modules\Insurance\Domain\Enums\CessionKind;
use Modules\Insurance\Domain\Models\CessionEvent;
use Modules\Insurance\Domain\Models\InsuranceClaimPenuh;

/**
 * Mengalihkan klaim terbayar ke program treaty: proporsional lalu XoL per-risk (D157-2) dan
 * memutakhirkan recoverable & pembayaran neto pada klaim.
 */
final class CedeClaimAction
{
    public function __construct(
        private readonly CessionEngine $engine,
        private readonly RecordCessionAction $record,
        private readonly ClockInterface $clock,
    ) {}

    public function execute(int $claimId): CessionEvent
    {
        return DB::transaction(function () use ($claimId): CessionEvent {
            $claim = InsuranceClaimPenuh::query()->with('policy.product')->whereKey($claimId)->lockForUpdate()->firstOrFail();

            $existing = CessionEvent::query()->where('event_key', "ins:cede:claim:{$claim->id}")->first();
            if ($existing !== null) {
                return $existing;
            }

            $grossLoss = (int) $claim->approved_amount_idr - (int) $claim->subrogation_recovered_idr;
            $plan = $this->engine->planClaim($claim, $grossLoss);

            $event = $this->record->execute(
                eventKey: "ins:cede:claim:{$claim->id}",
                kind: CessionKind::Claim,
                plan: $plan,
                at: CarbonImmutable::instance($this->clock->now()),
                policyId: $claim->policy_id,
                claimId: $claim->id,
            );

            $claim->forceFill([
                'reinsurance_recovered_idr' => $plan->cededIdr(),
                'net_payout_idr' => $plan->retainedIdr,
            ])->save();

            return $event;
        });
    }
}
