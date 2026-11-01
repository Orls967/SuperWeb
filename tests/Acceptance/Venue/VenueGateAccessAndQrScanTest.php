<?php

declare(strict_types=1);

namespace Tests\Acceptance\Venue;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Party\Contracts\PartyAgeVerificationInterface;
use Modules\Venue\Application\Actions\HoldGeneralAdmissionQuotaAction;
use Modules\Venue\Application\Actions\PurchaseVenueTicketAction;
use Modules\Venue\Application\Actions\ScanTicketAtGateAction;
use Modules\Venue\database\seeders\VenueRbacSeeder;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\GateScanLog;
use Modules\Venue\Domain\Models\InventoryCounter;
use Modules\Venue\Domain\Models\TicketTier;
use Modules\Venue\Domain\Services\VenueQrSigner;
use Tests\TestCase;

class VenueGateAccessAndQrScanTest extends TestCase
{
    use RefreshDatabase;

    private EntertainmentVenue $venue;

    private EntertainmentEvent $event;

    private EntertainmentZone $zone;

    private TicketTier $tier;

    private string $validQrString;

    private EntertainmentTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->venue = EntertainmentVenue::create([
            'venue_code' => 'BC-BALI-004',
            'name' => 'Uluwatu Sunset Amphitheatre',
            'venue_type' => 'AMPHITHEATRE',
            'city' => 'Badung',
            'max_legal_capacity' => 1500,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::create([
            'zone_code' => 'ZONE-CLIFF-01',
            'venue_id' => $this->venue->id,
            'name' => 'Cliffside Main Arena',
            'capacity_limit' => 800,
            'current_occupancy' => 0,
            'crowd_lockdown_active' => false,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-CLIFF-01',
            'venue_id' => $this->venue->id,
            'title' => 'Cliffside Sunset Rave 2026',
            'doors_open_at' => now()->addDays(2),
            'ticket_floor_price_idr' => 250000,
            'ticket_ceiling_price_idr' => 1000000,
            'current_ticket_price_idr' => 500000,
        ]);

        $this->tier = TicketTier::create([
            'event_id' => $this->event->id,
            'zone_id' => $this->zone->id,
            'name' => 'Cliff GA Regular',
            'tier_code' => 'GA-CLIFF',
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

        // Buat tiket uji
        $hold = app(HoldGeneralAdmissionQuotaAction::class)->handle($this->event->id, $this->tier->id, 1, 301, 'ven:hold:301:1');
        $res = app(PurchaseVenueTicketAction::class)->handle($hold['hold_token'], 301, 'wallet', 'ven:buy:301:1');
        $this->ticket = EntertainmentTicket::findOrFail($res['tickets'][0]['id']);

        $signer = app(VenueQrSigner::class);
        $this->validQrString = $signer->generatePayload($this->ticket);
    }

    public function test_format_qr_tiket_sesuai_spesifikasi_uuid_keyid_hmac(): void
    {
        // V-4: {ticket_uuid}.{key_id}.{hmac}
        $parts = explode('.', $this->validQrString);
        $this->assertCount(3, $parts, 'Payload QR harus terdiri dari 3 segmen terpisah titik');

        $this->assertSame($this->ticket->ticket_uuid, $parts[0]);
        $this->assertSame('v1', $parts[1]); // key_id default
        $this->assertSame(64, strlen($parts[2]), 'HMAC-SHA256 hex harus memiliki panjang 64 karakter');
    }

    public function test_pemindaian_pertama_berhasil_dan_mengubah_status_tiket_menjadi_used(): void
    {
        // Mock KYC Party: pembeli berusia 25 tahun (di atas min_age 21)
        $mockParty = $this->createMock(PartyAgeVerificationInterface::class);
        $mockParty->method('getVerifiedAge')->willReturn(25);
        $this->app->instance(PartyAgeVerificationInterface::class, $mockParty);

        $action = app(ScanTicketAtGateAction::class);

        $res = $action->handle(
            qrPayload: $this->validQrString,
            gateCode: 'GATE-CLIFF-A',
            direction: 'IN',
            scannedByUserId: 99,
            idempotencyKey: 'ven:scan:301:in'
        );

        $this->assertSame('ALLOWED', $res['status']);
        $this->assertSame('Akses diizinkan', $res['message']);

        $ticket = EntertainmentTicket::findOrFail($this->ticket->id);
        $this->assertSame('USED', $ticket->status);

        $zone = EntertainmentZone::findOrFail($this->zone->id);
        $this->assertSame(1, $zone->current_occupancy);
    }

    public function test_anti_passback_pemindaian_kedua_tiket_yang_sama_langsung_ditolak(): void
    {
        $mockParty = $this->createMock(PartyAgeVerificationInterface::class);
        $mockParty->method('getVerifiedAge')->willReturn(25);
        $this->app->instance(PartyAgeVerificationInterface::class, $mockParty);

        $action = app(ScanTicketAtGateAction::class);

        // Scan pertama: sukses
        $action->handle(
            qrPayload: $this->validQrString,
            gateCode: 'GATE-CLIFF-A',
            direction: 'IN',
            scannedByUserId: 99,
            idempotencyKey: 'ven:scan:302:in1'
        );

        // Scan kedua (tiket yang sama): ditolak anti-passback
        $res2 = $action->handle(
            qrPayload: $this->validQrString,
            gateCode: 'GATE-CLIFF-A',
            direction: 'IN',
            scannedByUserId: 99,
            idempotencyKey: 'ven:scan:302:in2'
        );

        $this->assertSame('DENIED', $res2['status']);
        $this->assertStringContainsString('Anti-passback: tiket sudah digunakan', $res2['message']);
    }

    public function test_pemindaian_qr_dengan_hmac_palsu_ditolak(): void
    {
        $parts = explode('.', $this->validQrString);
        $tamperedQr = $parts[0].'.'.$parts[1].'.'.str_repeat('0', 64); // HMAC palsu

        $action = app(ScanTicketAtGateAction::class);

        $res = $action->handle(
            qrPayload: $tamperedQr,
            gateCode: 'GATE-CLIFF-A',
            direction: 'IN',
            scannedByUserId: 99,
            idempotencyKey: 'ven:scan:fake:1'
        );

        $this->assertSame('DENIED', $res['status']);
        $this->assertStringContainsString('Tanda tangan QR tidak valid', $res['message']);
    }

    public function test_usia_di_bawah_batas_minimum_ditolak_berdasarkan_kyc_party(): void
    {
        // Mock KYC Party: pembeli berusia 19 tahun (min_age venue adalah 21)
        $mockParty = $this->createMock(PartyAgeVerificationInterface::class);
        $mockParty->method('getVerifiedAge')->willReturn(19);
        $this->app->instance(PartyAgeVerificationInterface::class, $mockParty);

        $action = app(ScanTicketAtGateAction::class);

        $res = $action->handle(
            qrPayload: $this->validQrString,
            gateCode: 'GATE-CLIFF-A',
            direction: 'IN',
            scannedByUserId: 99,
            idempotencyKey: 'ven:scan:underage:1'
        );

        $this->assertSame('DENIED', $res['status']);
        $this->assertStringContainsString('Usia belum memenuhi syarat minimum 21 tahun', $res['message']);
    }

    public function test_tanpa_data_kyc_terverifikasi_di_party_ditolak_bukan_dianggap_dewasa(): void
    {
        // Mock KYC Party: null (tidak ada data usia terverifikasi, V-6, Z9)
        $mockParty = $this->createMock(PartyAgeVerificationInterface::class);
        $mockParty->method('getVerifiedAge')->willReturn(null);
        $this->app->instance(PartyAgeVerificationInterface::class, $mockParty);

        $action = app(ScanTicketAtGateAction::class);

        $res = $action->handle(
            qrPayload: $this->validQrString,
            gateCode: 'GATE-CLIFF-A',
            direction: 'IN',
            scannedByUserId: 99,
            idempotencyKey: 'ven:scan:unverified:1'
        );

        $this->assertSame('DENIED', $res['status']);
        $this->assertStringContainsString('Data usia belum terverifikasi di KYC Party', $res['message']);
    }

    public function test_default_stub_party_age_verification_mengembalikan_null_dan_menolak_aksi_berbatas_usia(): void
    {
        $stub = app(PartyAgeVerificationInterface::class);
        $this->assertNull($stub->getVerifiedAge(99));

        $action = app(ScanTicketAtGateAction::class);

        $res = $action->handle(
            qrPayload: $this->validQrString,
            gateCode: 'GATE-CLIFF-A',
            direction: 'IN',
            scannedByUserId: 99,
            idempotencyKey: 'ven:scan:stub_null:1'
        );

        $this->assertSame('DENIED', $res['status']);
        $this->assertStringContainsString('Data usia belum terverifikasi di KYC Party', $res['message']);
    }

    public function test_re_entry_diizinkan_hanya_jika_tier_mengizinkan_dan_posisi_tiket_keluar(): void
    {
        // Ubah tier agar mengizinkan re-entry
        $this->tier->allows_reentry = true;
        $this->tier->save();

        $mockParty = $this->createMock(PartyAgeVerificationInterface::class);
        $mockParty->method('getVerifiedAge')->willReturn(25);
        $this->app->instance(PartyAgeVerificationInterface::class, $mockParty);

        $action = app(ScanTicketAtGateAction::class);

        // 1. Scan Masuk
        $resIn1 = $action->handle($this->validQrString, 'GATE-CLIFF-A', 'IN', 99, 'ven:scan:re:in1');
        $this->assertSame('ALLOWED', $resIn1['status']);

        // 2. Scan Keluar
        $resOut = $action->handle($this->validQrString, 'GATE-CLIFF-A', 'OUT', 99, 'ven:scan:re:out');
        $this->assertSame('ALLOWED', $resOut['status']);

        $ticket = EntertainmentTicket::findOrFail($this->ticket->id);
        $this->assertSame('EXITED', $ticket->status);

        // 3. Scan Masuk Kembali (Re-entry)
        $resIn2 = $action->handle($this->validQrString, 'GATE-CLIFF-A', 'IN', 99, 'ven:scan:re:in2');
        $this->assertSame('ALLOWED', $resIn2['status']);
        $this->assertSame('USED', $ticket->fresh()->status);
    }

    public function test_log_pemindaian_gerbang_dicatat_secara_append_only(): void
    {
        $mockParty = $this->createMock(PartyAgeVerificationInterface::class);
        $mockParty->method('getVerifiedAge')->willReturn(25);
        $this->app->instance(PartyAgeVerificationInterface::class, $mockParty);

        $action = app(ScanTicketAtGateAction::class);
        $action->handle($this->validQrString, 'GATE-CLIFF-A', 'IN', 99, 'ven:scan:log:1');

        $this->assertSame(1, GateScanLog::where('ticket_id', $this->ticket->id)->count());

        $log = GateScanLog::where('ticket_id', $this->ticket->id)->firstOrFail();
        $this->assertSame('ALLOWED', $log->scan_result);
        $this->assertSame('GATE-CLIFF-A', $log->gate_code);

        // Model GateScanLog menolak delete fisik (PR5)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Log pemindaian gerbang bersifat append-only dan tidak boleh dihapus (PR5)');
        $log->delete();
    }

    public function test_rute_gate_scanner_hanya_dapat_diakses_oleh_role_venue_staff(): void
    {
        $userNonStaff = User::factory()->create();
        $userStaff = User::factory()->create();

        $this->seed(VenueRbacSeeder::class);
        $userStaff->assignRole('venue_staff');

        // Akses tanpa role venue_staff -> 403 Forbidden
        $response403 = $this->actingAs($userNonStaff)->get('/venue/gate');
        $response403->assertStatus(403);

        // Akses dengan role venue_staff -> 200 OK
        $response200 = $this->actingAs($userStaff)->get('/venue/gate');
        $response200->assertStatus(200);
    }

    public function test_halaman_gate_scanner_memiliki_layout_ramah_mobile_375px(): void
    {
        $this->seed(VenueRbacSeeder::class);
        $userStaff = User::factory()->create();
        $userStaff->assignRole('venue_staff');

        $response = $this->actingAs($userStaff)->get('/venue/gate');
        $response->assertStatus(200);

        // Memastikan view mengandung elemen input scanner kamera dan tombol aksi turnstile
        $response->assertSee('viewport', false);
        $response->assertSee('Turnstile Scanner');
        $response->assertSee('qr_payload');
    }
}
