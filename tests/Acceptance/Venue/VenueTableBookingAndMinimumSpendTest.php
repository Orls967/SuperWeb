<?php

declare(strict_types=1);

namespace Tests\Acceptance\Venue;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Venue\Application\Actions\CaptureTableDepositAction;
use Modules\Venue\Application\Actions\ForfeitTableNoShowDepositAction;
use Modules\Venue\Application\Actions\ReserveTableWithMinimumSpendDepositAction;
use Modules\Venue\Application\Services\VenuePaymentGateway;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Tests\TestCase;

class VenueTableBookingAndMinimumSpendTest extends TestCase
{
    use RefreshDatabase;

    private EntertainmentVenue $venue;

    private EntertainmentEvent $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->venue = EntertainmentVenue::create([
            'venue_code' => 'BC-BALI-005',
            'name' => 'W Bali Seminyak Woobar',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Seminyak',
            'max_legal_capacity' => 1000,
            'min_age_requirement' => 21,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-WOOBAR-01',
            'venue_id' => $this->venue->id,
            'title' => 'Woobar Sunset Sessions',
            'doors_open_at' => now()->addDays(3),
            'ticket_floor_price_idr' => 200000,
            'ticket_ceiling_price_idr' => 1000000,
            'current_ticket_price_idr' => 500000,
        ]);
    }

    public function test_reservasi_meja_vip_menghasilkan_booking_code_deterministik_dan_menahan_deposit_hold(): void
    {
        $mockGateway = $this->createMock(VenuePaymentGateway::class);
        $mockGateway->expects($this->once())
            ->method('authorize')
            ->with($this->callback(function ($params) {
                return $params['amount'] === 2500000
                    && $params['idempotencyKey'] === 'ven:tbl:hold:401:1';
            }))
            ->willReturn(['success' => true, 'authorization_id' => 'auth_vip_401']);
        $this->app->instance(VenuePaymentGateway::class, $mockGateway);

        $action = app(ReserveTableWithMinimumSpendDepositAction::class);

        $result = $action->handle(
            eventId: $this->event->id,
            userId: 401,
            tableNumber: 'CABANA-01',
            minimumSpendIdr: 5000000,
            depositAmountIdr: 2500000,
            idempotencyKey: 'ven:tbl:hold:401:1'
        );

        $this->assertSame('HELD', $result['booking']->status);
        $this->assertSame('CABANA-01', $result['booking']->table_number);
        $this->assertStringStartsWith('VIP-CABANA-01-', $result['booking_code']);
    }

    public function test_reservasi_meja_yang_sudah_dipesan_pada_event_sama_ditolak(): void
    {
        EntertainmentTableBooking::create([
            'booking_code' => 'VIP-DAYBED-01-0001',
            'event_id' => $this->event->id,
            'table_number' => 'DAYBED-01',
            'user_id' => 402,
            'minimum_spend_idr' => 3000000,
            'deposit_amount_idr' => 1500000,
            'status' => 'HELD',
        ]);

        $action = app(ReserveTableWithMinimumSpendDepositAction::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Meja DAYBED-01 sudah dipesan untuk event ini');

        $action->handle(
            eventId: $this->event->id,
            userId: 403,
            tableNumber: 'DAYBED-01',
            minimumSpendIdr: 3000000,
            depositAmountIdr: 1500000,
            idempotencyKey: 'ven:tbl:hold:403:conflict'
        );
    }

    public function test_check_in_tamu_mengcapture_deposit_dan_memposting_escrow_ke_ledger(): void
    {
        $booking = EntertainmentTableBooking::create([
            'booking_code' => 'VIP-DAYBED-02-0001',
            'event_id' => $this->event->id,
            'table_number' => 'DAYBED-02',
            'user_id' => 404,
            'minimum_spend_idr' => 4000000,
            'deposit_amount_idr' => 2000000,
            'status' => 'HELD',
        ]);

        $mockGateway = $this->createMock(VenuePaymentGateway::class);
        $mockGateway->expects($this->once())
            ->method('capture')
            ->with($this->callback(function ($params) {
                return $params['amount'] === 2000000
                    && $params['idempotencyKey'] === 'ven:tbl:cap:404:1';
            }))
            ->willReturn(['success' => true]);
        $this->app->instance(VenuePaymentGateway::class, $mockGateway);

        $action = app(CaptureTableDepositAction::class);
        $res = $action->handle(
            bookingId: $booking->id,
            idempotencyKey: 'ven:tbl:cap:404:1'
        );

        $this->assertSame('SEATED', $res['booking']->status);

        // Verifikasi posting deposit ke akun ven:vip_escrow:IDR
        $escrowEntry = LedgerEntry::where('account_code', 'ven:vip_escrow:IDR')
            ->where('credit_amount', 2000000)
            ->first();
        $this->assertNotNull($escrowEntry, 'Capture deposit meja harus mengkredit ven:vip_escrow:IDR');
    }

    public function test_tamu_tidak_hadir_no_show_mengakui_deposit_sebagai_pendapatan_penalti(): void
    {
        $booking = EntertainmentTableBooking::create([
            'booking_code' => 'VIP-DAYBED-03-0001',
            'event_id' => $this->event->id,
            'table_number' => 'DAYBED-03',
            'user_id' => 405,
            'minimum_spend_idr' => 3000000,
            'deposit_amount_idr' => 1500000,
            'status' => 'HELD',
        ]);

        $mockGateway = $this->createMock(VenuePaymentGateway::class);
        $mockGateway->expects($this->once())
            ->method('capture')
            ->willReturn(['success' => true]);
        $this->app->instance(VenuePaymentGateway::class, $mockGateway);

        $action = app(ForfeitTableNoShowDepositAction::class);
        $res = $action->handle(
            bookingId: $booking->id,
            reason: 'Lewat batas waktu toleransi 2 jam setelah event dibuka',
            idempotencyKey: 'ven:tbl:forfeit:405:1'
        );

        $this->assertSame('NO_SHOW', $res['booking']->status);

        // Verifikasi pendapatan no-show diakui di ven:vip_noshow_rev:IDR
        $forfeitEntry = LedgerEntry::where('account_code', 'ven:vip_noshow_rev:IDR')
            ->where('credit_amount', 1500000)
            ->first();
        $this->assertNotNull($forfeitEntry, 'Deposit no-show harus diakui di ven:vip_noshow_rev:IDR');
    }

    public function test_pemrosesan_no_show_dua_kali_hanya_menghasilkan_satu_kali_eksekusi(): void
    {
        $booking = EntertainmentTableBooking::create([
            'booking_code' => 'VIP-DAYBED-04-0001',
            'event_id' => $this->event->id,
            'table_number' => 'DAYBED-04',
            'user_id' => 406,
            'minimum_spend_idr' => 3000000,
            'deposit_amount_idr' => 1500000,
            'status' => 'HELD',
        ]);

        $mockGateway = $this->createMock(VenuePaymentGateway::class);
        $mockGateway->method('capture')->willReturn(['success' => true]);
        $this->app->instance(VenuePaymentGateway::class, $mockGateway);

        $action = app(ForfeitTableNoShowDepositAction::class);

        $res1 = $action->handle($booking->id, 'No-show pertama', 'ven:tbl:forfeit:406:1');
        $res2 = $action->handle($booking->id, 'No-show pertama', 'ven:tbl:forfeit:406:1');

        $this->assertSame('NO_SHOW', $res1['booking']->status);
        $this->assertSame('NO_SHOW', $res2['booking']->status);
        $this->assertSame(1, LedgerEntry::where('account_code', 'ven:vip_noshow_rev:IDR')->where('credit_amount', 1500000)->count());
    }

    public function test_reservasi_meja_tanpa_idempotency_key_ditolak(): void
    {
        $action = app(ReserveTableWithMinimumSpendDepositAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency key wajib diisi (U-4)');

        $action->handle(
            eventId: $this->event->id,
            userId: 407,
            tableNumber: 'CABANA-02',
            minimumSpendIdr: 5000000,
            depositAmountIdr: 2500000,
            idempotencyKey: ''
        );
    }

    public function test_invarian_akuntansi_double_entry_deposit_meja_seimbang_nol(): void
    {
        $booking = EntertainmentTableBooking::create([
            'booking_code' => 'VIP-DAYBED-05-0001',
            'event_id' => $this->event->id,
            'table_number' => 'DAYBED-05',
            'user_id' => 408,
            'minimum_spend_idr' => 4000000,
            'deposit_amount_idr' => 2000000,
            'status' => 'HELD',
        ]);

        $mockGateway = $this->createMock(VenuePaymentGateway::class);
        $mockGateway->method('capture')->willReturn(['success' => true]);
        $this->app->instance(VenuePaymentGateway::class, $mockGateway);

        $action = app(CaptureTableDepositAction::class);
        $action->handle($booking->id, 'ven:tbl:cap:408:balance');

        $totalDebit = LedgerEntry::sum('debit_amount');
        $totalCredit = LedgerEntry::sum('credit_amount');
        $this->assertSame($totalDebit, $totalCredit, 'Total debit dan kredit harus seimbang nol (PR1, U-3)');
    }
}
