<?php

declare(strict_types=1);

namespace Tests\Acceptance\Venue;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Party\Contracts\PartyAgeVerificationInterface;
use Modules\Venue\Application\Actions\CloseEntertainmentEventAction;
use Modules\Venue\Application\Actions\HoldGeneralAdmissionQuotaAction;
use Modules\Venue\Application\Actions\PurchaseVenueTicketAction;
use Modules\Venue\Application\Actions\ScanTicketAtGateAction;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\InventoryCounter;
use Modules\Venue\Domain\Models\TicketTier;
use Modules\Venue\Domain\Services\VenueQrSigner;
use Tests\TestCase;

class VenueEventClosingAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private EntertainmentVenue $venue;

    private EntertainmentEvent $event;

    private EntertainmentZone $zone;

    private TicketTier $tier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->venue = EntertainmentVenue::create([
            'venue_code' => 'BC-BALI-007',
            'name' => 'Café del Mar Bali',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Canggu',
            'max_legal_capacity' => 3000,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::create([
            'zone_code' => 'ZONE-POOL-07',
            'venue_id' => $this->venue->id,
            'name' => 'Sunset Lagoon Deck',
            'capacity_limit' => 1500,
            'current_occupancy' => 0,
            'crowd_lockdown_active' => false,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-DELMAR-01',
            'venue_id' => $this->venue->id,
            'title' => 'Del Mar Closing Fiesta',
            'doors_open_at' => now()->addDays(1),
            'ticket_floor_price_idr' => 250000,
            'ticket_ceiling_price_idr' => 1000000,
            'current_ticket_price_idr' => 500000,
        ]);

        $this->tier = TicketTier::create([
            'event_id' => $this->event->id,
            'zone_id' => $this->zone->id,
            'name' => 'Fiesta Sunset GA',
            'tier_code' => 'GA-FIESTA',
            'tier_type' => 'GENERAL_ADMISSION',
            'price_idr' => 500000,
            'quota' => 50,
            'is_numbered_seating' => false,
            'allows_reentry' => false,
        ]);

        InventoryCounter::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->tier->id,
            'total_quota' => 50,
            'held_count' => 0,
            'sold_count' => 0,
        ]);
    }

    public function test_penutupan_event_mengakui_seluruh_pendapatan_dari_unearned_ke_revenue(): void
    {
        // Jual 4 tiket @ Rp 500.000 = Rp 2.000.000 unearned
        $hold = app(HoldGeneralAdmissionQuotaAction::class)->handle($this->event->id, $this->tier->id, 4, 501, 'ven:hold:501:1');
        app(PurchaseVenueTicketAction::class)->handle($hold['hold_token'], 501, 'wallet', 'ven:buy:501:1');

        $closeAction = app(CloseEntertainmentEventAction::class);
        $result = $closeAction->handle(
            eventId: $this->event->id,
            platformFeeBps: 500, // 5.00% fee platform = Rp 100.000
            idempotencyKey: 'ven:close:delmar:1'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('CLOSED', $this->event->fresh()->status);
        $this->assertSame(2000000, $result['total_unearned_recognized_idr']);
        $this->assertSame(100000, $result['platform_fee_idr']);
        $this->assertSame(1900000, $result['organizer_net_share_idr']);

        // Akun unearned harus didebit kembali sebesar Rp 2.000.000
        $debitUnearned = LedgerEntry::where('account_code', 'ven:ticket_unearned:IDR')
            ->where('debit_amount', 2000000)
            ->first();
        $this->assertNotNull($debitUnearned);

        // Akun pendapatan tiket dan fee dikredit
        $creditRev = LedgerEntry::where('account_code', 'ven:ticket_revenue:IDR')
            ->where('credit_amount', 1900000)
            ->first();
        $this->assertNotNull($creditRev);

        $creditFee = LedgerEntry::where('account_code', 'ven:platform_fee_revenue:IDR')
            ->where('credit_amount', 100000)
            ->first();
        $this->assertNotNull($creditFee);
    }

    public function test_penutupan_event_dua_kali_dengan_key_sama_bersifat_idempoten(): void
    {
        $hold = app(HoldGeneralAdmissionQuotaAction::class)->handle($this->event->id, $this->tier->id, 2, 502, 'ven:hold:502:1');
        app(PurchaseVenueTicketAction::class)->handle($hold['hold_token'], 502, 'wallet', 'ven:buy:502:1');

        $closeAction = app(CloseEntertainmentEventAction::class);
        $res1 = $closeAction->handle($this->event->id, 500, 'ven:close:delmar:same');
        $res2 = $closeAction->handle($this->event->id, 500, 'ven:close:delmar:same');

        $this->assertSame($res1['total_unearned_recognized_idr'], $res2['total_unearned_recognized_idr']);
        $this->assertSame(1, LedgerEntry::where('account_code', 'ven:ticket_unearned:IDR')->where('debit_amount', 1000000)->count());
    }

    public function test_tiket_tidak_dapat_dibeli_setelah_event_ditutup(): void
    {
        $closeAction = app(CloseEntertainmentEventAction::class);
        $closeAction->handle($this->event->id, 500, 'ven:close:delmar:now');

        $holdAction = app(HoldGeneralAdmissionQuotaAction::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Event telah ditutup dan tidak lagi menerima pembelian tiket');

        $holdAction->handle($this->event->id, $this->tier->id, 1, 503, 'ven:hold:closed');
    }

    public function test_audit_dua_sumber_ven_audit_berhasil_exit_0_bila_kondisi_konsisten(): void
    {
        // 2 tiket dibeli, 1 dipindai, 1 belum dipindai
        $hold = app(HoldGeneralAdmissionQuotaAction::class)->handle($this->event->id, $this->tier->id, 2, 504, 'ven:hold:504:1');
        $buyRes = app(PurchaseVenueTicketAction::class)->handle($hold['hold_token'], 504, 'wallet', 'ven:buy:504:1');

        $signer = app(VenueQrSigner::class);
        $t1 = EntertainmentTicket::findOrFail($buyRes['tickets'][0]['id']);
        $qr1 = $signer->generatePayload($t1);

        $mockParty = $this->createMock(PartyAgeVerificationInterface::class);
        $mockParty->method('getVerifiedAge')->willReturn(25);
        $this->app->instance(PartyAgeVerificationInterface::class, $mockParty);

        app(ScanTicketAtGateAction::class)->handle($qr1, 'GATE-DELMAR-1', 'IN', 99, 'ven:scan:504:1');

        // Jalankan ven:audit
        $exitCode = Artisan::call('ven:audit');
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Venue audit PASSED with 0 discrepancy', $output);
    }

    public function test_ven_audit_gagal_exit_1_bila_terdeteksi_anomali_rekayasa_tiket_dan_ledger(): void
    {
        // Beli 1 tiket
        $hold = app(HoldGeneralAdmissionQuotaAction::class)->handle($this->event->id, $this->tier->id, 1, 505, 'ven:hold:505:1');
        app(PurchaseVenueTicketAction::class)->handle($hold['hold_token'], 505, 'wallet', 'ven:buy:505:1');

        // Sabotase / korupsi: ubah total saldo unearned di ledger secara ilegal
        LedgerEntry::where('account_code', 'ven:ticket_unearned:IDR')->update(['credit_amount' => 99999999]);

        $exitCode = Artisan::call('ven:audit');
        $this->assertSame(1, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('FAILED', $output);
        $this->assertStringContainsString('discrepancies', $output);
    }

    public function test_ven_audit_gagal_exit_1_bila_okupansi_zona_melebihi_kapasitas(): void
    {
        // Sabotase: set okupansi melebihi batas legal
        $this->zone->current_occupancy = 1600; // Kapasitas 1500
        $this->zone->save();

        $exitCode = Artisan::call('ven:audit');
        $this->assertSame(1, $exitCode);
    }

    public function test_command_ven_close_events_menutup_semua_event_yang_sudah_selesai_secara_otomatis(): void
    {
        // Buat event yang waktu mulainya kemarin (sudah lewat)
        $pastEvent = EntertainmentEvent::create([
            'event_code' => 'EVT-PAST-01',
            'venue_id' => $this->venue->id,
            'title' => 'Yesterday Festival',
            'doors_open_at' => now()->subDays(1),
            'ticket_floor_price_idr' => 200000,
            'ticket_ceiling_price_idr' => 1000000,
            'current_ticket_price_idr' => 400000,
        ]);

        $exitCode = Artisan::call('ven:close-events');
        $this->assertSame(0, $exitCode);
        $this->assertSame('CLOSED', $pastEvent->fresh()->status);
    }

    public function test_invarian_ledger_seluruh_pilar_venue_seimbang_nol(): void
    {
        $totalDebit = LedgerEntry::sum('debit_amount');
        $totalCredit = LedgerEntry::sum('credit_amount');
        $this->assertSame($totalDebit, $totalCredit, 'Double-entry ledger seluruh transaksi venue harus balance nol');
    }
}
