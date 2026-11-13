<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\ControlTower\Domain\Models\SupplierScorecardSnapshot;
use Modules\ControlTower\Domain\Services\SupplierScorecardService;
use Modules\Core\Contracts\ClockInterface;

/**
 * Orkestrasi perhitungan dan persistensi idempoten kartu skor pemasok per supplier_ref + period.
 */
final class SupplierScorecardOrchestratorService
{
    public function __construct(
        private readonly SupplierScorecardService $scorecardService,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Hitung dan simpan kartu skor secara idempoten per (supplier_ref, period).
     *
     * @return Collection<int, SupplierScorecardSnapshot>
     */
    public function generateForPeriod(
        ?string $period = null,
        ?string $supplierRef = null,
        int $historyMonths = 6,
    ): Collection {
        $now = $this->clock->now();
        $targetPeriod = ($period !== null && trim($period) !== '') ? trim($period) : $now->format('Y-m');
        $asOfDate = $now->toDateString();

        if ($supplierRef !== null && trim($supplierRef) !== '') {
            $evaluated = [$this->scorecardService->evaluateSupplier(
                supplierRef: trim($supplierRef),
                supplierName: '',
                asOfDate: $asOfDate,
                historyMonths: $historyMonths,
            )];
        } else {
            $evaluated = $this->scorecardService->evaluateAll(
                asOfDate: $asOfDate,
                historyMonths: $historyMonths,
            );
        }

        $persisted = new Collection;

        foreach ($evaluated as $card) {
            $snapshot = SupplierScorecardSnapshot::query()->updateOrCreate(
                [
                    'supplier_ref' => $card['supplier_ref'],
                    'period' => $targetPeriod,
                ],
                [
                    'supplier_name' => $card['supplier_name'],
                    'composite_score' => $card['composite_score'],
                    'otd_score' => $card['otd_score'],
                    'reliability_score' => $card['reliability_score'],
                    'volume_score' => $card['volume_score'],
                    'on_time_permil' => $card['on_time_permil'],
                    'mean_lead_time_days' => $card['mean_lead_time_days'],
                    'sigma_lead_time_days' => $card['sigma_lead_time_days'],
                    'samples_count' => $card['samples_count'],
                    'tier' => $card['tier'],
                    'trend_json' => [
                        'direction' => $card['trend_direction'],
                        'history' => $card['monthly_history'],
                    ],
                    'action_recommendation' => $card['action_recommendation'],
                    'computed_at' => $now,
                ],
            );

            $persisted->push($snapshot);
        }

        return $persisted;
    }
}
