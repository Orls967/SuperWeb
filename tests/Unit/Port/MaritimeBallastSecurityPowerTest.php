<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Port\Application\Actions\ConductBallastWaterExchangeAction;
use Modules\Port\Application\Actions\ConnectColdIroningShorePowerAction;
use Modules\Port\Application\Actions\IssueDeclarationOfSecurityAction;
use Modules\Port\Application\Services\MarineServicesDisbursementService;
use Modules\Port\Application\Services\PortFleetService;
use Modules\Port\Domain\Models\FleetVoyage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Maritime Ballast, Security & Shore Power Actions', function (): void {
    beforeEach(function (): void {
        $fleetService = app(PortFleetService::class);
        $this->vessel = $fleetService->registerVessel([
            'imo_number' => '9665544',
            'vessel_name' => 'MV Eco Carrier',
            'vessel_type' => 'CONTAINER',
            'deadweight_tonnage' => 24000,
            'gross_tonnage' => 18000,
            'net_tonnage' => 9000,
            'length_overall_cm' => 17000,
            'beam_cm' => 2800,
            'design_draft_cm' => 950,
            'main_engine_power_kw' => 12000,
        ]);

        $this->voyage = FleetVoyage::create([
            'voyage_code' => 'VOY-ECO-01',
            'vessel_id' => $this->vessel->id,
            'voyage_number' => '01/2026',
            'origin_port' => 'IDJKT',
            'destination_port' => 'SGSIN',
            'load_port' => 'Tanjung Priok',
            'discharge_port' => 'Jurong Port Singapore',
            'planned_start_at' => CarbonImmutable::now(),
            'planned_end_at' => CarbonImmutable::now()->addDays(2),
        ]);
    });

    it('validates compliant ballast water exchange under Regulation B-4', function (): void {
        $action = app(ConductBallastWaterExchangeAction::class);

        // 250 NM dari pantai, kedalaman 850m, tangki 1000m3, dipompa 1000m3 (Sequential method)
        $res = $action->execute(
            (int) $this->voyage->id,
            250.0,
            850.0,
            1000.0,
            1000.0,
            'SEQUENTIAL'
        );

        expect($res['audit']['is_d1_compliant'])->toBeTrue()
            ->and($res['audit']['compliance_status'])->toBe('COMPLIANT_D1');
    });

    it('rejects ballast water exchange too close to coast', function (): void {
        $action = app(ConductBallastWaterExchangeAction::class);

        // Hanya 15 NM dari pantai (batas minimum 50 NM)
        expect(fn () => $action->execute(
            (int) $this->voyage->id,
            15.0,
            450.0,
            1000.0,
            1000.0
        ))->toThrow(DomainException::class);
    });

    it('issues and enforces ISPS Declaration of Security between ship and port', function (): void {
        $action = app(IssueDeclarationOfSecurityAction::class);
        $marineService = app(MarineServicesDisbursementService::class);

        $portCall = $marineService->schedulePortCall($this->voyage->id, 'SGSIN', 'Jurong Singapore', 'DISCHARGING', CarbonImmutable::now());

        // Kapal Level 1, Pelabuhan Level 2 -> Effective level 2, DoS wajib
        $res = $action->execute(
            $portCall->id,
            1,
            2,
            'Capt. Alex Tan (SSO)',
            'Mr. Michael Lim (PFSO)'
        );

        expect($res['declaration']['effective_operating_level'])->toBe(2)
            ->and($res['declaration']['dos_mandatory'])->toBeTrue()
            ->and($res['declaration']['security_measures'])->not->toBeEmpty();
    });

    it('connects cold ironing shore power and records electricity and emissions savings', function (): void {
        $action = app(ConnectColdIroningShorePowerAction::class);
        $marineService = app(MarineServicesDisbursementService::class);

        $portCall = $marineService->schedulePortCall($this->voyage->id, 'IDJKT', 'Tanjung Priok', 'LOADING', CarbonImmutable::now());

        // Kapal tersambung 36 jam, daya 800 kW
        $res = $action->execute($portCall->id, 36.0, 800.0, 0.165);

        expect($res['metrics']['total_energy_consumed_kwh'])->toBe(28800.0) // 36 * 800
            ->and($res['metrics']['avoided_mgo_fuel_mt'])->toBeGreaterThan(5.0)
            ->and($res['metrics']['avoided_co2_emissions_mt'])->toBeGreaterThan(15.0);
    });
});
