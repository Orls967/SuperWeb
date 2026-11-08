<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Shared\Domain\Algorithms\Port\BunkerFuelConsumptionOptimizer;
use Modules\Shared\Domain\Algorithms\Port\CiiCarbonIntensityRatingEngine;
use Modules\Shared\Domain\Algorithms\Port\GeneralAverageContributionDistributor;
use Modules\Shared\Domain\Algorithms\Port\LaytimeDemurrageCalculator;
use Modules\Shared\Domain\Algorithms\Port\TimeCharterEquivalentCalculator;

describe('A10: BunkerFuelConsumptionOptimizer', function (): void {
    it('calculates daily fuel consumption according to cubic propulsion law and weather margin', function (): void {
        $optimizer = new BunkerFuelConsumptionOptimizer;

        // Panamax Bulk Carrier: 70,000 DWT displacement, design speed 14.5 kts, 9000 kW main engine
        $result = $optimizer->calculateDailyConsumption(
            70000.0,
            14.0,
            14.5,
            9000.0,
            175.0, // SFOC design
            700.0, // Aux generator load
            210.0,
            3      // Beaufort 3 (gentle breeze)
        );

        expect($result['speed_knots'])->toBe(14.0)
            ->and($result['engine_power_kw'])->toBeGreaterThan(7000.0)
            ->and($result['engine_power_kw'])->toBeLessThan(9000.0)
            ->and($result['main_engine_consumption_mt_day'])->toBeGreaterThan(25.0)
            ->and($result['main_engine_consumption_mt_day'])->toBeLessThan(40.0)
            ->and($result['total_daily_consumption_mt'])->toBeGreaterThan(28.0);
    });

    it('penalizes fuel consumption in adverse weather conditions above Beaufort 4', function (): void {
        $optimizer = new BunkerFuelConsumptionOptimizer;

        $calm = $optimizer->calculateDailyConsumption(60000.0, 13.0, 14.5, 8500.0, 175.0, 600.0, 210.0, 2);
        $storm = $optimizer->calculateDailyConsumption(60000.0, 13.0, 14.5, 8500.0, 175.0, 600.0, 210.0, 7);

        expect($storm['weather_margin_factor'])->toBeGreaterThan($calm['weather_margin_factor'])
            ->and($storm['total_daily_consumption_mt'])->toBeGreaterThan($calm['total_daily_consumption_mt']);
    });

    it('evaluates multi-port bunkering decision considering fuel price and deviation costs', function (): void {
        $optimizer = new BunkerFuelConsumptionOptimizer;

        $candidates = [
            [
                'port_code' => 'SGSIN',
                'port_name' => 'Singapore Bunker Hub',
                'vlsfo_price_usd_mt' => 610.0,
                'deviation_distance_nm' => 15.0,
                'calling_cost_usd' => 3500.0,
            ],
            [
                'port_code' => 'IDJKT',
                'port_name' => 'Tanjung Priok',
                'vlsfo_price_usd_mt' => 685.0,
                'deviation_distance_nm' => 0.0,
                'calling_cost_usd' => 1500.0,
            ],
            [
                'port_code' => 'NLRTM',
                'port_name' => 'Rotterdam Gateway',
                'vlsfo_price_usd_mt' => 595.0,
                'deviation_distance_nm' => 450.0, // Terlalu jauh deviasinya
                'calling_cost_usd' => 8000.0,
            ],
        ];

        $decision = $optimizer->optimizeBunkeringPort($candidates, 500.0, 18000.0, 13.5, 26.0);

        // Singapore harus menjadi pilihan optimal karena harga per ton jauh lebih murah dibanding Tanjung Priok
        // dan biaya deviasi 15 mil laut sangat kecil
        expect($decision['optimal_port_code'])->toBe('SGSIN')
            ->and($decision['comparisons'])->toHaveCount(3);
    });
});

describe('A11: LaytimeDemurrageCalculator', function (): void {
    it('calculates demurrage when net laytime exceeds allowed hours under SHINC terms', function (): void {
        $calculator = new LaytimeDemurrageCalculator;
        $base = CarbonImmutable::parse('2026-05-01 08:00:00');

        $norTendered = $base;
        $workCommenced = $base->addHours(6); // Selesai turn time
        $workCompleted = $base->addHours(110); // Total 104 jam berjalan (diizinkan 72 jam)

        $statement = $calculator->calculateStatement(
            $norTendered,
            $workCommenced,
            $workCompleted,
            72.0, // allowed laytime
            18000.0, // daily demurrage rate
            9000.0, // despatch rate
            'SHINC',
            6
        );

        expect($statement['result_type'])->toBe('DEMURRAGE')
            ->and($statement['demurrage_hours'])->toBe(32.0)
            ->and($statement['settlement_amount_usd'])->toBe(24000.0); // (32 / 24) * 18,000 = 24,000
    });

    it('calculates despatch when loading is completed early and exempts rain interruption', function (): void {
        $calculator = new LaytimeDemurrageCalculator;
        $base = CarbonImmutable::parse('2026-06-10 08:00:00');

        $norTendered = $base;
        $workCommenced = $base->addHours(6);
        $workCompleted = $base->addHours(50); // 44 jam kotor

        // Ada hujan 8 jam yang dikecualikan
        $interruptions = [
            [
                'start' => $base->addHours(15),
                'end' => $base->addHours(23),
                'reason' => 'RAIN',
                'is_excepted' => true,
            ],
        ];

        $statement = $calculator->calculateStatement(
            $norTendered,
            $workCommenced,
            $workCompleted,
            72.0,
            20000.0,
            10000.0,
            'SHEX',
            6,
            $interruptions
        );

        expect($statement['result_type'])->toBe('DESPATCH')
            ->and($statement['excepted_hours'])->toBe(8.0)
            ->and($statement['net_laytime_used_hours'])->toBe(36.0) // 44 - 8 = 36
            ->and($statement['despatch_hours'])->toBe(36.0) // 72 - 36 = 36 jam saved
            ->and($statement['settlement_amount_usd'])->toBe(-15000.0); // (36 / 24) * 10,000 = -15,000
    });
});

