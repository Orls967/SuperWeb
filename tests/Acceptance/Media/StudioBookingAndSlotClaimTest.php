<?php

declare(strict_types=1);

namespace Tests\Acceptance\Media;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Med\Application\Services\StudioBookingService;
use Modules\Med\Domain\Models\MedStudio;
use Modules\Med\Domain\Models\MedStudioSlotClaim;
use RuntimeException;
use Tests\TestCase;

class StudioBookingAndSlotClaimTest extends TestCase
{
    use RefreshDatabase;

    protected StudioBookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->bookingService = app(StudioBookingService::class);
    }

    public function test_studio_booking_creates_slot_claims_atomically(): void
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-TST-01',
            'name' => 'Sound Stage Alpha',
            'facility_type' => 'SOUND_STAGE',
            'location_city' => 'Jakarta',
            'hourly_rate_minor' => 2_000_000,
            'full_day_rate_minor' => 15_000_000,
            'status' => 'AVAILABLE',
        ]);

        $booking = $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-CORP-01',
            'start_time' => '2026-11-01 09:00:00',
            'end_time' => '2026-11-01 10:00:00', // 2 discrete 30-min slots
            'idempotency_key' => 'BKG-HOLD:001',
        ]);

        expect($booking->status)->toBe('HELD')
            ->and(MedStudioSlotClaim::where('booking_id', $booking->id)->count())->toBe(2);
    }

    public function test_two_competing_bookings_for_same_slot_allows_exactly_one(): void
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-TST-02',
            'name' => 'Podcast Room Beta',
            'facility_type' => 'PODCAST_ROOM',
            'location_city' => 'Jakarta',
            'hourly_rate_minor' => 500_000,
            'full_day_rate_minor' => 3_500_000,
            'status' => 'AVAILABLE',
        ]);

        $booking1 = $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-01',
            'start_time' => '2026-11-01 14:00:00',
            'end_time' => '2026-11-01 15:00:00',
            'idempotency_key' => 'BKG-HOLD:COMP-1',
        ]);

        expect($booking1->status)->toBe('HELD');

        $this->expectException(RuntimeException::class);
        $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-02',
            'start_time' => '2026-11-01 14:30:00',
            'end_time' => '2026-11-01 15:30:00', // Overlaps on 14:30..15:00
            'idempotency_key' => 'BKG-HOLD:COMP-2',
        ]);
    }

    public function test_contiguous_slot_booking_succeeds_without_conflict(): void
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-TST-03',
            'name' => 'Stage Gamma',
            'facility_type' => 'SOUND_STAGE',
            'location_city' => 'Surabaya',
            'hourly_rate_minor' => 1_500_000,
            'full_day_rate_minor' => 10_000_000,
            'status' => 'AVAILABLE',
        ]);

        $booking1 = $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-01',
            'start_time' => '2026-11-01 10:00:00',
            'end_time' => '2026-11-01 12:00:00',
            'idempotency_key' => 'BKG-HOLD:CONTIG-1',
        ]);

        // Immediately contiguous: starts right at 12:00:00
        $booking2 = $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-02',
            'start_time' => '2026-11-01 12:00:00',
            'end_time' => '2026-11-01 14:00:00',
            'idempotency_key' => 'BKG-HOLD:CONTIG-2',
        ]);

        expect($booking1->status)->toBe('HELD')
            ->and($booking2->status)->toBe('HELD');
    }

    public function test_booking_lifecycle_hold_confirm_checkin(): void
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-TST-04',
            'name' => 'Virtual Production 1',
            'facility_type' => 'VIRTUAL_PRODUCTION',
            'location_city' => 'Jakarta',
            'hourly_rate_minor' => 5_000_000,
            'full_day_rate_minor' => 35_000_000,
            'status' => 'AVAILABLE',
        ]);

        $booking = $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-01',
            'start_time' => '2026-11-02 08:00:00',
            'end_time' => '2026-11-02 10:00:00',
            'idempotency_key' => 'BKG-HOLD:LF-1',
        ]);

        $confirmed = $this->bookingService->confirmBooking($booking->id);
        expect($confirmed->status)->toBe('CONFIRMED');

        $checkedIn = $this->bookingService->checkInBooking($booking->id);
        expect($checkedIn->status)->toBe('CHECKED_IN');
    }

    public function test_cancelled_booking_releases_slot_claims(): void
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-TST-05',
            'name' => 'Podcast Room Delta',
            'facility_type' => 'PODCAST_ROOM',
            'location_city' => 'Jakarta',
            'hourly_rate_minor' => 500_000,
            'full_day_rate_minor' => 3_500_000,
            'status' => 'AVAILABLE',
        ]);

        $booking = $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-01',
            'start_time' => '2026-11-03 10:00:00',
            'end_time' => '2026-11-03 11:00:00',
            'idempotency_key' => 'BKG-HOLD:REL-1',
        ]);

        expect(MedStudioSlotClaim::where('booking_id', $booking->id)->count())->toBe(2);

        $cancelled = $this->bookingService->cancelBooking($booking->id, 'Client reschedule');
        expect($cancelled->status)->toBe('CANCELLED')
            ->and(MedStudioSlotClaim::where('booking_id', $booking->id)->count())->toBe(0);

        // Now another client can book that exact slot
        $booking2 = $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-02',
            'start_time' => '2026-11-03 10:00:00',
            'end_time' => '2026-11-03 11:00:00',
            'idempotency_key' => 'BKG-HOLD:REL-2',
        ]);
        expect($booking2->status)->toBe('HELD');
    }

    public function test_no_show_booking_applies_penalty_fee(): void
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-TST-06',
            'name' => 'Sound Stage Epsilon',
            'facility_type' => 'SOUND_STAGE',
            'location_city' => 'Jakarta',
            'hourly_rate_minor' => 2_000_000,
            'full_day_rate_minor' => 15_000_000,
            'status' => 'AVAILABLE',
        ]);

        $booking = $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-01',
            'start_time' => '2026-11-04 09:00:00',
            'end_time' => '2026-11-04 11:00:00',
            'idempotency_key' => 'BKG-HOLD:NS-1',
        ]);

        $this->bookingService->confirmBooking($booking->id);

        $noShow = $this->bookingService->markNoShow($booking->id);
        expect($noShow->status)->toBe('NO_SHOW')
            ->and($noShow->cancellation_fee_minor)->toBeGreaterThan(0);
    }

    public function test_booking_with_invalid_duration_is_rejected(): void
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-TST-07',
            'name' => 'Sound Stage Zeta',
            'facility_type' => 'SOUND_STAGE',
            'location_city' => 'Jakarta',
            'hourly_rate_minor' => 2_000_000,
            'full_day_rate_minor' => 15_000_000,
            'status' => 'AVAILABLE',
        ]);

        $this->expectException(InvalidArgumentException::class);
        // 15 minutes is less than minimum 30-min slot unit
        $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-01',
            'start_time' => '2026-11-05 10:00:00',
            'end_time' => '2026-11-05 10:15:00',
            'idempotency_key' => 'BKG-HOLD:INV-DUR',
        ]);
    }

    public function test_booking_with_end_before_start_is_rejected(): void
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-TST-08',
            'name' => 'Sound Stage Eta',
            'facility_type' => 'SOUND_STAGE',
            'location_city' => 'Jakarta',
            'hourly_rate_minor' => 2_000_000,
            'full_day_rate_minor' => 15_000_000,
            'status' => 'AVAILABLE',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-01',
            'start_time' => '2026-11-05 12:00:00',
            'end_time' => '2026-11-05 10:00:00',
            'idempotency_key' => 'BKG-HOLD:INV-REV',
        ]);
    }

    public function test_booking_studio_under_maintenance_is_rejected(): void
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-TST-09',
            'name' => 'Sound Stage Theta',
            'facility_type' => 'SOUND_STAGE',
            'location_city' => 'Jakarta',
            'hourly_rate_minor' => 2_000_000,
            'full_day_rate_minor' => 15_000_000,
            'status' => 'MAINTENANCE',
        ]);

        $this->expectException(RuntimeException::class);
        $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-01',
            'start_time' => '2026-11-06 10:00:00',
            'end_time' => '2026-11-06 12:00:00',
            'idempotency_key' => 'BKG-HOLD:MAINT',
        ]);
    }

    public function test_booking_is_idempotent_with_same_key(): void
    {
        $studio = MedStudio::create([
            'id' => (string) Str::uuid(),
            'studio_code' => 'STU-TST-10',
            'name' => 'Sound Stage Iota',
            'facility_type' => 'SOUND_STAGE',
            'location_city' => 'Jakarta',
            'hourly_rate_minor' => 2_000_000,
            'full_day_rate_minor' => 15_000_000,
            'status' => 'AVAILABLE',
        ]);

        $b1 = $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-01',
            'start_time' => '2026-11-07 10:00:00',
            'end_time' => '2026-11-07 12:00:00',
            'idempotency_key' => 'BKG-HOLD:IDEMP-01',
        ]);

        $b2 = $this->bookingService->holdBooking([
            'studio_id' => $studio->id,
            'client_entity_id' => 'CLIENT-01',
            'start_time' => '2026-11-07 10:00:00',
            'end_time' => '2026-11-07 12:00:00',
            'idempotency_key' => 'BKG-HOLD:IDEMP-01',
        ]);

        expect($b1->id)->toBe($b2->id);
    }
}
