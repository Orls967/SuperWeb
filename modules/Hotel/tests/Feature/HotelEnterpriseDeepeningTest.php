<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Actions\CalculateDynamicBarAndChannelRateAction;
use Modules\Hotel\Application\Actions\ExecuteOverbookingWalkCompensationAction;
use Modules\Hotel\Application\Actions\ManageBanquetEventOrderBomAndMarginAction;
use Modules\Hotel\Application\Actions\MintTimeshareFractionalVillaPointsAction;
use Modules\Hotel\Application\Actions\ReserveRoomWithInventoryNightLockAction;
use Modules\Hotel\Application\Actions\RunIdempotentHotelNightAuditAction;
use Modules\Hotel\Application\Actions\SettleMultiVendorDestinationPackageAction;
use Modules\Hotel\Application\Actions\SettleSplitGuestFolioCorporatePersonalAction;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\MiceContract;
use Tests\TestCase;

class HotelEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private HotelRoom $room;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->property = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-BALI-RESORT-01',
            'name' => 'The Grand Nusa Dua Resort',
            'property_type' => 'RESORT',
            'city' => 'Badung',
            'total_rooms' => 120,
        ]);

        $this->room = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_number' => 'VILLA-101',
            'room_type' => 'VILLA',
            'base_rate_idr' => 2500000,
            'status' => 'available',
        ]);

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_reserve_room_with_inventory_night_lock_action_creates_reservation_and_folio(): void
    {
        $action = app(ReserveRoomWithInventoryNightLockAction::class);

        $result = $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-14',
            dailyRateIdr: 2500000
        );

        $this->assertEquals(4, $result['nights']);
        $this->assertEquals(10000000, $result['total_room_charge_idr']);
        $this->assertNotNull($result['folio']);
        $this->assertEquals(10000000, $result['folio']->outstanding_balance);

        $this->assertDatabaseHas('htl_reservations', [
            'id' => $result['reservation']->id,
            'room_id' => $this->room->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_reserve_room_action_enforces_anti_overlap_blocking_double_booking(): void
    {
        $action = app(ReserveRoomWithInventoryNightLockAction::class);

        $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-14',
            dailyRateIdr: 2500000
        );

        // Attempt overlapping reservation for the same room
        $this->expectException(\RuntimeException::class);
        $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) Str::uuid(),
            checkInDate: '2026-11-12',
            checkOutDate: '2026-11-16',
            dailyRateIdr: 2500000
        );
    }

    public function test_calculate_dynamic_bar_and_channel_rate_action_respects_floor_and_markup(): void
    {
        $action = app(CalculateDynamicBarAndChannelRateAction::class);

        // Base cost: 1,000,000 -> Floor = 1,150,000 IDR
        // Occupancy: 90% -> yield multiplier = 1.40x -> raw BAR = 1,610,000 -> rounded 1,610,000
        // OTA markup: 1.15x -> raw channel = 1,851,500 -> rounded 1,850,000
        $result = $action->handle(
            propertyId: $this->property->id,
            roomType: 'VILLA',
            baseCostIdr: 1000000,
            occupancyPct: 90.0,
            channel: 'OTA_BOOKING'
        );

        $this->assertEquals(1150000, $result['floor_rate_idr']);
        $this->assertGreaterThanOrEqual($result['floor_rate_idr'], $result['bar_rate_idr']);
        $this->assertGreaterThanOrEqual($result['bar_rate_idr'], $result['channel_rate_idr']);
    }

    public function test_execute_overbooking_walk_compensation_action_relocates_guest_and_tallies_credit(): void
    {
        $reservation = HotelReservation::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'reservation_number' => 'HTL-RES-WALK01',
            'guest_user_id' => (string) Str::uuid(),
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'daily_rate_idr' => 2000000,
            'total_room_charge_idr' => 4000000,
            'status' => 'confirmed',
        ]);

        $action = app(ExecuteOverbookingWalkCompensationAction::class);

        $result = $action->handle(
            reservationId: $reservation->id,
            alternatePropertyName: 'Four Seasons Resort Jimbaran',
            transportAllowanceIdr: 400000,
            goodwillCreditIdr: 1500000
        );

        // Compensation = 2M (1 night rate) + 400k (transport) + 1.5M (credit) = 3.9M IDR
        $this->assertEquals(3900000, $result['total_compensation_idr']);
        $this->assertEquals('Four Seasons Resort Jimbaran', $result['alternate_property_name']);

        $reservation->refresh();
        $this->assertEquals('RELOCATED_WALK', $reservation->status);
    }

    public function test_run_idempotent_hotel_night_audit_action_posts_charges_and_computes_metrics(): void
    {
        $today = now()->toDateString();
        $reservation = HotelReservation::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'reservation_number' => 'HTL-RES-AUDIT01',
            'guest_user_id' => (string) Str::uuid(),
            'check_in_date' => $today,
            'check_out_date' => now()->addDays(2)->toDateString(),
            'daily_rate_idr' => 1800000,
            'total_room_charge_idr' => 3600000,
            'status' => 'checked_in',
        ]);

        HotelFolio::create([
            'id' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'guest_user_id' => $reservation->guest_user_id,
            'total_room_charges' => 0,
            'total_addon_charges' => 0,
            'total_paid' => 0,
            'outstanding_balance' => 0,
            'status' => 'open',
        ]);

        $action = app(RunIdempotentHotelNightAuditAction::class);

        $result = $action->handle(
            propertyId: $this->property->id,
            auditDate: $today,
            totalAvailableRooms: 100
        );

        $this->assertEquals(1, $result['active_in_house_count']);
        $this->assertEquals(1800000, $result['total_room_revenue_posted_idr']);
        $this->assertEquals(1800000, $result['adr_idr']);
        $this->assertEquals(18000, $result['revpar_idr']); // 1.8M / 100 rooms
        $this->assertEquals('AUDIT_CLOSED_BALANCED', $result['status']);
    }

    public function test_settle_split_guest_folio_corporate_personal_action_splits_settlement(): void
    {
        $folio = HotelFolio::create([
            'id' => (string) Str::uuid(),
            'reservation_id' => (string) Str::uuid(),
            'guest_user_id' => (string) Str::uuid(),
            'total_room_charges' => 5000000,
            'total_addon_charges' => 1200000,
            'total_paid' => 0,
            'outstanding_balance' => 6200000,
            'status' => 'open',
        ]);

        $action = app(SettleSplitGuestFolioCorporatePersonalAction::class);

        $result = $action->handle(
            folioId: $folio->id,
            corporateRoomShareIdr: 5000000,
            guestPersonalAddonIdr: 1200000
        );

        $this->assertEquals(6200000, $result['total_settled_idr']);

        $folio->refresh();
        $this->assertEquals('settled', $folio->status);
        $this->assertEquals(0, $folio->outstanding_balance);
        $this->assertEquals(6200000, $folio->total_paid);
    }

    public function test_manage_banquet_event_order_bom_and_margin_action_approves_when_margin_ge_40(): void
    {
        $miceContract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'MICE-GALA-01',
            'property_id' => $this->property->id,
            'event_type' => 'CONFERENCE',
            'client_name' => 'Tech Corp',
            'event_date' => now()->addDays(10)->toDateString(),
            'contract_total_value_idr' => 100000000,
            'status' => 'CONFIRMED',
        ]);

        $action = app(ManageBanquetEventOrderBomAndMarginAction::class);

        // 100 pax @ 500k = 50M revenue. Costs: F&B 15M + AV 5M + Decor 5M = 25M (50% margin -> Approved!)
        $result = $action->handle(
            miceContractId: $miceContract->id,
            packageName: 'Corporate Annual Gala',
            paxCount: 100,
            packagePricePerPaxIdr: 500000,
            fnbMaterialsCostIdr: 15000000,
            avEquipmentVendorCostIdr: 5000000,
            decorFloristVendorCostIdr: 5000000
        );

        $this->assertEquals(50000000, $result['total_revenue_idr']);
        $this->assertEquals(25000000, $result['total_production_cost_idr']);
        $this->assertEquals(50.0, $result['gross_margin_pct']);

        $this->assertDatabaseHas('htl_banquet_production_sheets', [
            'id' => $result['production_sheet']->id,
            'total_production_cost_idr' => 25000000,
        ]);
    }

    public function test_manage_banquet_event_order_bom_and_margin_action_rejects_sub_40_margin(): void
    {
        $miceContract = MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'MICE-GALA-SUB40',
            'property_id' => $this->property->id,
            'event_type' => 'CONFERENCE',
            'client_name' => 'Loss Leader Corp',
            'event_date' => now()->addDays(10)->toDateString(),
            'contract_total_value_idr' => 20000000,
            'status' => 'CONFIRMED',
        ]);

        $action = app(ManageBanquetEventOrderBomAndMarginAction::class);

        // 100 pax @ 200k = 20M revenue. Costs: 15M + 5M + 5M = 25M (Cost exceeds revenue -> Negative margin -> Reject!)
        $this->expectException(\RuntimeException::class);
        $action->handle(
            miceContractId: $miceContract->id,
            packageName: 'Loss Leader Package',
            paxCount: 100,
            packagePricePerPaxIdr: 200000,
            fnbMaterialsCostIdr: 15000000,
            avEquipmentVendorCostIdr: 5000000,
            decorFloristVendorCostIdr: 5000000
        );
    }

    public function test_mint_timeshare_fractional_villa_points_action_and_multi_vendor_package_action(): void
    {
        // 1. Mint timeshare
        $timeshareAction = app(MintTimeshareFractionalVillaPointsAction::class);
        $tsResult = $timeshareAction->handle(
            propertyId: $this->property->id,
            unitCode: 'VILLA-TS-99',
            unitName: 'Sunset Ocean Pool Villa',
            dailyRentalRateIdr: 7500000,
            totalTokenShares: 100
        );

        $this->assertEquals('VILLA-TS-99', $tsResult['unit_code']);
        $this->assertEquals(100, $tsResult['total_shares']);

        $this->assertDatabaseHas('htl_timeshare_units', [
            'unit_code' => 'VILLA-TS-99',
            'daily_rental_rate_idr' => 7500000,
        ]);

        // 2. Settle Multi-Vendor Package
        $packageAction = app(SettleMultiVendorDestinationPackageAction::class);
        $pkgResult = $packageAction->handle(
            packageCode: 'PKG-BALI-ALLIN',
            title: 'Bali 5D4N Premium Experience',
            totalPriceIdr: 20000000,
            vendorShares: [
                ['vendor' => 'HOTEL', 'amount' => 12000000, 'account' => 'hotel:escrow'],
                ['vendor' => 'AIRLINE', 'amount' => 5000000, 'account' => 'air:escrow'],
                ['vendor' => 'CRUISE', 'amount' => 3000000, 'account' => 'cruise:escrow'],
            ]
        );

        $this->assertEquals(20000000, $pkgResult['total_price_idr']);
        $this->assertEquals(3, $pkgResult['vendor_count']);

        $this->assertDatabaseHas('htl_destination_packages', [
            'package_code' => 'PKG-BALI-ALLIN',
            'total_price_idr' => 20000000,
        ]);
    }

    public function test_hotel_pms_enterprise_http_routes_workflow(): void
    {
        // 1. Reservations page
        $resGet = $this->actingAs($this->admin)->get(route('hotel.pms.reservations'));
        $resGet->assertOk();
        $resGet->assertSeeText('Hotel Room Reservations');

        // 2. Yield rates page & calculate post
        $yieldGet = $this->actingAs($this->admin)->get(route('hotel.pms.yield-rates'));
        $yieldGet->assertOk();

        $yieldPost = $this->actingAs($this->admin)->post(route('hotel.pms.yield-rates.calculate'), [
            'property_id' => $this->property->id,
            'room_type' => 'DELUXE',
            'base_cost_idr' => 600000,
            'occupancy_pct' => 85.0,
            'channel' => 'OTA_BOOKING',
        ]);
        $yieldPost->assertRedirect(route('hotel.pms.yield-rates'));
        $yieldPost->assertSessionHas('success');

        // 3. Overbooking page
        $obGet = $this->actingAs($this->admin)->get(route('hotel.pms.overbooking'));
        $obGet->assertOk();
        $obGet->assertSeeText('Overbooking Relocation');
    }
}
