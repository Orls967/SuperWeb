<?php

declare(strict_types=1);

namespace Modules\Venue\database\seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Services\VenueTicketChainService;

class VenueDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Demo Patron',
                'email' => 'patron@superweb.test',
                'password' => bcrypt('password'),
            ]
        );

        $venue = EntertainmentVenue::firstOrCreate(
            ['venue_code' => 'VEN-ATLAS-01'],
            [
                'name' => 'Atlas Beach Fest Bali',
                'venue_type' => 'BEACH_CLUB',
                'city' => 'Badung, Bali',
                'min_age_requirement' => 18,
                'max_legal_capacity' => 5000,
            ]
        );

        $event = EntertainmentEvent::firstOrCreate(
            ['event_code' => 'EVT-BALI-SUNSET-01'],
            [
                'venue_id' => $venue->id,
                'title' => 'Bali Summer Sound Festival',
                'doors_open_at' => now()->addDays(7),
                'ticket_floor_price_idr' => 250000,
                'ticket_ceiling_price_idr' => 1500000,
                'current_ticket_price_idr' => 750000,
            ]
        );

        EntertainmentZone::firstOrCreate(
            ['zone_code' => 'ZN-POOL-01'],
            [
                'venue_id' => $venue->id,
                'name' => 'Lagoon Pool Deck',
                'capacity_limit' => 300,
                'current_occupancy' => 45,
                'crowd_lockdown_active' => false,
            ]
        );

        $chainService = app(VenueTicketChainService::class);
        $ticketUuid = (string) Str::uuid();
        $hashData = $chainService->computeTicketHash(
            eventId: (int) $event->id,
            ticketNumber: 'TCK-DEMO-001',
            ticketUuid: $ticketUuid,
            pricePaidIdr: 750000,
            latestTicketHash: null
        );

        $ticket = EntertainmentTicket::firstOrCreate(
            ['ticket_number' => 'TCK-DEMO-001'],
            [
                'ticket_uuid' => $ticketUuid,
                'event_id' => $event->id,
                'buyer_user_id' => $user->id,
                'price_paid_idr' => 750000,
                'ticket_hash' => $hashData['ticket_hash'],
                'prev_hash' => $hashData['prev_hash'],
                'idempotency_key' => 'DEMO-VEN-TCK-001',
                'status' => 'VALID',
            ]
        );

        // Post unearned ticket revenue to ledger to reconcile subledger and ledger balance
        $ledger = app(Ledger::class);
        $ledger->post(new PostingDTO(
            type: 'VENUE_TICKET_SALE',
            description: 'Venue demo ticket sale',
            idempotencyKey: 'DEMO-VEN-TCK-001',
            entries: Posting::lines()
                ->debit('ven:ticket_clearing:IDR', 750000)
                ->credit('ven:ticket_unearned:IDR', 750000)
                ->toEntries('IDR'),
            referenceType: 'VENUE_TICKET',
            referenceId: (string) $ticket->id,
        ));
    }
}
