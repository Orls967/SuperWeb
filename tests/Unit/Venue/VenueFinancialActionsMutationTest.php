<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Venue\Application\Actions\CloseEntertainmentEventAction;
use Modules\Venue\Application\Actions\ExecuteArtistContractEscrowAndWhtPayoutAction;
use Modules\Venue\Application\Actions\HoldGeneralAdmissionQuotaAction;
use Modules\Venue\Application\Actions\PurchaseVenueTicketAction;
use Modules\Venue\Domain\Models\ArtistContract;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\InventoryCounter;
use Modules\Venue\Domain\Models\TicketTier;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(
    ExecuteArtistContractEscrowAndWhtPayoutAction::class,
    CloseEntertainmentEventAction::class
);

describe('Venue Financial Actions Mutation Test', function () {
    beforeEach(function () {
        $this->seed(LedgerAccountsSeeder::class);

        $this->venue = EntertainmentVenue::create([
            'venue_code' => 'VEN-MUT-01',
            'name' => 'Mutation Test Venue',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Badung',
            'max_legal_capacity' => 1000,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::create([
            'zone_code' => 'ZONE-MUT-01',
            'venue_id' => $this->venue->id,
            'name' => 'Mutation Deck',
            'capacity_limit' => 500,
            'current_occupancy' => 0,
            'crowd_lockdown_active' => false,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-MUT-01',
            'venue_id' => $this->venue->id,
            'title' => 'Mutation Event',
            'doors_open_at' => now()->addDays(3),
            'ticket_floor_price_idr' => 200000,
            'ticket_ceiling_price_idr' => 1000000,
            'current_ticket_price_idr' => 500000,
        ]);

        $this->tier = TicketTier::create([
            'event_id' => $this->event->id,
            'zone_id' => $this->zone->id,
            'name' => 'GA Mutation',
            'tier_code' => 'GA-MUT',
            'tier_type' => 'GENERAL_ADMISSION',
            'price_idr' => 500000,
            'quota' => 100,
            'is_numbered_seating' => false,
            'allows_reentry' => false,
        ]);

        InventoryCounter::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->tier->id,
            'total_quota' => 100,
            'held_count' => 0,
            'sold_count' => 0,
        ]);
    });

    it('settles artist contract accurately with withholding tax and idempotency', function () {
        $contract = ArtistContract::create([
            'id' => (string) Str::uuid(),
            'venue_id' => (string) Str::uuid(),
            'event_id' => (string) $this->event->id,
            'contract_number' => 'ART-MUT-001',
            'artist_name' => 'Peggy Gou',
            'advance_amount' => 50000000,
            'door_share_percentage' => '20.00',
            'status' => 'active',
        ]);

        $action = app(ExecuteArtistContractEscrowAndWhtPayoutAction::class);

        // Test empty idempotency key
        expect(fn () => $action->handle((string) $contract->id, 500000000, 200, ''))
            ->toThrow(InvalidArgumentException::class, 'Idempotency key wajib diisi (U-4)');

        // Settlement: 20% of 500jt = 100jt gross, 200 bps (2%) = 2jt tax, advance 50jt -> net 48jt
        $res = $action->handle((string) $contract->id, 500000000, 200, 'ven:mut:art:1');
        expect($res['gross_share_idr'])->toBe(100000000)
            ->and($res['wht_tax_idr'])->toBe(2000000)
            ->and($res['advance_deducted_idr'])->toBe(50000000)
            ->and($res['net_payout_idr'])->toBe(48000000)
            ->and($res['contract']->status)->toBe('settled')
            ->and($contract->fresh()->door_share_gross)->toBe(100000000)
            ->and($contract->fresh()->net_payout_amount)->toBe(48000000);

        // Idempotency: same key returns identical
        $idempRes = $action->handle((string) $contract->id, 500000000, 200, 'ven:mut:art:1');
        expect($idempRes['net_payout_idr'])->toBe(48000000)
            ->and($idempRes['gross_share_idr'])->toBe(100000000)
            ->and($idempRes['wht_tax_idr'])->toBe(2000000);

        // Different key for already settled contract throws exception
        expect(fn () => $action->handle((string) $contract->id, 500000000, 200, 'ven:mut:art:different'))
            ->toThrow(RuntimeException::class, 'Kontrak artis sudah diselesaikan (settled)');
    });

    it('floors artist net payout at zero when advance exceeds share', function () {
        $contract = ArtistContract::create([
            'id' => (string) Str::uuid(),
            'venue_id' => (string) Str::uuid(),
            'event_id' => (string) $this->event->id,
            'contract_number' => 'ART-MUT-002',
            'artist_name' => 'Tale of Us',
            'advance_amount' => 150000000, // Advance 150jt
            'door_share_percentage' => '10.00', // 10% of 200jt = 20jt
            'status' => 'active',
        ]);

        $action = app(ExecuteArtistContractEscrowAndWhtPayoutAction::class);
        $res = $action->handle((string) $contract->id, 200000000, 200, 'ven:mut:art:floor');
        expect($res['net_payout_idr'])->toBe(0)
            ->and($res['gross_share_idr'])->toBe(20000000)
            ->and($res['wht_tax_idr'])->toBe(400000);
    });

    it('closes event recognizing unearned revenue and platform fee with idempotency', function () {
        User::factory()->create(['id' => 777]);
        $hold = app(HoldGeneralAdmissionQuotaAction::class)->handle($this->event->id, $this->tier->id, 2, 777, 'ven:mut:hold:1');
        app(PurchaseVenueTicketAction::class)->handle($hold['hold_token'], 777, 'wallet', 'ven:mut:buy:1');

        $closeAction = app(CloseEntertainmentEventAction::class);

        // Empty key throws exception
        expect(fn () => $closeAction->handle($this->event->id, 500, ''))
            ->toThrow(InvalidArgumentException::class, 'Idempotency key wajib diisi (U-4)');

        // Close event: 2 * 500.000 = 1.000.000 unearned. Fee 500 bps (5%) = 50.000, Net = 950.000
        $res = $closeAction->handle($this->event->id, 500, 'ven:mut:close:1');
        expect($res['success'])->toBeTrue()
            ->and($res['total_unearned_recognized_idr'])->toBe(1000000)
            ->and($res['platform_fee_idr'])->toBe(50000)
            ->and($res['organizer_net_share_idr'])->toBe(950000)
            ->and($this->event->fresh()->status)->toBe('CLOSED');

        // Idempotency: closing again returns same
        $resIdemp = $closeAction->handle($this->event->id, 500, 'ven:mut:close:same');
        expect($resIdemp['total_unearned_recognized_idr'])->toBe(1000000)
            ->and($resIdemp['platform_fee_idr'])->toBe(50000)
            ->and($resIdemp['organizer_net_share_idr'])->toBe(950000);
    });
});
