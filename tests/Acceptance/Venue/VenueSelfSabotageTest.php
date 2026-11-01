<?php

declare(strict_types=1);

namespace Tests\Acceptance\Venue;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Venue\Application\Actions\HoldGeneralAdmissionQuotaAction;
use Modules\Venue\Application\Actions\PurchaseVenueTicketAction;
use Modules\Venue\Application\Actions\ReserveTableWithMinimumSpendDepositAction;
use Modules\Venue\Application\Actions\ScanTicketAtGateAction;
use Modules\Venue\Application\Services\VenuePaymentGateway;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\InventoryCounter;
use Modules\Venue\Domain\Models\TicketTier;
use Modules\Venue\Domain\Services\VenueQrSigner;
use Modules\Venue\Domain\Services\VenueTicketChainService;
use Tests\TestCase;

class VenueSelfSabotageTest extends TestCase
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
            'venue_code' => 'BC-SABOTAGE-01',
            'name' => 'Sabotage Target Venue',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Badung',
            'max_legal_capacity' => 1000,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::create([
            'zone_code' => 'ZONE-SABOTAGE-01',
            'venue_id' => $this->venue->id,
            'name' => 'Main Sabotage Zone',
            'capacity_limit' => 500,
            'current_occupancy' => 0,
            'crowd_lockdown_active' => false,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-SABOTAGE-01',
            'venue_id' => $this->venue->id,
            'title' => 'Sabotage Resistance Event',
            'doors_open_at' => now()->addDays(2),
            'ticket_floor_price_idr' => 200000,
            'ticket_ceiling_price_idr' => 1000000,
            'current_ticket_price_idr' => 500000,
        ]);

        $this->tier = TicketTier::create([
            'event_id' => $this->event->id,
            'zone_id' => $this->zone->id,
            'name' => 'Sabotage GA Tier',
            'tier_code' => 'GA-SAB',
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
    }

    public function test_sabotase_1_manipulasi_saldo_unearned_tertangkap_audit(): void
    {
        $hold = app(HoldGeneralAdmissionQuotaAction::class)->handle($this->event->id, $this->tier->id, 1, 901, 'ven:sab:hold:1');
        app(PurchaseVenueTicketAction::class)->handle($hold['hold_token'], 901, 'wallet', 'ven:sab:buy:1');

        // Sabotase: sisipkan entri liar (INSERT diizinkan append-only trigger) yang menyebabkan selisih saldo unearned
        $unearnedAcc = DB::table('bank_ledger_accounts')->where('code', 'ven:ticket_unearned:IDR')->first();
        $txId = DB::table('bank_ledger_transactions')->value('id');
        DB::table('bank_ledger_entries')->insert([
            'transaction_id' => $txId,
            'account_id' => $unearnedAcc->id,
            'asset_code' => 'IDR',
            'amount' => '999999999.000000000000000000',
            'balance_after' => '0',
            'created_at' => now(),
        ]);

        $exitCode = Artisan::call('ven:audit');
        $this->assertSame(1, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('discrepancies', $output);
    }

    public function test_sabotase_2_manipulasi_tanda_tangan_hmac_qr_ditolak(): void
    {
        $hold = app(HoldGeneralAdmissionQuotaAction::class)->handle($this->event->id, $this->tier->id, 1, 902, 'ven:sab:hold:2');
        $res = app(PurchaseVenueTicketAction::class)->handle($hold['hold_token'], 902, 'wallet', 'ven:sab:buy:2');
        $ticket = EntertainmentTicket::findOrFail($res['tickets'][0]['id']);

        $realQr = app(VenueQrSigner::class)->generatePayload($ticket);
        $parts = explode('.', $realQr);
        $tamperedQr = $parts[0].'.'.$parts[1].'.'.hash('sha256', 'attacker-fabricated-hmac');

        $scanAction = app(ScanTicketAtGateAction::class);
        $scanRes = $scanAction->handle($tamperedQr, 'GATE-SAB-1', 'IN', 1, 'ven:sab:scan:fake');

        $this->assertSame('DENIED', $scanRes['status']);
        $this->assertStringContainsString('Tanda tangan QR tidak valid', $scanRes['message']);
    }

    public function test_sabotase_3_manipulasi_rantai_hash_chain_tiket_terdeteksi(): void
    {
        $hold = app(HoldGeneralAdmissionQuotaAction::class)->handle($this->event->id, $this->tier->id, 2, 903, 'ven:sab:hold:3');
        app(PurchaseVenueTicketAction::class)->handle($hold['hold_token'], 903, 'wallet', 'ven:sab:buy:3');

        $chainService = app(VenueTicketChainService::class);
        $this->assertTrue($chainService->verifyEventTicketChain($this->event->id));

        // Sabotase: modifikasi prev_hash pada database secara query builder
        EntertainmentTicket::where('event_id', $this->event->id)->orderByDesc('id')->limit(1)->update([
            'prev_hash' => str_repeat('f', 64),
        ]);

        $this->assertFalse($chainService->verifyEventTicketChain($this->event->id));
    }

    public function test_sabotase_4_modifikasi_harga_atau_fakta_tiket_ditolak_model_append_only(): void
    {
        $hold = app(HoldGeneralAdmissionQuotaAction::class)->handle($this->event->id, $this->tier->id, 1, 904, 'ven:sab:hold:4');
        $res = app(PurchaseVenueTicketAction::class)->handle($hold['hold_token'], 904, 'wallet', 'ven:sab:buy:4');
        $ticket = EntertainmentTicket::findOrFail($res['tickets'][0]['id']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Field finansial dan integritas hash tiket tidak boleh diubah (PR5)');
        $ticket->price_paid_idr = 1000;
        $ticket->save();
    }

    public function test_sabotase_5_penghapusan_fisik_tiket_ditolak_model_append_only(): void
    {
        $hold = app(HoldGeneralAdmissionQuotaAction::class)->handle($this->event->id, $this->tier->id, 1, 905, 'ven:sab:hold:5');
        $res = app(PurchaseVenueTicketAction::class)->handle($hold['hold_token'], 905, 'wallet', 'ven:sab:buy:5');
        $ticket = EntertainmentTicket::findOrFail($res['tickets'][0]['id']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fakta tiket bersifat append-only dan tidak boleh dihapus (PR5)');
        $ticket->delete();
    }

    public function test_sabotase_6_double_booking_meja_vip_ditolak(): void
    {
        $mockGateway = $this->createMock(VenuePaymentGateway::class);
        $mockGateway->method('authorize')->willReturn(['success' => true]);
        $this->app->instance(VenuePaymentGateway::class, $mockGateway);

        $action = app(ReserveTableWithMinimumSpendDepositAction::class);
        $action->handle($this->event->id, 906, 'SAB-TBL-1', 5000000, 2000000, 'ven:sab:tbl:1');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Meja SAB-TBL-1 sudah dipesan untuk event ini');
        $action->handle($this->event->id, 907, 'SAB-TBL-1', 5000000, 2000000, 'ven:sab:tbl:2');
    }
}
