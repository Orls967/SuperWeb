<?php

declare(strict_types=1);

namespace Tests\Perilaku\R2;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Domain\Models\Tender;
use Modules\Procurement\Domain\Models\TenderBid;
use Modules\Supplier\Application\Services\SupplierService;
use Modules\Supplier\Domain\Models\Supplier;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('perilaku')]
class R2_2Test extends TestCase
{
    use RefreshDatabase;

    private ProcurementService $service;

    private User $admin;

    private Supplier $supplierA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->service = app(ProcurementService::class);
        $this->admin = User::where('role', 'admin')->first()
            ?? User::factory()->create(['role' => 'admin']);

        $supplierService = app(SupplierService::class);
        $this->supplierA = Supplier::first()
            ?? $supplierService->register(['name' => 'PT Pemasok Blind Test', 'kind' => 'supplier'], $this->admin);
    }

    private function createClosedTender(): Tender
    {
        return $this->service->createTender([
            'title' => 'Tender Blind Test Pengadaan',
            'type' => 'closed',
            'bids_close_at' => now()->addDays(2),
        ], $this->admin);
    }

    public function test_klausul_1_sebelum_open_bids_nilai_penawaran_tidak_terbaca_mentah_di_db(): void
    {
        $tender = $this->createClosedTender();
        $offerData = [
            'total_price_idr' => 25_000_000,
            'lead_time_days' => 5,
            'spec_compliance_pct' => 98,
        ];

        $bid = $this->service->sealBid($tender, $this->supplierA, $offerData);

        $freshBid = $bid->fresh();
        $rawDbRow = DB::table('prc_tender_bids')->where('id', $bid->id)->first();

        // Klausul 1 Terbukti: offer null, ciphertext terenkripsi, angka mentah tidak ada di plaintext
        $this->assertNull($freshBid->offer, 'Klausul 1: Plaintext offer harus null sebelum tender dibuka.');
        $this->assertNull($rawDbRow->offer, 'Klausul 1: Kolom DB offer harus null.');
        $this->assertNull($freshBid->opened_at, 'Klausul 1: opened_at harus null.');
        $this->assertNotNull($freshBid->offer_ciphertext);
        $this->assertStringNotContainsString('25000000', $rawDbRow->offer_ciphertext);
    }

    public function test_klausul_2_dan_3_formula_is_seal_intact_sama_dan_perubahan_field_ditolak_saat_pembukaan(): void
    {
        $tender = $this->createClosedTender();
        $offerData = [
            'total_price_idr' => 30_000_000,
            'lead_time_days' => 7,
            'spec_compliance_pct' => 95,
        ];

        $bid = $this->service->sealBid($tender, $this->supplierA, $offerData);

        // Klausul 2 Terbukti: formula segel utuh mengembalikan true sebelum dimanipulasi
        $this->assertTrue($bid->isSealIntact(), 'Klausul 2: isSealIntact() harus true untuk penawaran utuh');

        // Manipulasi isi penawaran (ubah ciphertext)
        DB::table('prc_tender_bids')->where('id', $bid->id)->update([
            'offer_ciphertext' => 'tampered_ciphertext_'.base64_encode('hacked'),
        ]);

        $tamperedBid = TenderBid::findOrFail($bid->id);
        $this->assertFalse($tamperedBid->isSealIntact(), 'Klausul 2: isSealIntact() harus false setelah dimanipulasi');

        // Majukan waktu melewati tenggat
        $tender->update(['bids_close_at' => now()->subMinute()]);

        // Klausul 3 Terbukti: openBids() wajib menolak bid yang segelnya rusak
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dimanipulasi');

        $this->service->openBids($tender, $this->admin);
    }
}
