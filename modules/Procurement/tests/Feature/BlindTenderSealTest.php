<?php

declare(strict_types=1);

namespace Modules\Procurement\tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Domain\Models\Tender;
use Modules\Supplier\Application\Services\SupplierService;
use Modules\Supplier\Domain\Models\Supplier;
use RuntimeException;
use Tests\TestCase;

class BlindTenderSealTest extends TestCase
{
    use RefreshDatabase;

    private ProcurementService $service;

    private User $admin;

    private Supplier $supplierA;

    private Supplier $supplierB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->service = app(ProcurementService::class);
        $this->admin = User::where('role', 'admin')->first()
            ?? User::factory()->create(['role' => 'admin']);

        $supplierService = app(SupplierService::class);
        $this->supplierA = Supplier::first()
            ?? $supplierService->register(['name' => 'PT Pemasok Uji Pertama', 'kind' => 'supplier'], $this->admin);
        $this->supplierB = Supplier::skip(1)->first()
            ?? $supplierService->register(['name' => 'PT Pemasok Uji Kedua', 'kind' => 'supplier'], $this->admin);
    }

    private function createClosedTender(): Tender
    {
        return $this->service->createTender([
            'title' => 'Tender Pengadaan Logistik Blind',
            'type' => 'closed',
            'bids_close_at' => now()->addDays(2),
        ], $this->admin);
    }

    public function test_isi_penawaran_tidak_terbaca_offer_null_dan_terenkripsi_sebelum_tender_dibuka(): void
    {
        $tender = $this->createClosedTender();
        $offerData = [
            'total_price_idr' => 25_000_000,
            'lead_time_days' => 5,
            'spec_compliance_pct' => 98,
        ];

        $bid = $this->service->sealBid($tender, $this->supplierA, $offerData);

        // Langsung cek dari database / fresh model
        $freshBid = $bid->fresh();
        $rawDbRow = DB::table('prc_tender_bids')->where('id', $bid->id)->first();

        // Plaintext offer WAJIB null sebelum dibuka
        $this->assertNull($freshBid->offer, 'Offer harus null sebelum tender dibuka.');
        $this->assertNull($rawDbRow->offer, 'Kolom DB offer harus null sebelum tender dibuka.');
        $this->assertNull($freshBid->opened_at, 'opened_at harus null sebelum tender dibuka.');

        // offer_ciphertext terenkripsi dan bukan plaintext JSON
        $this->assertNotNull($freshBid->offer_ciphertext);
        $this->assertStringNotContainsString('25000000', $rawDbRow->offer_ciphertext);

        // Dekripsi menghasilkan data asli
        $decryptedJson = Crypt::decryptString($freshBid->offer_ciphertext);
        $this->assertJson($decryptedJson);
        $this->assertEquals(25_000_000, json_decode($decryptedJson, true)['total_price_idr']);
    }

    public function test_is_seal_intact_mengembalikan_true_untuk_penawaran_sah_dan_false_bila_dimanipulasi(): void
    {
        $tender = $this->createClosedTender();
        $offerData = [
            'total_price_idr' => 15_000_000,
            'lead_time_days' => 7,
        ];

        $bid = $this->service->sealBid($tender, $this->supplierA, $offerData);

        // 1. Penawaran sah tanpa parameter kandidat
        $this->assertTrue($bid->isSealIntact());

        // 2. Penawaran sah dengan array yang sama (urutan key beda tetap kanonik)
        $permutedOffer = [
            'lead_time_days' => 7,
            'total_price_idr' => 15_000_000,
        ];
        $this->assertTrue($bid->isSealIntact($permutedOffer));

        // 3. Penawaran dimanipulasi dengan kandidat berbeda
        $tamperedCandidate = [
            'total_price_idr' => 12_000_000,
            'lead_time_days' => 7,
        ];
        $this->assertFalse($bid->isSealIntact($tamperedCandidate));

        // 4. Manipulasi hash segel di database
        $bid->seal_hash = hash('sha256', 'palsu');
        $this->assertFalse($bid->isSealIntact());
    }

    public function test_open_bids_membuka_seluruh_penawaran_tersegel_dan_memulihkan_offer_setelah_tenggat(): void
    {
        $tender = $this->createClosedTender();

        $offerA = ['total_price_idr' => 18_000_000, 'lead_time_days' => 4];
        $offerB = ['total_price_idr' => 16_500_000, 'lead_time_days' => 6];

        $bidA = $this->service->sealBid($tender, $this->supplierA, $offerA);
        $bidB = $this->service->sealBid($tender, $this->supplierB, $offerB);

        // Sebelum tenggat: openBids ditolak
        try {
            $this->service->openBids($tender);
            $this->fail('Membuka segel sebelum tenggat harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('belum tiba', $e->getMessage());
        }

        // Majukan tenggat ke masa lampau
        $tender->update(['bids_close_at' => now()->subMinute()]);

        // Buka seluruh penawaran
        $openedCount = $this->service->openBids($tender);
        $this->assertEquals(2, $openedCount);

        // Periksa model dipulihkan
        $freshBidA = $bidA->fresh();
        $freshBidB = $bidB->fresh();

        $this->assertNotNull($freshBidA->opened_at);
        $this->assertNotNull($freshBidB->opened_at);

        $this->assertEquals(18_000_000, $freshBidA->offer['total_price_idr']);
        $this->assertEquals(16_500_000, $freshBidB->offer['total_price_idr']);
        $this->assertEquals('opened', $tender->fresh()->status);
    }

    public function test_manipulasi_isi_penawaran_atau_seal_hash_setelah_segel_ditolak_saat_open_bids(): void
    {
        $tender = $this->createClosedTender();
        $offerData = ['total_price_idr' => 30_000_000, 'lead_time_days' => 10];

        $bid = $this->service->sealBid($tender, $this->supplierA, $offerData);

        // Skenario A: Penyerang memanipulasi offer_ciphertext dengan harga lebih rendah
        $tamperedOffer = ['total_price_idr' => 10_000_000, 'lead_time_days' => 10];
        $tamperedCiphertext = Crypt::encryptString(json_encode($tamperedOffer));

        DB::table('prc_tender_bids')->where('id', $bid->id)->update([
            'offer_ciphertext' => $tamperedCiphertext,
        ]);

        $tender->update(['bids_close_at' => now()->subMinute()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Manipulasi terdeteksi');

        $this->service->openBids($tender);
    }
}
