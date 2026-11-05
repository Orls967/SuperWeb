<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\LifePolicy;
use Modules\Insurance\Domain\Models\UnitLinkFund;
use Modules\Insurance\Domain\Models\UnitLinkHolding;

final class SwitchUnitLinkFundAction
{
    public function __construct(
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * Mengalihkan unit investasi dari satu fund ke fund lain (switching).
     */
    public function execute(
        string $policyNumber,
        string $sourceFundCode,
        string $targetFundCode,
        int $unitsToSwitch,
        string $actorId,
        string $idempotencyKey,
    ): array {
        if ($sourceFundCode === $targetFundCode) {
            throw new InvalidArgumentException('Fund asal dan fund tujuan pengalihan tidak boleh sama.');
        }
        if ($unitsToSwitch <= 0) {
            throw new InvalidArgumentException('Jumlah unit yang dialihkan harus bernilai positif.');
        }

        $policy = LifePolicy::where('policy_number', $policyNumber)->firstOrFail();
        $sourceFund = UnitLinkFund::where('fund_code', $sourceFundCode)->firstOrFail();
        $targetFund = UnitLinkFund::where('fund_code', $targetFundCode)->firstOrFail();

        return $this->guard->run(
            key: $idempotencyKey,
            scope: 'ins:ul:switch:'.$policyNumber,
            payload: [
                'policy' => $policyNumber,
                'from' => $sourceFundCode,
                'to' => $targetFundCode,
                'units' => $unitsToSwitch,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use ($policy, $sourceFund, $targetFund, $unitsToSwitch) {
                return DB::transaction(function () use ($policy, $sourceFund, $targetFund, $unitsToSwitch) {
                    $sourceHolding = UnitLinkHolding::where('life_policy_id', $policy->id)
                        ->where('fund_id', $sourceFund->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($sourceHolding->units_held < $unitsToSwitch) {
                        throw new InvalidArgumentException("Saldo unit di fund {$sourceFund->fund_code} ({$sourceHolding->units_held}) tidak mencukupi untuk dialihkan ({$unitsToSwitch}).");
                    }

                    // Valuasi perpindahan
                    $cashValueSwitchedIdr = (int) intdiv($unitsToSwitch * $sourceFund->current_nav_per_unit_idr, 1000);
                    $targetUnitsPurchased = (int) intdiv($cashValueSwitchedIdr * 1000, $targetFund->current_nav_per_unit_idr);

                    // Potong unit di sumber
                    $sourceHolding->decrement('units_held', $unitsToSwitch);
                    $sourceHolding->decrement('current_valuation_idr', $cashValueSwitchedIdr);
                    $sourceFund->decrement('total_units', $unitsToSwitch);
                    $sourceFund->decrement('total_aum_idr', $cashValueSwitchedIdr);

                    // Tambah unit di target
                    $targetHolding = UnitLinkHolding::firstOrCreate(
                        ['life_policy_id' => $policy->id, 'fund_id' => $targetFund->id],
                        ['units_held' => 0, 'acquisition_cost_idr' => 0, 'current_valuation_idr' => 0]
                    );
                    $targetHolding->increment('units_held', $targetUnitsPurchased);
                    $targetHolding->increment('acquisition_cost_idr', $cashValueSwitchedIdr);
                    $targetHolding->increment('current_valuation_idr', $cashValueSwitchedIdr);

                    $targetFund->increment('total_units', $targetUnitsPurchased);
                    $targetFund->increment('total_aum_idr', $cashValueSwitchedIdr);

                    return [
                        'policy_number' => $policy->policy_number,
                        'source_fund' => $sourceFund->fund_code,
                        'target_fund' => $targetFund->fund_code,
                        'source_units_deducted' => $unitsToSwitch,
                        'cash_value_idr' => $cashValueSwitchedIdr,
                        'target_units_credited' => $targetUnitsPurchased,
                    ];
                });
            }
        );
    }
}

