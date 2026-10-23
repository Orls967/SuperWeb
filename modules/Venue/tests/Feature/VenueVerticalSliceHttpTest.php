<?php

declare(strict_types=1);

namespace Modules\Venue\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\VenAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Venue\database\seeders\VenueDemoSeeder;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Tests\TestCase;

class VenueVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            VenueDemoSeeder::class,
        ]);
    }

    public function test_a_authorized_staff_can_access_venue_and_scan_gate_ticket(): void
    {
        $staff = User::factory()->create(['role' => 'venue_staff']);
        $ticket = EntertainmentTicket::firstOrFail();
        $zone = EntertainmentZone::firstOrFail();

        // 1. Access index
        $response = $this->actingAs($staff)->get(route('venue.index'));
        $response->assertOk();
        $response->assertSeeText('Beach Club, Venue & Entertainment Operations');
        $response->assertSee($ticket->ticket_number);

        // 2. Scan gate ticket
        $scanResponse = $this->actingAs($staff)->post(route('venue.gate.scan', $ticket), [
            'zone_id' => $zone->id,
            'patron_age' => 22,
        ]);

        $scanResponse->assertRedirect(route('venue.index'));
        $scanResponse->assertSessionHas('success');

        $this->assertDatabaseHas('ven_entertainment_tickets', [
            'id' => $ticket->id,
            'status' => 'USED',
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $guest = User::factory()->create(['role' => 'customer']);
        $ticket = EntertainmentTicket::firstOrFail();
        $zone = EntertainmentZone::firstOrFail();

        $response = $this->actingAs($guest)->get(route('venue.index'));
        $response->assertForbidden();

        $scanResponse = $this->actingAs($guest)->post(route('venue.gate.scan', $ticket), [
            'zone_id' => $zone->id,
            'patron_age' => 22,
        ]);
        $scanResponse->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('venue.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_d_two_source_venue_audit_passes_cleanly(): void
    {
        $exit = Artisan::call('ven:audit');
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Venue audit PASSED with 0 discrepancy', Artisan::output());
    }

    public function test_e_ven_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new VenAuditCorruptionFixture;
        $expectedError = $fixture->corrupt();

        $exit = Artisan::call('ven:audit');
        $this->assertSame(1, $exit);
        $this->assertStringContainsString($expectedError, Artisan::output());
    }
}
