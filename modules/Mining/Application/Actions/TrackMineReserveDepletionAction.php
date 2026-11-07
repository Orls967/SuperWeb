<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Services\MineExplorationReserveDepletionTracker;

class TrackMineReserveDepletionAction
{
    public function __construct(
        private readonly MineExplorationReserveDepletionTracker $tracker
    ) {}

    /**
     * Lacak dan rekonsiliasi deplesi cadangan tambang KCMI/JORC (D94-4, D94-6).
     *
     * @param array{
     *     site_id: string,
     *     pit_code: string,
     *     initial_proved_reserves_tons: int,
     *     initial_probable_reserves_tons: int,
     *     mined_production_tons: int,
     *     exploration_conversion_tons?: int,
     *     actual_overburden_bcm: int,
     *     planned_life_of_mine_sr: float,
     *     stripping_cost_per_bcm_idr: int
     * } $data
     * @return array{
     *     site_id: string,
     *     pit_code: string,
     *     closing_proved_reserves_tons: int,
     *     closing_probable_reserves_tons: int,
     *     total_remaining_reserves_tons: int,
     *     actual_stripping_ratio: float,
     *     planned_lom_sr: float,
     *     excess_stripping_bcm: int,
     *     capitalized_stripping_asset_idr: int,
     *     reserve_depletion_pct: float,
     *     exhaustion_risk_verdict: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $site = MiningSite::findOrFail($siteId);

            $result = $this->tracker->track([
                'pit_code' => (string) $data['pit_code'],
                'initial_proved_reserves_tons' => (int) $data['initial_proved_reserves_tons'],
                'initial_probable_reserves_tons' => (int) $data['initial_probable_reserves_tons'],
                'mined_production_tons' => (int) $data['mined_production_tons'],
                'exploration_conversion_tons' => (int) ($data['exploration_conversion_tons'] ?? 0),
                'actual_overburden_bcm' => (int) $data['actual_overburden_bcm'],
                'planned_life_of_mine_sr' => (float) $data['planned_life_of_mine_sr'],
                'stripping_cost_per_bcm_idr' => (int) $data['stripping_cost_per_bcm_idr'],
            ]);

            return array_merge(['site_id' => $site->id], $result);
        });
    }
}
