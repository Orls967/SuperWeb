<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Domain\Models\CrowdCrushIncident;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\GateScanLog;
use Modules\Venue\Domain\Models\TableBottleOrder;
use Modules\Venue\Domain\Models\TurnstileDevice;
use Modules\Venue\Domain\Models\VenueBagInspection;
use Modules\Venue\Domain\Models\VenuePermit;
use Modules\Venue\Domain\Services\VenueTicketChainService;
use Modules\Venue\Ledger\VenueTransactionTypes;

final class VenueT1OpsSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now();
        $ledger = app(Ledger::class);
        $chainService = app(VenueTicketChainService::class);

        // Patron User
        $user = User::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Aditya Pratama (VIP Patron)',
                'email' => 'patron@superweb.test',
                'password' => bcrypt('password'),
            ]
        );

        // 1. Enam (6) Venue Standar
        $venuesData = [
            ['code' => 'VEN-ATLAS-01', 'name' => 'Atlas Beach Club Canggu', 'type' => 'BEACH_CLUB', 'city' => 'Badung, Bali', 'age' => 21, 'cap' => 10000],
            ['code' => 'VEN-SAVAYA-02', 'name' => 'Savaya Cliffside Uluwatu', 'type' => 'CLIFF_CLUB', 'city' => 'Badung, Bali', 'age' => 21, 'cap' => 4500],
            ['code' => 'VEN-JIEXPO-03', 'name' => 'JIExpo Kemayoran Arena', 'type' => 'CONCERT_HALL', 'city' => 'Jakarta Pusat', 'age' => 18, 'cap' => 18000],
            ['code' => 'VEN-GBK-04', 'name' => 'Gelora Bung Karno Stadium', 'type' => 'STADIUM', 'city' => 'Jakarta Pusat', 'age' => 15, 'cap' => 77000],
            ['code' => 'VEN-COLOSSEUM-05', 'name' => 'Colosseum Club Kota', 'type' => 'NIGHTCLUB', 'city' => 'Jakarta Barat', 'age' => 21, 'cap' => 2500],
            ['code' => 'VEN-PRAMBANAN-06', 'name' => 'Prambanan Open Air Theatre', 'type' => 'HERITAGE_ARENA', 'city' => 'Sleman, DIY', 'age' => 12, 'cap' => 12000],
        ];

        $venueModels = [];
        foreach ($venuesData as $vd) {
            $venueModels[$vd['code']] = EntertainmentVenue::firstOrCreate(
                ['venue_code' => $vd['code']],
                [
                    'name' => $vd['name'],
                    'venue_type' => $vd['type'],
                    'city' => $vd['city'],
                    'min_age_requirement' => $vd['age'],
                    'max_legal_capacity' => $vd['cap'],
                ]
            );
        }

        // 2. Tiga Puluh Enam (36) Zona (6 per venue)
        $zoneTypes = ['INDOOR', 'OUTDOOR', 'VIP_TERRACE', 'STAGE_PIT', 'MEZZANINE', 'BEACH_DECK'];
        $zoneModels = [];
        foreach ($venueModels as $vCode => $vModel) {
            foreach ($zoneTypes as $idx => $zType) {
                $zCode = "ZN-{$vCode}-" . str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
                $zoneModels[$zCode] = EntertainmentZone::firstOrCreate(
                    ['zone_code' => $zCode],
                    [
                        'venue_id' => $vModel->id,
                        'name' => "Zone {$zType} {$vModel->name}",
                        'capacity_limit' => (int) floor($vModel->max_legal_capacity / 6),
                        'current_occupancy' => 0,
                        'crowd_lockdown_active' => false,
                    ]
                );
            }
        }

        // 3. Tiga Belas (13) Turnstile IoT Devices
        for ($t = 1; $t <= 8; $t++) {
            $tCode = 'TRN-LANE-' . str_pad((string) $t, 2, '0', STR_PAD_LEFT);
            TurnstileDevice::firstOrCreate(
                ['device_code' => $tCode],
                [
                    'zone_id' => $zoneModels['ZN-VEN-ATLAS-01-01']->id,
                    'hardware_serial' => "HW-SN-{$tCode}-2026",
                    'firmware_version' => 'v2.4.1',
                    'ip_address' => "192.168.10." . (100 + $t),
                    'turnstile_status' => 'ONLINE',
                    'optical_beam_trips' => 0,
                    'last_heartbeat_at' => $now,
                ]
            );
        }

        // 4. Katalog Bernama Kasus Uji & Produksi (15 Kasus)
        $atlas = $venueModels['VEN-ATLAS-01'];
        $eventSunset = EntertainmentEvent::firstOrCreate(
            ['event_code' => 'EVT-CAS-01-SUNSET'],
            [
                'venue_id' => $atlas->id,
                'title' => 'Bali Summer Sound Festival 2026',
                'doors_open_at' => $now->addDays(5),
                'ticket_floor_price_idr' => 350000,
                'ticket_ceiling_price_idr' => 1500000,
                'current_ticket_price_idr' => 750000,
                'status' => 'on_sale',
            ]
        );

        // Izin Keramaian Resmi Kepolisian & Damkar
        VenuePermit::firstOrCreate(
            ['permit_number' => 'POL-BALI-2026-0089'],
            [
                'event_id' => $eventSunset->id,
                'issuing_authority' => 'Polda Bali Ditintelkam',
                'max_legal_capacity' => 5000,
                'valid_from' => $now->subDays(10),
                'valid_until' => $now->addDays(10),
                'status' => 'APPROVED',
            ]
        );

        // Tiket-tiket simulasi
        $ticketCases = [
            ['num' => 'TCK-CAS-01-001', 'price' => 750000, 'status' => 'issued'],
            ['num' => 'TCK-CAS-02-002', 'price' => 750000, 'status' => 'issued'],
            ['num' => 'TCK-CAS-03-003', 'price' => 750000, 'status' => 'scanned_in'],
            ['num' => 'TCK-CAS-04-004', 'price' => 750000, 'status' => 'issued'],
            ['num' => 'TCK-CAS-05-005', 'price' => 500000, 'status' => 'issued'],
        ];

        $prevHash = null;
        foreach ($ticketCases as $idx => $tc) {
            $uuid = '00000089-0000-0000-0000-' . str_pad((string) ($idx + 1), 12, '0', STR_PAD_LEFT);
            $hashData = $chainService->computeTicketHash(
                eventId: (int) $eventSunset->id,
                ticketNumber: $tc['num'],
                ticketUuid: $uuid,
                pricePaidIdr: $tc['price'],
                latestTicketHash: $prevHash
            );
            $prevHash = $hashData['ticket_hash'];

            $ticket = EntertainmentTicket::where('ticket_number', $tc['num'])->first();
            if ($ticket === null) {
                $ticket = EntertainmentTicket::create([
                    'ticket_number' => $tc['num'],
                    'ticket_uuid' => $uuid,
                    'event_id' => $eventSunset->id,
                    'buyer_user_id' => $user->id,
                    'price_paid_idr' => $tc['price'],
                    'ticket_hash' => $hashData['ticket_hash'],
                    'prev_hash' => $hashData['prev_hash'],
                    'idempotency_key' => "ven:seed_sale:{$tc['num']}",
                    'status' => $tc['status'],
                    'scanned_at' => $tc['status'] === 'scanned_in' ? $now->subHour() : null,
                    'last_direction' => $tc['status'] === 'scanned_in' ? 'IN' : null,
                ]);
            } else {
                \Illuminate\Support\Facades\DB::table('ven_entertainment_tickets')
                    ->where('id', $ticket->id)
                    ->update([
                        'ticket_hash' => $hashData['ticket_hash'],
                        'prev_hash' => $hashData['prev_hash'],
                    ]);
            }

            // Jurnal ledger unearned ticket sale
            $ledger->post(new PostingDTO(
                type: VenueTransactionTypes::VENUE_TICKET_SALE->value,
                description: "Ticket sale {$tc['num']}",
                idempotencyKey: "ven:seed_sale:{$tc['num']}",
                entries: Posting::lines()
                    ->debit('ven:ticket_clearing:IDR', $tc['price'])
                    ->credit('ven:ticket_unearned:IDR', $tc['price'])
                    ->toEntries('IDR'),
                referenceType: 'VENUE_TICKET',
                referenceId: (string) $ticket->id
            ));
        }

        // Booking Meja VIP
        $booking = EntertainmentTableBooking::firstOrCreate(
            ['booking_code' => 'BK-VIP-CABANA-01'],
            [
                'event_id' => $eventSunset->id,
                'user_id' => $user->id,
                'table_number' => 'CABANA-01',
                'deposit_amount_idr' => 5000000,
                'minimum_spend_idr' => 10000000,
                'idempotency_key' => 'SEED-BK-VIP-CABANA-01',
                'status' => 'deposit_held',
            ]
        );

        $ledger->post(new PostingDTO(
            type: VenueTransactionTypes::VENUE_VIP_TABLE_ESCROW->value,
            description: 'Hold deposit meja VIP Cabana 01',
            idempotencyKey: "ven:seed_tbl:{$booking->booking_code}",
            entries: Posting::lines()
                ->debit('ven:vip_escrow:IDR', 5000000)
                ->credit('ven:vip_unearned_rev:IDR', 5000000)
                ->toEntries('IDR'),
            referenceType: 'VENUE_TABLE_BOOKING',
            referenceId: (string) $booking->id
        ));

        // Botol Champagne di Meja
        TableBottleOrder::firstOrCreate(
            ['booking_id' => $booking->id, 'bottle_sku' => 'CHAMP-DOM-LUM'],
            [
                'bottle_name' => 'Dom Perignon Luminous Vintage',
                'quantity' => 1,
                'unit_price_idr' => 6500000,
                'total_price_idr' => 6500000,
                'spout_ml_dispensed' => 750,
                'variance_ml' => 0,
                'status' => 'served',
            ]
        );

        // Audit Tas Acak
        VenueBagInspection::firstOrCreate(
            ['inspection_code' => 'BAG-INSP-2026-001'],
            [
                'event_id' => $eventSunset->id,
                'gate_code' => 'TRN-LANE-01',
                'inspector_badge' => 'POL-SEC-049',
                'contraband_found' => false,
                'status' => 'cleared',
                'inspected_at' => $now,
            ]
        );
    }
}
