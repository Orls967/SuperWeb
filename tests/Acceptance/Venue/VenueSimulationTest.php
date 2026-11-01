<?php

declare(strict_types=1);

namespace Tests\Acceptance\Venue;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Party\Contracts\PartyAgeVerificationInterface;
use Modules\Venue\Application\Actions\CaptureTableDepositAction;
use Modules\Venue\Application\Actions\CloseEntertainmentEventAction;
use Modules\Venue\Application\Actions\ForfeitTableNoShowDepositAction;
use Modules\Venue\Application\Actions\HoldGeneralAdmissionQuotaAction;
use Modules\Venue\Application\Actions\PurchaseVenueTicketAction;
use Modules\Venue\Application\Actions\RefundVenueTicketAction;
use Modules\Venue\Application\Actions\ReserveTableWithMinimumSpendDepositAction;
use Modules\Venue\Application\Actions\ScanTicketAtGateAction;
use Modules\Venue\Application\Services\VenuePaymentGateway;
use Modules\Venue\database\seeders\VenueRbacSeeder;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\InventoryCounter;
use Modules\Venue\Domain\Models\TicketTier;
use Modules\Venue\Domain\Services\VenueQrSigner;
use Tests\TestCase;

class VenueSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulasi_skala_penuh_3_venue_12_event_ribuan_transaksi_dan_audit_dua_sumber(): void
    {
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(VenueRbacSeeder::class);

        // Mock payment gateway
        $mockGateway = $this->createMock(VenuePaymentGateway::class);
        $mockGateway->method('authorize')->willReturn(['success' => true]);
        $mockGateway->method('capture')->willReturn(['success' => true]);
        $this->app->instance(VenuePaymentGateway::class, $mockGateway);

        // Mock Party KYC: semua pengunjung usia 25 tahun (di atas 21)
        $mockParty = $this->createMock(PartyAgeVerificationInterface::class);
        $mockParty->method('getVerifiedAge')->willReturn(25);
        $this->app->instance(PartyAgeVerificationInterface::class, $mockParty);

        // 1. Buat 3 Venue
        $venues = [];
        $venueCodes = ['BC-CANGGU', 'BC-ULUWATU', 'BC-SEMINYAK'];
        foreach ($venueCodes as $idx => $code) {
            $venues[] = EntertainmentVenue::create([
                'venue_code' => $code,
                'name' => "Luxury Venue {$code}",
                'venue_type' => 'BEACH_CLUB',
                'city' => 'Badung',
                'max_legal_capacity' => 3000,
                'min_age_requirement' => 21,
            ]);
        }

        // 2. Buat 12 Event (4 per venue) beserta zona dan tier tiket
        $events = [];
        $tiers = [];
        $holdAction = app(HoldGeneralAdmissionQuotaAction::class);
        $purchaseAction = app(PurchaseVenueTicketAction::class);
        $refundAction = app(RefundVenueTicketAction::class);
        $scanAction = app(ScanTicketAtGateAction::class);
        $reserveTableAction = app(ReserveTableWithMinimumSpendDepositAction::class);
        $captureTableAction = app(CaptureTableDepositAction::class);
        $forfeitTableAction = app(ForfeitTableNoShowDepositAction::class);
        $closeEventAction = app(CloseEntertainmentEventAction::class);
        $qrSigner = app(VenueQrSigner::class);

        $eventSeq = 1;
        foreach ($venues as $venue) {
            for ($e = 1; $e <= 4; $e++) {
                $zone = EntertainmentZone::create([
                    'zone_code' => "ZONE-{$venue->venue_code}-{$e}",
                    'venue_id' => $venue->id,
                    'name' => "Main Zone Event {$eventSeq}",
                    'capacity_limit' => 2000,
                    'current_occupancy' => 0,
                    'crowd_lockdown_active' => false,
                ]);

                $event = EntertainmentEvent::create([
                    'event_code' => sprintf('EVT-%04d', $eventSeq),
                    'venue_id' => $venue->id,
                    'title' => "Festival Event {$eventSeq}",
                    'doors_open_at' => now()->addDays($e),
                    'ticket_floor_price_idr' => 200000,
                    'ticket_ceiling_price_idr' => 1000000,
                    'current_ticket_price_idr' => 500000,
                ]);

                $tier = TicketTier::create([
                    'event_id' => $event->id,
                    'zone_id' => $zone->id,
                    'name' => "GA Regular Event {$eventSeq}",
                    'tier_code' => "GA-{$eventSeq}",
                    'tier_type' => 'GENERAL_ADMISSION',
                    'price_idr' => 500000,
                    'quota' => 300,
                    'is_numbered_seating' => false,
                    'allows_reentry' => false,
                ]);

                InventoryCounter::create([
                    'event_id' => $event->id,
                    'tier_id' => $tier->id,
                    'total_quota' => 300,
                    'held_count' => 0,
                    'sold_count' => 0,
                ]);

                $events[] = $event;
                $tiers[$event->id] = $tier;
                $eventSeq++;
            }
        }

        $this->assertCount(12, $events);

        // 3. Eksekusi Pembelian Tiket Massal (250 tiket x 12 event = 3.000 tiket beli)
        $purchasedTicketIds = [];
        $buyerId = 1000;
        foreach ($events as $event) {
            $tier = $tiers[$event->id];
            // Setiap event: 50 pembelian @ 5 tiket = 250 tiket
            for ($b = 1; $b <= 50; $b++) {
                $buyerId++;
                $hold = $holdAction->handle(
                    eventId: $event->id,
                    tierId: $tier->id,
                    quantity: 5,
                    buyerUserId: $buyerId,
                    idempotencyKey: "ven:sim:hold:{$event->id}:{$b}"
                );

                $res = $purchaseAction->handle(
                    holdToken: $hold['hold_token'],
                    buyerUserId: $buyerId,
                    paymentMethod: 'wallet',
                    idempotencyKey: "ven:sim:buy:{$event->id}:{$b}"
                );

                foreach ($res['tickets'] as $t) {
                    $purchasedTicketIds[] = $t['id'];
                }
            }
        }

        $this->assertCount(3000, $purchasedTicketIds);

        // 4. Eksekusi 300 Refund (25 refund per event pada 12 event)
        $refundedCount = 0;
        for ($i = 0; $i < 300; $i++) {
            $ticketId = $purchasedTicketIds[$i * 10]; // Ambil 300 tiket tersebar
            $refundAction->handle(
                ticketId: $ticketId,
                reason: "Refund simulasi #{$i}",
                idempotencyKey: "ven:sim:ref:{$ticketId}"
            );
            $refundedCount++;
        }
        $this->assertSame(300, $refundedCount);

        // 5. Eksekusi 2.000 Gate Scans Turnstile IN
        $scannedCount = 0;
        $ticketIndex = 0;
        while ($scannedCount < 2000 && $ticketIndex < count($purchasedTicketIds)) {
            $tId = $purchasedTicketIds[$ticketIndex];
            $ticket = EntertainmentTicket::findOrFail($tId);
            $ticketIndex++;

            if ($ticket->status === 'ISSUED') {
                $qr = $qrSigner->generatePayload($ticket);
                $scanRes = $scanAction->handle(
                    qrPayload: $qr,
                    gateCode: 'GATE-SIM-01',
                    direction: 'IN',
                    scannedByUserId: 999,
                    idempotencyKey: "ven:sim:scan:{$tId}"
                );

                $this->assertSame('ALLOWED', $scanRes['status']);
                $scannedCount++;
            }
        }
        $this->assertSame(2000, $scannedCount);

        // 6. Eksekusi 40 Reservasi Meja VIP (dengan capture & no-show)
        for ($m = 1; $m <= 40; $m++) {
            $targetEvent = $events[$m % 12];
            $tableRes = $reserveTableAction->handle(
                eventId: $targetEvent->id,
                userId: 8000 + $m,
                tableNumber: "VIP-TBL-{$m}",
                minimumSpendIdr: 5000000,
                depositAmountIdr: 2000000,
                idempotencyKey: "ven:sim:tbl:res:{$m}"
            );

            if ($m % 2 === 0) {
                $captureTableAction->handle(
                    bookingId: $tableRes['booking']->id,
                    idempotencyKey: "ven:sim:tbl:cap:{$m}"
                );
            } else {
                $forfeitTableAction->handle(
                    bookingId: $tableRes['booking']->id,
                    reason: 'No-show guest toleransi habis',
                    idempotencyKey: "ven:sim:tbl:forfeit:{$m}"
                );
            }
        }

        // 7. Tutup Seluruh 12 Event
        foreach ($events as $event) {
            $closeRes = $closeEventAction->handle(
                eventId: $event->id,
                platformFeeBps: 500,
                idempotencyKey: "ven:sim:close:{$event->id}"
            );
            $this->assertTrue($closeRes['success']);
            $this->assertSame('CLOSED', $event->fresh()->status);
        }

        // 8. Invarian Akhir:
        // A. Zero unearned residue: seluruh saldo kewajiban unearned harus 0
        $unearnedBalance = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ven:ticket_unearned:IDR'))->sum('amount');
        $this->assertSame(0, $unearnedBalance, 'Residu unearned revenue harus tepat 0 setelah semua event ditutup');

        // B. Ledger balance Σ debit == Σ credit
        $totalDebits = abs((int) LedgerEntry::where('amount', '<', 0)->sum('amount'));
        $totalCredits = (int) LedgerEntry::where('amount', '>', 0)->sum('amount');
        $this->assertGreaterThan(0, $totalDebits);
        $this->assertSame($totalDebits, $totalCredits, 'Double-entry ledger harus seimbang nol');
        $this->assertSame(0, (int) LedgerEntry::sum('amount'));

        // C. Zero double-selling: nomor tiket unik
        $distinctTicketNumbers = EntertainmentTicket::distinct('ticket_number')->count('ticket_number');
        $this->assertSame(3000, $distinctTicketNumbers, 'Tidak boleh ada duplikasi nomor tiket (zero double-selling)');

        // D. Audit dua sumber ven:audit exit code 0
        $exitCode = Artisan::call('ven:audit');
        $this->assertSame(0, $exitCode, 'ven:audit harus keluar dengan kode 0 (bersih)');
        $output = Artisan::output();
        $this->assertStringContainsString('Venue audit PASSED with 0 discrepancy', $output);
    }
}
