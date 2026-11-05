<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Application\Services\TreatyCatalog;
use Modules\Insurance\Domain\DTOs\CessionLineData;
use Modules\Insurance\Domain\DTOs\CessionPlan;
use Modules\Insurance\Domain\Enums\CessionKind;
use Modules\Insurance\Domain\Enums\TreatyType;
use Modules\Insurance\Domain\Models\CessionEvent;
use Modules\Insurance\Domain\Models\InsuranceClaimPenuh;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;
use Modules\Insurance\Domain\Models\ReinsuranceTreaty;
use Modules\Shared\Domain\Algorithms\Reinsurance\ProgramAllocator;

final class CedePeriodAction
{
    public function __construct(
        private readonly TreatyCatalog $catalog,
        private readonly ProgramAllocator $allocator,
        private readonly RecordCessionAction $record,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @return list<CessionEvent>
     */
    public function execute(string $periodYm): array
    {
        $now = CarbonImmutable::instance($this->clock->now());
        $activeTreaties = ReinsuranceTreaty::query()
            ->with('panel')
            ->where('status', 'active')
            ->where('type', TreatyType::StopLoss)
            ->get();

        $events = [];

        foreach ($activeTreaties as $treaty) {
            $eventKey = "ins:cede:stoploss:{$treaty->id}:{$periodYm}";
            $existing = CessionEvent::query()->where('event_key', $eventKey)->first();
            if ($existing !== null) {
                $events[] = $existing;

                continue;
            }

            // Hitung earned premium dan incurred claim untuk lini treaty
            $earnedPremium = (int) InsurancePolicyPenuh::query()
                ->join('ins_full_products as p', 'p.id', '=', 'ins_full_policies.product_id')
                ->where('p.line_category', $treaty->line_category)
                ->where('ins_full_policies.status', 'active')
                ->sum('retained_premium_idr');

            $incurredLoss = (int) InsuranceClaimPenuh::query()
                ->join('ins_full_policies as pol', 'pol.id', '=', 'ins_full_claims.policy_id')
                ->join('ins_full_products as prod', 'prod.id', '=', 'pol.product_id')
                ->where('prod.line_category', $treaty->line_category)
                ->sum('approved_amount_idr');

            if ($earnedPremium <= 0 || $incurredLoss <= 0) {
                continue;
            }

            $recoverable = $this->allocator->stopLoss(
                earnedPremiumIdr: $earnedPremium,
                incurredIdr: $incurredLoss,
                attachmentLrBps: $treaty->sl_attachment_lr_bps,
                limitLrBps: $treaty->sl_limit_lr_bps
            );

            if ($recoverable <= 0) {
                continue;
            }

            // Alokasikan recoverable ke panel treaty
            $lines = [];
            foreach ($treaty->panel as $panelMember) {
                $shareCeded = (int) intdiv($recoverable * $panelMember->share_bps, 10000);
                $lines[] = new CessionLineData(
                    treatyId: $treaty->id,
                    reinsurerPartyId: $panelMember->reinsurer_party_id,
                    layerCode: 'SL',
                    cededIdr: $shareCeded,
                    commissionIdr: 0,
                    reinstatementPremiumIdr: 0
                );
            }

            $plan = new CessionPlan(
                grossIdr: $incurredLoss,
                retainedIdr: max(0, $incurredLoss - $recoverable),
                lines: $lines,
                snapshot: [
                    'earned_premium_idr' => $earnedPremium,
                    'incurred_loss_idr' => $incurredLoss,
                    'recoverable_idr' => $recoverable,
                ]
            );

            $event = $this->record->execute(
                eventKey: $eventKey,
                kind: CessionKind::StopLoss,
                plan: $plan,
                at: $now,
                treatyId: $treaty->id
            );

            $events[] = $event;
        }

        return $events;
    }
}
