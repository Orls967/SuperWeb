<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\HtlAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelReservation;
use Tests\TestCase;

class HotelVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);
    }

    public function test_a_authorized_user_can_access_hotel_index_and_settle_folio(): void
    {
        $hotelAdmin = User::factory()->create(['role' => 'hotel_admin']);
        $reservation = HotelReservation::where('status', 'checked_in')->firstOrFail();

        // Access index
        $response = $this->actingAs($hotelAdmin)->get(route('hotel.index'));
        $response->assertOk();
        $response->assertSeeText('Hotel, Reservasi & Folio Operations');
        $response->assertSee($reservation->reservation_number);

        // Settle folio
        $settleResponse = $this->actingAs($hotelAdmin)->post(route('hotel.reservations.settle', $reservation), [
            'payment_amount' => $reservation->total_room_charge_idr,
        ]);

        $settleResponse->assertRedirect(route('hotel.index'));
        $settleResponse->assertSessionHas('success');

        // Check ledger transaction created
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'type' => 'HOTEL_FOLIO_SETTLEMENT',
            'reference_type' => 'htl_folios',
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $reservation = HotelReservation::firstOrFail();

        $response = $this->actingAs($customer)->get(route('hotel.index'));
        $response->assertForbidden();

        $postResponse = $this->actingAs($customer)->post(route('hotel.reservations.settle', $reservation), [
            'payment_amount' => 1000000,
        ]);
        $postResponse->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('hotel.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_d_two_source_hotel_audit_passes_cleanly(): void
    {
        $exit = Artisan::call('htl:audit');
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Hotel audit PASSED with 0 discrepancy', Artisan::output());
    }

    public function test_e_htl_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new HtlAuditCorruptionFixture;
        $expectedError = $fixture->corrupt();

        $exit = Artisan::call('htl:audit');
        $this->assertSame(1, $exit);
        $this->assertStringContainsString($expectedError, Artisan::output());
    }
}
