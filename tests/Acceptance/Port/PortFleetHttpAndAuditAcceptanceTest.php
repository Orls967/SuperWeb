<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Port\Database\Seeders\PortFleetSeeder;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Port Fleet HTTP Endpoints & Audit Acceptance', function (): void {
    beforeEach(function (): void {
        $this->seed(PortFleetSeeder::class);
    });

    it('allows shipping line agent to view fleet overview dashboard', function (): void {
        $user = User::factory()->create(['role' => 'shipping_line_agent']);

        $response = $this->actingAs($user)->get(route('port.fleet.index'));
        $response->assertOk()
            ->assertViewIs('port::fleet.index')
            ->assertViewHas('vessels')
            ->assertSee('MV Nusantara Perdana')
            ->assertSee('Rating');
    });

    it('allows port operator to view voyages monitoring dashboard', function (): void {
        $user = User::factory()->create(['role' => 'port_operator']);

        $response = $this->actingAs($user)->get(route('port.voyages.index'));
        $response->assertOk()
            ->assertViewIs('port::fleet.voyages')
            ->assertViewHas('voyages')
            ->assertSee('VOY-2026-N01');
    });

    it('allows authorized agent to view charter parties and demurrage invoices', function (): void {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('port.charter.index'));
        $response->assertOk()
            ->assertViewIs('port::fleet.charter')
            ->assertViewHas('charterParties')
            ->assertSee('CP-2026-NP-001');
    });

    it('calculates TCE via simulate endpoint', function (): void {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->postJson(route('port.simulate.tce'), [
            'cargo_quantity_mt' => 50000.0,
            'freight_rate_usd_mt' => 15.0,
            'sea_steaming_days' => 12.0,
            'port_days_load' => 2.0,
            'port_days_disch' => 2.0,
            'bunker_sea_cost_usd' => 180000.0,
            'bunker_port_cost_usd' => 20000.0,
            'port_disbursements_usd' => 45000.0,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'gross_revenue_usd',
                'total_voyage_costs_usd',
                'net_voyage_revenue_usd',
                'daily_tce_usd',
                'net_daily_profit_usd',
            ]);
    });

    it('executes simulation of Malacca incident via endpoint', function (): void {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->postJson(route('port.simulate.malacca'));
        $response->assertOk()
            ->assertJsonPath('status', 'SIMULATION_SUCCESS')
            ->assertJsonStructure([
                'status',
                'voyage',
                'weather_advisory',
                'general_average_case',
            ]);
    });

    it('passes prt:fleet-audit on seeded database and fails when corrupt flag is passed', function (): void {
        $this->artisan('prt:fleet-audit')->assertSuccessful();
        $this->artisan('prt:fleet-audit --corrupt')->assertFailed();
    });
});
