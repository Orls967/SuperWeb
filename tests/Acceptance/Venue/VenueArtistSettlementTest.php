<?php

declare(strict_types=1);

namespace Tests\Acceptance\Venue;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Venue\Application\Actions\ExecuteArtistContractEscrowAndWhtPayoutAction;
use Modules\Venue\Domain\Models\ArtistContract;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Tests\TestCase;

class VenueArtistSettlementTest extends TestCase
{
    use RefreshDatabase;

    private EntertainmentVenue $venue;

    private EntertainmentEvent $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->venue = EntertainmentVenue::create([
            'venue_code' => 'BC-BALI-006',
            'name' => 'Potato Head Beach Club Seminyak',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Seminyak',
            'max_legal_capacity' => 2500,
            'min_age_requirement' => 21,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-POTATO-01',
            'venue_id' => $this->venue->id,
            'title' => 'Echoes of Sunset with Global Artist',
            'doors_open_at' => now()->addDays(5),
            'ticket_floor_price_idr' => 300000,
            'ticket_ceiling_price_idr' => 1500000,
            'current_ticket_price_idr' => 600000,
        ]);
    }

    public function test_settlement_artis_menghitung_gross_share_pajak_pph23_dan_net_payout_secara_integer_eksak(): void
    {
        $contract = ArtistContract::create([
            'id' => (string) Str::uuid(),
            'venue_id' => (string) Str::uuid(),
            'event_id' => (string) $this->event->id,
            'contract_number' => 'ART-PHEAD-001',
            'artist_name' => 'Peggy Gou',
            'advance_amount' => 100000000, // Rp 100 jt advance
            'door_share_percentage' => '25.00', // 25.00% = 2500 bps
            'status' => 'active',
        ]);

        $action = app(ExecuteArtistContractEscrowAndWhtPayoutAction::class);

        // Total penjualan pintu = Rp 1.000.000.000
        // Gross share (25%) = Rp 250.000.000
        // PPh 23 (2.00% = 200 bps) = Rp 5.000.000
        // Sisa setelah pajak = Rp 245.000.000
        // Advance dikurangi = Rp 100.000.000
        // Net Payout = Rp 145.000.000
        $result = $action->handle(
            artistContractId: (string) $contract->id,
            totalDoorSalesIdr: 1000000000,
            whtBps: 200,
            idempotencyKey: 'ven:art:settle:001:1'
        );

        $this->assertSame(250000000, $result['gross_share_idr']);
        $this->assertSame(5000000, $result['wht_tax_idr']);
        $this->assertSame(100000000, $result['advance_deducted_idr']);
        $this->assertSame(145000000, $result['net_payout_idr']);
        $this->assertSame('settled', $result['contract']->status);
    }

    public function test_settlement_artis_memposting_jurnal_beban_pajak_dan_kewajiban_ke_ledger(): void
    {
        $contract = ArtistContract::create([
            'id' => (string) Str::uuid(),
            'venue_id' => (string) Str::uuid(),
            'event_id' => (string) $this->event->id,
            'contract_number' => 'ART-PHEAD-002',
            'artist_name' => 'Boris Brejcha',
            'advance_amount' => 50000000,
            'door_share_percentage' => '20.00',
            'status' => 'active',
        ]);

        $action = app(ExecuteArtistContractEscrowAndWhtPayoutAction::class);

        // Total door sales = Rp 500.000.000
        // Gross share (20%) = Rp 100.000.000
        // PPh 23 (200 bps) = Rp 2.000.000
        // Net payout = Rp 48.000.000
        $action->handle(
            artistContractId: (string) $contract->id,
            totalDoorSalesIdr: 500000000,
            whtBps: 200,
            idempotencyKey: 'ven:art:settle:002:1'
        );

        // Beban biaya artis: debit expense:venue_artist_fee:IDR sebesar Rp 100 jt
        $expenseEntry = LedgerEntry::where('account_code', 'expense:venue_artist_fee:IDR')
            ->where('debit_amount', 100000000)
            ->first();
        $this->assertNotNull($expenseEntry, 'Beban artis harus didebit di expense:venue_artist_fee:IDR');

        // Pajak PPh 23: kredit tax:wht23_payable:IDR sebesar Rp 2 jt
        $taxEntry = LedgerEntry::where('account_code', 'tax:wht23_payable:IDR')
            ->where('credit_amount', 2000000)
            ->first();
        $this->assertNotNull($taxEntry, 'PPh 23 harus dikredit di tax:wht23_payable:IDR');
    }

    public function test_settlement_artis_menolak_kontrak_yang_sudah_berstatus_settled(): void
    {
        $contract = ArtistContract::create([
            'id' => (string) Str::uuid(),
            'venue_id' => (string) Str::uuid(),
            'event_id' => (string) $this->event->id,
            'contract_number' => 'ART-PHEAD-003',
            'artist_name' => 'Charlotte de Witte',
            'advance_amount' => 50000000,
            'door_share_percentage' => '20.00',
            'status' => 'settled',
        ]);

        $action = app(ExecuteArtistContractEscrowAndWhtPayoutAction::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Kontrak artis sudah diselesaikan (settled)');

        $action->handle(
            artistContractId: (string) $contract->id,
            totalDoorSalesIdr: 500000000,
            whtBps: 200,
            idempotencyKey: 'ven:art:settle:003:retry'
        );
    }

    public function test_settlement_idempoten_mengembalikan_hasil_identik_pada_key_sama(): void
    {
        $contract = ArtistContract::create([
            'id' => (string) Str::uuid(),
            'venue_id' => (string) Str::uuid(),
            'event_id' => (string) $this->event->id,
            'contract_number' => 'ART-PHEAD-004',
            'artist_name' => 'Amelie Lens',
            'advance_amount' => 40000000,
            'door_share_percentage' => '20.00',
            'status' => 'active',
        ]);

        $action = app(ExecuteArtistContractEscrowAndWhtPayoutAction::class);

        $res1 = $action->handle((string) $contract->id, 400000000, 200, 'ven:art:settle:004:same');
        $res2 = $action->handle((string) $contract->id, 400000000, 200, 'ven:art:settle:004:same');

        $this->assertSame($res1['net_payout_idr'], $res2['net_payout_idr']);
        $this->assertSame(1, LedgerEntry::where('account_code', 'expense:venue_artist_fee:IDR')->where('debit_amount', 80000000)->count());
    }

    public function test_settlement_menolak_eksekusi_tanpa_idempotency_key(): void
    {
        $contract = ArtistContract::create([
            'id' => (string) Str::uuid(),
            'venue_id' => (string) Str::uuid(),
            'event_id' => (string) $this->event->id,
            'contract_number' => 'ART-PHEAD-005',
            'artist_name' => 'Solomun',
            'advance_amount' => 40000000,
            'door_share_percentage' => '20.00',
            'status' => 'active',
        ]);

        $action = app(ExecuteArtistContractEscrowAndWhtPayoutAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency key wajib diisi (U-4)');

        $action->handle((string) $contract->id, 400000000, 200, '');
    }

    public function test_net_payout_bernilai_nol_jika_advance_melebihi_gross_share_setelah_pajak(): void
    {
        $contract = ArtistContract::create([
            'id' => (string) Str::uuid(),
            'venue_id' => (string) Str::uuid(),
            'event_id' => (string) $this->event->id,
            'contract_number' => 'ART-PHEAD-006',
            'artist_name' => 'Tale of Us',
            'advance_amount' => 200000000, // Advance Rp 200 jt sangat besar
            'door_share_percentage' => '10.00', // 10% dari Rp 500 jt = Rp 50 jt
            'status' => 'active',
        ]);

        $action = app(ExecuteArtistContractEscrowAndWhtPayoutAction::class);

        $res = $action->handle((string) $contract->id, 500000000, 200, 'ven:art:settle:006:floor');
        $this->assertSame(0, $res['net_payout_idr'], 'Net payout tidak boleh negatif (terpotong advance penuh)');
    }

    public function test_invarian_ledger_settlement_artis_seimbang_nol(): void
    {
        $contract = ArtistContract::create([
            'id' => (string) Str::uuid(),
            'venue_id' => (string) Str::uuid(),
            'event_id' => (string) $this->event->id,
            'contract_number' => 'ART-PHEAD-007',
            'artist_name' => 'Carl Cox',
            'advance_amount' => 50000000,
            'door_share_percentage' => '25.00',
            'status' => 'active',
        ]);

        $action = app(ExecuteArtistContractEscrowAndWhtPayoutAction::class);
        $action->handle((string) $contract->id, 600000000, 200, 'ven:art:settle:007:bal');

        $totalDebit = LedgerEntry::sum('debit_amount');
        $totalCredit = LedgerEntry::sum('credit_amount');
        $this->assertSame($totalDebit, $totalCredit, 'Double-entry settlement artis harus seimbang nol');
    }
}
