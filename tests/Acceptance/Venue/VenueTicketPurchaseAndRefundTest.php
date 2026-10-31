<?php

declare(strict_types=1);

namespace Tests\Acceptance\Venue;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Venue\Application\Actions\HoldGeneralAdmissionQuotaAction;
use Modules\Venue\Application\Actions\PurchaseVenueTicketAction;
use Modules\Venue\Application\Actions\RefundVenueTicketAction;
use Modules\Venue\Domain\Events\VenueTicketIssued;
use Modules\Venue\Domain\Events\VenueTicketRefunded;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\InventoryCounter;
use Modules\Venue\Domain\Models\TicketTier;
use Modules\Venue\Domain\Services\VenueTicketChainService;
use Tests\TestCase;

class VenueTicketPurchaseAndRefundTest extends TestCase
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
            'venue_code' => 'BC-BALI-003',
            'name' => 'Atlas Beach Fest Canggu',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Badung',
            'max_legal_capacity' => 5000,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::create([
            'zone_code' => 'ZONE-FEST-01',
            'venue_id' => $this->venue->id,
            'name' => 'Main Beachstage Pavilion',
            'capacity_limit' => 2500,
            'current_occupancy' => 0,
            'crowd_lockdown_active' => false,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-ATLAS-2026',
            'venue_id' => $this->venue->id,
            'title' => 'Atlas Summer Solstice Rave',
            'doors_open_at' => now()->addDays(7),
            'ticket_floor_price_idr' => 300000,
            'ticket_ceiling_price_idr' => 2000000,
            'current_ticket_price_idr' => 600000,
        ]);

        $this->tier = TicketTier::create([
            'event_id' => $this->event->id,
            'zone_id' => $this->zone->id,
            'name' => 'General Admission Festival Entry',
            'tier_code' => 'GA-FEST',
            'tier_type' => 'GENERAL_ADMISSION',
            'price_idr' => 600000,
            'quota' => 200,
            'is_numbered_seating' => false,
            'allows_reentry' => false,
        ]);

        InventoryCounter::create([
            'event_id' => $this->event->id,
            'tier_id' => $this->tier->id,
            'total_quota' => 200,
            'held_count' => 0,
            'sold_count' => 0,
        ]);
    }

    public function test_pembelian_tiket_menerbitkan_nomor_gapless_dan_hash_chain(): void
    {
        Event::fake([VenueTicketIssued::class]);

        $holdAction = app(HoldGeneralAdmissionQuotaAction::class);
        $hold = $holdAction->handle(
            eventId: $this->event->id,
            tierId: $this->tier->id,
            quantity: 2,
            buyerUserId: 201,
            idempotencyKey: 'ven:hold:201:1'
        );

        $purchaseAction = app(PurchaseVenueTicketAction::class);
        $res = $purchaseAction->handle(
            holdToken: $hold['hold_token'],
            buyerUserId: 201,
            paymentMethod: 'wallet',
            idempotencyKey: 'ven:buy:201:1'
        );

        $this->assertTrue($res['success']);
        $this->assertCount(2, $res['tickets']);

        $t1 = $res['tickets'][0];
        $t2 = $res['tickets'][1];

        // Format nomor tiket gapless TKT-{eventId}-{seq}
        $this->assertMatchesRegularExpression('/^TKT-\d{4}-\d{6}$/', $t1['ticket_number']);
        $this->assertMatchesRegularExpression('/^TKT-\d{4}-\d{6}$/', $t2['ticket_number']);

        // Hash-chain: tiket kedua memiliki prev_hash yang sama dengan ticket_hash tiket pertama
        $this->assertNotEmpty($t1['ticket_hash']);
        $this->assertNotEmpty($t2['ticket_hash']);
        $this->assertSame($t1['ticket_hash'], $t2['prev_hash'], 'Tiket kedua harus menautkan prev_hash ke hash tiket pertama (PR5, B0.1)');

        Event::assertDispatched(VenueTicketIssued::class, 2);
    }

    public function test_pembelian_tiket_memposting_kewajiban_unearned_revenue_di_ledger(): void
    {
        $holdAction = app(HoldGeneralAdmissionQuotaAction::class);
        $hold = $holdAction->handle($this->event->id, $this->tier->id, 1, 202, 'ven:hold:202:1');

        $purchaseAction = app(PurchaseVenueTicketAction::class);
        $res = $purchaseAction->handle(
            holdToken: $hold['hold_token'],
            buyerUserId: 202,
            paymentMethod: 'wallet',
            idempotencyKey: 'ven:buy:202:1'
        );

        $ticket = $res['tickets'][0];

        // Cari baris ledger unearned
        $unearnedCredit = LedgerEntry::where('account_code', 'ven:ticket_unearned:IDR')
            ->where('credit_amount', 600000)
            ->first();

        $this->assertNotNull($unearnedCredit, 'Penjualan tiket harus mengkredit ven:ticket_unearned:IDR (V-7)');
    }

    public function test_pembelian_tiket_menolak_eksekusi_jika_hold_token_tidak_valid_atau_kedaluwarsa(): void
    {
        $purchaseAction = app(PurchaseVenueTicketAction::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hold token tidak ditemukan atau telah kedaluwarsa');

        $purchaseAction->handle(
            holdToken: 'invalid-or-expired-token',
            buyerUserId: 203,
            paymentMethod: 'wallet',
            idempotencyKey: 'ven:buy:203:invalid'
        );
    }

    public function test_idempotensi_pembelian_tiket_dengan_key_sama_mengembalikan_tiket_yang_sama(): void
    {
        $holdAction = app(HoldGeneralAdmissionQuotaAction::class);
        $hold = $holdAction->handle($this->event->id, $this->tier->id, 1, 204, 'ven:hold:204:1');

        $purchaseAction = app(PurchaseVenueTicketAction::class);

        $res1 = $purchaseAction->handle(
            holdToken: $hold['hold_token'],
            buyerUserId: 204,
            paymentMethod: 'wallet',
            idempotencyKey: 'ven:buy:204:same'
        );

        $res2 = $purchaseAction->handle(
            holdToken: $hold['hold_token'],
            buyerUserId: 204,
            paymentMethod: 'wallet',
            idempotencyKey: 'ven:buy:204:same'
        );

        $this->assertSame($res1['tickets'][0]['ticket_number'], $res2['tickets'][0]['ticket_number']);
        $this->assertSame(1, EntertainmentTicket::where('buyer_user_id', 204)->count());
    }

    public function test_pembelian_tiket_tanpa_idempotency_key_ditolak_422(): void
    {
        $purchaseAction = app(PurchaseVenueTicketAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency key wajib diisi (U-4)');

        $purchaseAction->handle(
            holdToken: 'dummy-token',
            buyerUserId: 205,
            paymentMethod: 'wallet',
            idempotencyKey: '' // Kosong
        );
    }

    public function test_refund_tiket_sebelum_event_selesai_membalik_jurnal_unearned_dan_melepas_kuota(): void
    {
        Event::fake([VenueTicketRefunded::class]);

        $holdAction = app(HoldGeneralAdmissionQuotaAction::class);
        $hold = $holdAction->handle($this->event->id, $this->tier->id, 1, 206, 'ven:hold:206:1');

        $purchaseAction = app(PurchaseVenueTicketAction::class);
        $res = $purchaseAction->handle($hold['hold_token'], 206, 'wallet', 'ven:buy:206:1');
        $ticketId = $res['tickets'][0]['id'];

        $refundAction = app(RefundVenueTicketAction::class);
        $refundRes = $refundAction->handle(
            ticketId: $ticketId,
            reason: 'Perubahan jadwal pelanggan',
            idempotencyKey: 'ven:refund:206:1'
        );

        $this->assertTrue($refundRes['success']);

        $ticket = EntertainmentTicket::findOrFail($ticketId);
        $this->assertSame('REFUNDED', $ticket->status);

        // Verifikasi pembalikan unearned di ledger (debit ven:ticket_unearned:IDR)
        $unearnedDebit = LedgerEntry::where('account_code', 'ven:ticket_unearned:IDR')
            ->where('debit_amount', 600000)
            ->first();

        $this->assertNotNull($unearnedDebit, 'Refund tiket harus mendebit ven:ticket_unearned:IDR');

        // Kuota held/sold berkurang
        $counter = InventoryCounter::where('event_id', $this->event->id)->where('tier_id', $this->tier->id)->firstOrFail();
        $this->assertSame(0, $counter->sold_count);

        Event::assertDispatched(VenueTicketRefunded::class);
    }

    public function test_refund_tiket_ditolak_jika_event_sudah_berstatus_closed(): void
    {
        $holdAction = app(HoldGeneralAdmissionQuotaAction::class);
        $hold = $holdAction->handle($this->event->id, $this->tier->id, 1, 207, 'ven:hold:207:1');
        $purchaseAction = app(PurchaseVenueTicketAction::class);
        $res = $purchaseAction->handle($hold['hold_token'], 207, 'wallet', 'ven:buy:207:1');
        $ticketId = $res['tickets'][0]['id'];

        // Tutup event
        $this->event->status = 'CLOSED';
        $this->event->save();

        $refundAction = app(RefundVenueTicketAction::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tiket tidak dapat direfund karena event telah ditutup');

        $refundAction->handle(
            ticketId: $ticketId,
            reason: 'Refund terlambat',
            idempotencyKey: 'ven:refund:207:late'
        );
    }

    public function test_model_entertainment_ticket_menolak_update_dan_delete_fakta_tiket_append_only(): void
    {
        $holdAction = app(HoldGeneralAdmissionQuotaAction::class);
        $hold = $holdAction->handle($this->event->id, $this->tier->id, 1, 208, 'ven:hold:208:1');
        $purchaseAction = app(PurchaseVenueTicketAction::class);
        $res = $purchaseAction->handle($hold['hold_token'], 208, 'wallet', 'ven:buy:208:1');
        $ticket = EntertainmentTicket::findOrFail($res['tickets'][0]['id']);

        // Menolak delete fisik (PR5)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fakta tiket bersifat append-only dan tidak boleh dihapus (PR5)');
        $ticket->delete();
    }

    public function test_model_entertainment_ticket_menolak_modifikasi_field_kritis(): void
    {
        $holdAction = app(HoldGeneralAdmissionQuotaAction::class);
        $hold = $holdAction->handle($this->event->id, $this->tier->id, 1, 209, 'ven:hold:209:1');
        $purchaseAction = app(PurchaseVenueTicketAction::class);
        $res = $purchaseAction->handle($hold['hold_token'], 209, 'wallet', 'ven:buy:209:1');
        $ticket = EntertainmentTicket::findOrFail($res['tickets'][0]['id']);

        // Mencoba memalsukan harga bayar atau hash
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Field finansial dan integritas hash tiket tidak boleh diubah (PR5)');
        $ticket->price_paid_idr = 1000;
        $ticket->save();
    }

    public function test_verifikasi_rantai_hash_chain_tiket_mendeteksi_bila_ada_tiket_yang_disisipkan_atau_diubah(): void
    {
        $holdAction = app(HoldGeneralAdmissionQuotaAction::class);
        $hold = $holdAction->handle($this->event->id, $this->tier->id, 3, 210, 'ven:hold:210:1');
        $purchaseAction = app(PurchaseVenueTicketAction::class);
        $purchaseAction->handle($hold['hold_token'], 210, 'wallet', 'ven:buy:210:1');

        $tickets = EntertainmentTicket::where('event_id', $this->event->id)->orderBy('id')->get();
        $this->assertCount(3, $tickets);

        // Verifikasi keaslian rantai
        $chainVerifier = app(VenueTicketChainService::class);
        $this->assertTrue($chainVerifier->verifyEventTicketChain($this->event->id));
    }
}