describe('A12: CiiCarbonIntensityRatingEngine', function (): void {
    it('determines attained CII and assigns IMO rating A-E according to MEPC.354(78)', function (): void {
        $engine = new CiiCarbonIntensityRatingEngine;

        // Bulk Carrier 60,000 DWT berlayar 5000 nm, konsumsi 180 MT VLSFO
        $res = $engine->evaluateCii(
            'BULK_CARRIER',
            60000.0,
            5000.0,
            ['vlsfo_mt' => 180.0],
            2026
        );

        expect($res['rating'])->toBeIn(['A', 'B', 'C', 'D', 'E'])
            ->and($res['attained_cii'])->toBeGreaterThan(1.0)
            ->and($res['reference_cii'])->toBeGreaterThan(1.0)
            ->and($res['required_cii'])->toBeLessThan($res['reference_cii']) // 2026 Z=11% reduction
            ->and($res['boundaries'])->toHaveKeys(['superior_a', 'minor_superior_b', 'moderate_c', 'minor_inferior_d']);
    });

    it('requires corrective action when carbon rating drops to inferior E', function (): void {
        $engine = new CiiCarbonIntensityRatingEngine;

        // Konsumsi sangat boros: 2000 MT VLSFO untuk jarak pendek 500 nm pada kapal 60k DWT
        $res = $engine->evaluateCii(
            'BULK_CARRIER',
            60000.0,
            500.0,
            ['vlsfo_mt' => 2000.0],
            2026
        );

        expect($res['rating'])->toBe('E')
            ->and($res['corrective_action_required'])->toBeTrue()
            ->and($res['recommended_speed_reduction_pct'])->toBeGreaterThan(0.0);
    });
});

describe('A13: TimeCharterEquivalentCalculator', function (): void {
    it('computes net daily TCE rate and operating margin against vessel daily OPEX', function (): void {
        $tceCalc = new TimeCharterEquivalentCalculator;

        // Kargo 50,000 MT * $15/MT = $750,000 gross freight
        // 14 hari laut + 4 hari port = 18 hari total
        // Bunker: $200,000 laut + $25,000 port. Port disbursement: $65,000. Total costs: $290,000.
        // Net: $460,000 / 18 = $25,555.56 / day
        $result = $tceCalc->calculateTce(
            50000.0,
            15.0,
            14.0,
            2.0,
            2.0,
            200000.0,
            25000.0,
            65000.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            7000.0 // Daily OPEX
        );

        expect($result['gross_revenue_usd'])->toBe(750000.0)
            ->and($result['total_voyage_costs_usd'])->toBe(290000.0)
            ->and($result['net_voyage_revenue_usd'])->toBe(460000.0)
            ->and($result['daily_tce_usd'])->toBe(25555.56)
            ->and($result['net_daily_profit_usd'])->toBe(18555.56);
    });
});

describe('A14: GeneralAverageContributionDistributor', function (): void {
    it('apportions sacrifices and expenditures across vessel, cargo, and freight interests', function (): void {
        $gaDistributor = new GeneralAverageContributionDistributor;

        // Kasus: Kapal tiba $10M, Kargo tiba $7.6M (+ Made good $400k sacrifice = $8M), Freight $2M. Total Contributory: $20M.
        // Kerugian GA: $1M (Jettison cargo $400k, Salvage tug $600k).
        // Rate: 1M / 20M = 5.0%.
        $result = $gaDistributor->calculateAdjustment(
            10000000.0,
            0.0,
            7600000.0, // Arrived sound value of cargo
            0.0,
            2000000.0,
            0.0,
            [
                ['interest' => 'CARGO', 'description' => 'Jettison deck cargo', 'amount_usd' => 400000.0, 'category' => 'SACRIFICE'],
                ['interest' => 'SHIP', 'description' => 'Salvage assistance under LOF', 'amount_usd' => 600000.0, 'category' => 'EXPENDITURE'],
            ]
        );

        expect($result['contribution_rate_percentage'])->toBe(5.0)
            ->and($result['total_general_average_loss'])->toBe(1000000.0)
            ->and($result['apportionment']['ship']['gross_contribution'])->toBe(500000.0) // 5% of 10M
            ->and($result['apportionment']['cargo']['gross_contribution'])->toBe(400000.0) // 5% of 8M
            ->and($result['apportionment']['freight']['gross_contribution'])->toBe(100000.0) // 5% of 2M
            ->and($result['apportionment']['cargo']['net_payable_or_receivable'])->toBe(0.0); // 400k gross - 400k sacrifice = 0
    });
});
