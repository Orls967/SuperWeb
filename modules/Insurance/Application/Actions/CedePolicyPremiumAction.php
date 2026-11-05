<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Application\Services\CessionEngine;
use Modules\Insurance\Domain\Enums\CessionKind;
use Modules\Insurance\Domain\Models\CessionEvent;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;

/**
 * Mengalihkan premi polis ke program treaty aktif (D157-2) dan memutakhirkan kolom ceded/retained polis.
 */
final class CedePolicyPremiumAction
{
    public function __construct(
        private readonly CessionEngine $engine,
        private readonly RecordCessionAction $record,
        private readonly ClockInterface $clock,
    ) {}

    public function execute(int $policyId): CessionEvent
    {
        return DB::transaction(function () use ($policyId): CessionEvent {
            $policy = InsurancePolicyPenuh::query()->with('product')->whereKey($policyId)->lockForUpdate()->firstOrFail();

            $existing = CessionEvent::query()->where('event_key', "ins:cede:premium:{$policy->id}")->first();
            if ($existing !== null) {
                return $existing;
            }

            $plan = $this->engine->planPremium($policy);
            $event = $this->record->execute(
                eventKey: "ins:cede:premium:{$policy->id}",
                kind: CessionKind::Premium,
                plan: $plan,
                at: CarbonImmutable::instance($this->clock->now()),
                policyId: $policy->id,
            );

            $policy->forceFill([
                'ceded_premium_idr' => $plan->cededIdr(),
                'retained_premium_idr' => $plan->retainedIdr,
            ])->save();

            return $event;
        });
    }
}
