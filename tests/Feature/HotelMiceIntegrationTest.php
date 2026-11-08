<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HtlBanquetOrder;
use Modules\Hotel\Domain\Models\HtlWeddingMilestoneSchedule;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;
use Tests\TestCase;

final class HotelMiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $salesUser;

    protected User $frontDeskUser;

    protected User $chefUser;

    protected User $financeUser;

    protected HotelProperty $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->salesUser = User::factory()->create(['role' => 'sales_mgr']);
        $this->frontDeskUser = User::factory()->create(['role' => 'front_desk']);
        $this->chefUser = User::factory()->create(['role' => 'chef_banquet']);
        $this->financeUser = User::factory()->create(['role' => 'finance']);

        $this->property = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-INT-001',
            'name' => 'Metropolitan Convention & Hotel',
            'property_type' => 'HOTEL',
            'city' => 'Jakarta Pusat',
            'total_rooms' => 450,
        ]);
    }

    public function test_mice_sales_pipeline_board_route(): void
    {
        $response = $this->actingAs($this->salesUser)->get(route('hotel.mice.pipeline'));
        $response->assertStatus(200);
        $response->assertSee('MICE & Global Event Sales Pipeline', false);
    }

    public function test_banquet_beo_production_sheet_route(): void
    {
        $response = $this->actingAs($this->chefUser)->get(route('hotel.mice.banquets.beo'));
        $response->assertStatus(200);
        $response->assertSee('Lembar Kerja Produksi Banquet BEO', false);
    }

    public function test_room_block_allotment_monitor_route(): void
    {
        $response = $this->actingAs($this->frontDeskUser)->get(route('hotel.mice.room-blocks'));
        $response->assertStatus(200);
        $response->assertSee('Pemantau Alokasi Kamar Blok', false);
    }

    public function test_wedding_milestone_contract_hub_route(): void
    {
        $response = $this->actingAs($this->salesUser)->get(route('hotel.mice.weddings'));
        $response->assertStatus(200);
        $response->assertSee('Hub Kontrak Pernikahan Mewah', false);
    }

    public function test_exhibition_booth_designer_route(): void
    {
        $response = $this->actingAs($this->frontDeskUser)->get(route('hotel.mice.exhibitions'));
        $response->assertStatus(200);
        $response->assertSee('Denah Grid Stan Pameran', false);
    }

    public function test_margin_calculator_route(): void
    {
        $response = $this->actingAs($this->salesUser)->get(route('hotel.mice.calculator'));
        $response->assertStatus(200);
        $response->assertSee('Kalkulator & Guardrail Margin Kotor Acara MICE', false);
    }

    public function test_reconciliation_center_route(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('hotel.mice.reconciliation'));
        $response->assertStatus(200);
        $response->assertSee('Rekonsiliasi Master Folio MICE & Audit Ledger', false);
    }

    public function test_cli_commands_execute_without_errors(): void
    {
        $contract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'CTR-CLI-001',
            'property_id' => $this->property->id,
            'event_type' => 'CONFERENCE',
            'client_name' => 'CLI Test Group',
            'event_date' => date('Y-m-d', strtotime('+30 days')),
            'contract_total_value_idr' => 75000000,
            'status' => 'CONFIRMED',
        ]);

        RoomBlockAllotment::create([
            'id' => (string) Str::uuid(),
            'block_code' => 'BLK-CLI-001',
            'mice_contract_id' => $contract->id,
            'check_in_date' => date('Y-m-d', strtotime('+30 days')),
            'release_deadline_date' => date('Y-m-d', strtotime('+15 days')),
            'allotted_rooms_count' => 50,
            'confirmed_rooms_count' => 10,
            'released_rooms_count' => 0,
            'status' => 'HELD',
        ]);

        HtlBanquetOrder::create([
            'id' => (string) Str::uuid(),
            'beo_code' => 'BEO-CLI-001',
            'mice_contract_id' => $contract->id,
            'title' => 'CLI Banquet Dinner',
            'meal_period' => 'DINNER',
            'guaranteed_pax' => 100,
            'prepared_pax' => 105,
            'event_date' => date('Y-m-d', strtotime('+30 days')),
            'status' => 'CONFIRMED',
        ]);

        HtlWeddingMilestoneSchedule::create([
            'id' => (string) Str::uuid(),
            'mice_contract_id' => $contract->id,
            'milestone_index' => 1,
            'name' => 'Initial Deposit',
            'percentage' => 30.0,
            'amount_idr' => 22500000,
            'paid_amount_idr' => 0,
            'due_date' => date('Y-m-d', strtotime('+7 days')),
            'status' => 'PENDING',
        ]);

        // Run Artisan commands
        $this->artisan('hotel:release-room-blocks', ['--dry-run' => true])->assertSuccessful();
        $this->artisan('hotel:audit-mice-contracts')->assertSuccessful();
        $this->artisan('hotel:audit-mice-attrition')->assertSuccessful();
        $this->artisan('hotel:sync-banquet-bom')->assertSuccessful();
        $this->artisan('hotel:mice-milestone-reminder')->assertSuccessful();
    }

    public function test_post_margin_calculator_compute(): void
    {
        $payload = [
            'proposal_id' => 'PROP-INT-001',
            'revenue' => [
                'hall' => 50000000,
                'fnb' => 80000000,
            ],
            'costs' => [
                'food' => 24000000,
                'bev' => 6000000,
            ],
            'min_guardrail_margin' => 40.0,
        ];

        $response = $this->actingAs($this->salesUser)->post(route('hotel.mice.calculator.compute'), $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('total_revenue_idr', 130000000);
    }

    public function test_post_exhibition_booth_reserve(): void
    {
        $contract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'CTR-BTH-TEST',
            'property_id' => $this->property->id,
            'event_type' => 'EXHIBITION',
            'client_name' => 'Auto Show Expo',
            'event_date' => date('Y-m-d', strtotime('+45 days')),
            'contract_total_value_idr' => 300000000,
            'status' => 'CONFIRMED',
        ]);

        $payload = [
            'contract_id' => $contract->id,
            'booth_number' => 'B-101',
            'booth_type' => 'ISLAND',
            'grid_x' => 2,
            'grid_y' => 4,
            'width_m' => 6,
            'length_m' => 6,
            'electricity_kva' => 2.5,
            'is_dedicated_lan' => 1,
            'base_rental_idr' => 35000000,
        ];

        $response = $this->actingAs($this->frontDeskUser)->post(route('hotel.mice.exhibitions.reserve'), $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('htl_exhibition_booth_reservations', [
            'mice_contract_id' => $contract->id,
            'booth_number' => 'B-101',
        ]);
    }

    public function test_post_wedding_milestone_payment(): void
    {
        $contract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'CTR-WED-PAY',
            'property_id' => $this->property->id,
            'event_type' => 'WEDDING',
            'client_name' => 'Luxury Wedding Couple',
            'event_date' => date('Y-m-d', strtotime('+90 days')),
            'contract_total_value_idr' => 250000000,
            'status' => 'CONFIRMED',
        ]);

        $schedule = HtlWeddingMilestoneSchedule::create([
            'id' => (string) Str::uuid(),
            'mice_contract_id' => $contract->id,
            'milestone_index' => 1,
            'name' => 'Deposit Payment',
            'percentage' => 25.0,
            'amount_idr' => 62500000,
            'paid_amount_idr' => 0,
            'due_date' => date('Y-m-d'),
            'status' => 'PENDING',
        ]);

        $payload = [
            'contract_id' => $contract->id,
            'milestone_index' => 1,
            'amount_paid_idr' => 62500000,
            'payment_reference' => 'BCA-TRANSFER-8899',
        ];

        $response = $this->actingAs($this->financeUser)->post(route('hotel.mice.weddings.pay-milestone'), $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('htl_wedding_milestone_schedules', [
            'id' => $schedule->id,
            'status' => 'PAID',
            'payment_reference' => 'BCA-TRANSFER-8899',
        ]);
    }

    public function test_post_room_block_release_trigger(): void
    {
        $contract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'CTR-REL-TEST',
            'property_id' => $this->property->id,
            'event_type' => 'CONFERENCE',
            'client_name' => 'Release Test Org',
            'event_date' => date('Y-m-d', strtotime('+20 days')),
            'contract_total_value_idr' => 120000000,
            'status' => 'CONFIRMED',
        ]);

        RoomBlockAllotment::create([
            'id' => (string) Str::uuid(),
            'block_code' => 'BLK-REL-TEST',
            'mice_contract_id' => $contract->id,
            'check_in_date' => date('Y-m-d', strtotime('+20 days')),
            'release_deadline_date' => date('Y-m-d'),
            'allotted_rooms_count' => 100,
            'confirmed_rooms_count' => 20,
            'released_rooms_count' => 0,
            'status' => 'HELD',
        ]);

        $payload = [
            'property_id' => $this->property->id,
        ];

        $response = $this->actingAs($this->frontDeskUser)->post(route('hotel.mice.release-blocks.trigger'), $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_post_reconciliation_audit(): void
    {
        $contract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'CTR-AUD-TEST',
            'property_id' => $this->property->id,
            'event_type' => 'CONFERENCE',
            'client_name' => 'Audit Test Corp',
            'event_date' => date('Y-m-d', strtotime('+10 days')),
            'contract_total_value_idr' => 200000000,
            'status' => 'CONFIRMED',
        ]);

        $payload = [
            'contract_id' => $contract->id,
        ];

        $response = $this->actingAs($this->financeUser)->post(route('hotel.mice.reconciliation.audit'), $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}
