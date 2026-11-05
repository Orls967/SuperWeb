<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Modules\Insurance\Domain\Models\BlacklistedEntity;
use Modules\Insurance\Domain\Models\EmbedPolicy;
use Modules\Insurance\Domain\Models\MarketCommission;
use Modules\Insurance\Domain\Models\ParametricEvent;
use Modules\Insurance\Domain\Models\SiuInvestigation;

final class EmbeddedAuditor
{
    /**
     * @return array{clean: bool, violations: list<string>, stats: array<string, int>}
     */
    public function audit(): array
    {
        $violations = [];

        // 1. Audit Invarian Komisi Broker
        $policiesWithBroker = EmbedPolicy::query()
            ->whereNotNull('broker_party_id')
            ->where('broker_commission_idr', '>', 0)
            ->get();

        foreach ($policiesWithBroker as $pol) {
            $sumComm = MarketCommission::query()
                ->where('embedded_policy_id', $pol->id)
                ->sum('commission_amount_idr');

            if ($sumComm !== $pol->broker_commission_idr) {
                $violations[] = sprintf(
                    'Polis embedded %s: komisi tercatat di polis (Rp %s) tidak cocok dengan akumulasi komisi broker (Rp %s).',
                    $pol->policy_number,
                    number_format($pol->broker_commission_idr),
                    number_format($sumComm)
                );
            }
        }

        // 2. Audit Invarian Parametrik
        $events = ParametricEvent::all();
        foreach ($events as $ev) {
            if ($ev->policies_paid > $ev->policies_evaluated) {
                $violations[] = sprintf(
                    'Event parametrik %s: jumlah polis dibayar (%d) melebihi jumlah dievaluasi (%d).',
                    $ev->event_key,
                    $ev->policies_paid,
                    $ev->policies_evaluated
                );
            }
        }

        // 3. Audit Invarian Blacklist SIU
        $confirmedSiu = SiuInvestigation::query()->where('status', 'confirmed_fraud')->get();
        foreach ($confirmedSiu as $siu) {
            $hasBlacklist = BlacklistedEntity::query()->where('source_case_number', $siu->case_number)->exists();
            if (! $hasBlacklist) {
                $violations[] = sprintf(
                    'Kasus SIU %s: status confirmed_fraud tapi tidak tercatat di daftar hitam industri.',
                    $siu->case_number
                );
            }
        }

        return [
            'clean' => $violations === [],
            'violations' => $violations,
            'stats' => [
                'policies_audited' => EmbedPolicy::count(),
                'commissions_audited' => MarketCommission::count(),
                'events_audited' => ParametricEvent::count(),
                'siu_cases_audited' => SiuInvestigation::count(),
            ],
        ];
    }
}
