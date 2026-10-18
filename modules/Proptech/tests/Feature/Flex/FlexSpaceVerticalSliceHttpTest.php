<?php

declare(strict_types=1);

namespace Modules\Proptech\tests\Feature\Flex;

use App\Models\User;
use App\Quality\Audit\Fixtures\ProptechFlexCorruptionFixture;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Proptech\Application\Services\FlexSpaceService;
use Modules\Proptech\Domain\Models\Flex\FlexBooking;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;
use Tests\TestCase;

class FlexSpaceVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected FlexSpaceService $flexService;

    protected FlexSpace $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->flexService = app(FlexSpaceService::class);

        $this->space = FlexSpace::create([
            'space_code' => 'SPC-HTTP-01',
            'name' => 'Http Executive Boardroom',
            'space_type' => 'MEETING_ROOM',
            'capacity_persons' => 12,
            'rate_per_hour_idr' => 200000,
            'area_sqm' => 45.0,
            'status' => 'AVAILABLE',
        ]);
    }

    public function test_a_authorized_user_can_access_flex_index_book_and_checkin(): void
    {
        $manager = User::factory()->create(['role' => 'facility_manager']);

        // 1. Access flex index view
        $response = $this->actingAs($manager)->get(route('proptech.flex.index'));
        $response->assertOk();
        $response->assertSeeText('Flex-Space & Co-Working Operations');
        $response->assertSeeText('Http Executive Boardroom');

        // 2. Create booking
        $start = Carbon::now()->addHours(2);
        $end = Carbon::now()->addHours(5);

        $bookResponse = $this->actingAs($manager)->post(route('proptech.flex.store'), [
            'flex_space_id' => $this->space->id,
            'start_time' => $start->toDateTimeString(),
            'end_time' => $end->toDateTimeString(),
        ]);

        $bookResponse->assertRedirect(route('proptech.flex.index'));
        $bookResponse->assertSessionHas('success');

        $booking = FlexBooking::where('flex_space_id', $this->space->id)->firstOrFail();
        $this->assertEquals('HELD', $booking->status);
        $this->assertEquals(600000, $booking->total_price_idr);
        $this->assertEquals(180000, $booking->deposit_amount_idr);

        // 3. Check-in with passport
        $checkInResponse = $this->actingAs($manager)->post(route('proptech.flex.checkin', $booking), [
            'passport_token' => 'QR-PASSPORT-VALID-TEST-TOKEN',
        ]);

        $checkInResponse->assertRedirect(route('proptech.flex.index'));
        $checkInResponse->assertSessionHas('success');
        $this->assertEquals('CHECKED_IN', $booking->refresh()->status);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get(route('proptech.flex.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('proptech.flex.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_flex_audit_passes_cleanly(): void
    {
        $code = Artisan::call('prp:audit-flex');
        $this->assertSame(0, $code);
    }

    public function test_e_prp_audit_flex_fails_when_data_is_corrupted(): void
    {
        $fixture = new ProptechFlexCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('prp:audit-flex');
        $this->assertSame(1, $code);
    }
}
