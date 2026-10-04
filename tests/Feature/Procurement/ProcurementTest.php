<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Procurement\Application\Services\InboundShipmentService;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Domain\Models\BudgetCenter;
use Modules\Procurement\Domain\Models\BudgetEncumbrance;
use Modules\Procurement\Domain\Models\Quote;
use Modules\Procurement\Domain\Models\Tender;
use Modules\Procurement\Domain\Models\TenderBid;
use Modules\Supplier\Application\Services\SupplierService;
use Modules\Supplier\Domain\Models\Supplier;
use Modules\Supplier\Domain\Models\SupplierScorecard;
use Tests\TestCase;

class ProcurementTest extends TestCase
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
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->supplierA = Supplier::where('is_active', true)->firstOrFail();
        $this->supplierB = Supplier::where('is_active', true)->skip(1)->first()
            ?? app(SupplierService::class)->register([
                'name' => 'PT Pemasok Uji Kedua',
                'kind' => 'supplier',
            ], $this->admin);
    }

    private function makeBudgetCenter(int $budget = 100_000_000): BudgetCenter
    {
        return $this->service->createBudgetCenter('TEST-'.uniqid(), 'Pusat Uji', $budget);
    }

    public function test_requisition_encumbers_budget_on_approval(): void
    {
        $center = $this->makeBudgetCenter(50_000_000);

        $pr = $this->service->createRequisition([
            'title' => 'Pengadaan tester',
            'budget_center_id' => $center->id,
            'lines' => [
                ['description' => 'Item A', 'qty' => 10, 'estimated_unit_price_idr' => 1_000_000],
                ['description' => 'Item B', 'qty' => 5, 'estimated_unit_price_idr' => 2_000_000],
            ],
        ], $this->admin);

        $this->assertStringStartsWith('PR/', $pr->number);
        $this->assertSame(20_000_000, (int) $pr->total_estimated_idr);
        $this->assertSame('pending_approval', $pr->status);

        $this->service->approveRequisition($pr->fresh());

        $this->assertSame('approved', $pr->fresh()->status);
        $this->assertSame(
            20_000_000,
            (int) BudgetEncumbrance::where('source_type', 'pr')->where('source_id', $pr->id)->value('amount_idr')
        );
        $this->assertSame(30_000_000, $center->fresh()->remainingBudget());
    }

    public function test_encumbrance_is_idempotent_per_source_and_flags_overrun(): void
    {
        $center = $this->makeBudgetCenter(1_000_000);

        $first = $this->service->encumber($center, 'po', 999, 2_000_000);
        $second = $this->service->encumber($center, 'po', 999, 2_000_000);

        $this->assertSame(1, BudgetEncumbrance::where('source_type', 'po')->where('source_id', 999)->count());
        $this->assertTrue($first['warning']);
        $this->assertSame($first['remaining_idr'], $second['remaining_idr']);

        $this->assertTrue($this->service->releaseEncumbrance('po', 999));
        $this->assertFalse($this->service->releaseEncumbrance('po', 999));
        $this->assertSame(1_000_000, $center->fresh()->remainingBudget());
    }

    public function test_rfq_comparison_ranks_by_composite_and_requires_reason(): void
    {
        $rfq = $this->service->createRfq([
            'title' => 'RFQ Uji Harga',
            'closes_at' => now()->addDays(3),
        ], [$this->supplierA->id, $this->supplierB->id], $this->admin);

        $this->assertSame(2, $rfq->invitations()->count());

        SupplierScorecard::updateOrCreate(
            ['supplier_id' => $this->supplierA->id, 'period' => '2026-10'],
            [
                'supplier_id' => $this->supplierA->id,
                'period' => '2026-10',
                'otd_score' => 95,
                'quality_score' => 95,
                'price_score' => 95,
                'responsiveness_score' => 95,
                'overall_score' => 95,
                'action' => 'none',
            ]
        );

        $this->service->submitQuote($rfq, $this->supplierA, ['total_price_idr' => 8_000_000, 'lead_time_days' => 5]);
        $this->service->submitQuote($rfq, $this->supplierB, ['total_price_idr' => 10_000_000, 'lead_time_days' => 10]);

        $comparison = $this->service->compareQuotes($rfq);
        $this->assertCount(2, $comparison);
        $this->assertSame((string) $this->supplierA->id, $comparison[0]['supplier_id']);

        $quoteA = Quote::where('rfq_id', $rfq->id)->where('supplier_id', $this->supplierA->id)->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->awardQuote($rfq, $quoteA, '', $this->admin);
    }

    public function test_award_records_reason_and_status(): void
    {
        $rfq = $this->service->createRfq([
            'title' => 'RFQ Pemenang',
            'closes_at' => now()->addDays(1),
        ], [$this->supplierA->id], $this->admin);

        $this->service->submitQuote($rfq, $this->supplierA, ['total_price_idr' => 7_000_000, 'lead_time_days' => 3]);
        $quote = Quote::where('rfq_id', $rfq->id)->firstOrFail();

        $winner = $this->service->awardQuote($rfq, $quote, 'Harga terendah', $this->admin);

        $this->assertTrue($winner->is_selected);
        $this->assertSame('Harga terendah', $winner->selection_reason);
        $this->assertSame('awarded', $rfq->fresh()->status);
    }

    public function test_duplicate_quote_is_upserted(): void
    {
        $rfq = $this->service->createRfq([
            'title' => 'RFQ Replay',
            'closes_at' => now()->addDays(2),
        ], [$this->supplierA->id], $this->admin);

        $this->service->submitQuote($rfq, $this->supplierA, ['total_price_idr' => 5_000_000]);
        $this->service->submitQuote($rfq, $this->supplierA, ['total_price_idr' => 6_000_000]);

        $this->assertSame(1, Quote::where('rfq_id', $rfq->id)->count());
        $this->assertSame(6_000_000, (int) Quote::where('rfq_id', $rfq->id)->first()->total_price_idr);
    }

    // ── 33.3 Tender: segel blind + evaluasi berbobot ────────────────────

    public function test_tender_seal_is_blind_until_opened_and_evaluated(): void
    {
        $tender = $this->service->createTender([
            'title' => 'Tender Gudang',
            'type' => 'closed',
            'bids_close_at' => now()->addDay(),
        ], $this->admin);

        $this->assertSame(100, array_sum($tender->criteria));

        $bidA = $this->service->sealBid($tender, $this->supplierA, ['total_price_idr' => 10_000_000, 'lead_time_days' => 7]);
        $bidB = $this->service->sealBid($tender, $this->supplierB, ['total_price_idr' => 8_000_000, 'lead_time_days' => 14]);

        $this->assertNull($bidA->fresh()->opened_at, 'Segel buta: offer belum terbaca sebelum dibuka.');
        $this->assertSame(64, strlen($bidA->seal_hash));

        // Evaluasi sebelum buka bersamaan harus ditolak.
        try {
            $this->service->evaluateTender($tender);
            $this->fail('Evaluasi sebelum buka bersamaan harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('buka', $e->getMessage());
        }

        // Lewati waktu: majukan tenggat agar segel dapat dibuka.
        $tender->update(['bids_close_at' => now()->subMinute()]);

        $this->assertSame(2, $this->service->openBids($tender));
        $this->assertSame('opened', $tender->fresh()->status);

        $results = $this->service->evaluateTender($tender);
        $this->assertCount(2, $results);
        $this->assertSame('evaluated', $tender->fresh()->status);
        $this->assertTrue(TenderBid::where('tender_id', $tender->id)->where('is_winner', true)->exists());
    }

    public function test_tender_criteria_must_sum_to_100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createTender([
            'title' => 'Bobot salah',
            'bids_close_at' => now()->addDay(),
            'criteria' => ['price' => 50],
        ], $this->admin);
    }

    public function test_tender_seal_rejected_after_deadline(): void
    {
        $tender = $this->service->createTender([
            'title' => 'Tender lewat tenggat',
            'bids_close_at' => now()->subDay(),
        ], $this->admin);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->sealBid($tender, $this->supplierA, ['total_price_idr' => 1]);
    }

    // ── 33.4 PO: versi, tutup/batal, blanket/call-off ────────────────────

    public function test_po_records_version_and_releases_encumbrance_on_close(): void
    {
        $center = $this->makeBudgetCenter(100_000_000);

        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $this->supplierA->id,
            'title' => 'PO Pembelian Bahan',
            'budget_center_id' => $center->id,
            'lines' => [['description' => 'Bahan X', 'qty' => 10, 'unit_price' => 500_000]],
        ], $this->admin);

        $this->assertStringStartsWith('PO/', $po->number);
        $this->assertSame(5_000_000, (int) $po->total_amount);
        $this->assertSame('draft', $po->status);
        $this->assertSame(95_000_000, $center->fresh()->remainingBudget());

        // Revisi → versi + approval (33.4).
        $approval = $this->service->revisePurchaseOrder($po, ['title' => 'PO Revisi'], $this->admin, 'Perubahan volume');
        $this->assertNotNull($approval->uuid ?? $approval->id);
        $this->assertSame(2, $po->fresh()->version);
        $this->assertSame(2, $po->versions()->count());

        // Tutup → encumbrance dilepas.
        $this->service->closePurchaseOrder($po->fresh(), 'Selesai');
        $this->assertSame('closed', $po->fresh()->status);
        $this->assertSame(100_000_000, $center->fresh()->remainingBudget());
    }

    public function test_cancel_po_releases_encumbrance(): void
    {
        $center = $this->makeBudgetCenter(50_000_000);
        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $this->supplierA->id,
            'title' => 'PO Dibatalkan',
            'budget_center_id' => $center->id,
            'lines' => [['description' => 'Item', 'qty' => 1, 'unit_price' => 1_000_000]],
        ], $this->admin);

        $this->assertSame(49_000_000, $center->fresh()->remainingBudget());

        $this->service->cancelPurchaseOrder($po, 'Vendor batal');

        $this->assertSame('cancelled', $po->fresh()->status);
        $this->assertSame(50_000_000, $center->fresh()->remainingBudget());
        $this->assertSame(
            'released',
            BudgetEncumbrance::where('source_type', 'po')->where('source_id', $po->id)->value('status')
        );
    }

    public function test_blanket_po_generates_call_off(): void
    {
        [$blanket, $callOff] = $this->service->createBlanketAndCallOff([
            'supplier_id' => $this->supplierA->id,
            'title' => 'Blanket PO Semester',
            'lines' => [['description' => 'Kerangka pasok', 'qty' => 1, 'unit_price' => 1_000_000_000]],
        ], [
            'title' => 'Call-off Bulan 1',
            'lines' => [['description' => 'Pengiriman 1', 'qty' => 1, 'unit_price' => 100_000_000]],
        ], $this->admin);

        $this->assertSame('blanket', $blanket->kind);
        $this->assertSame('call_off', $callOff->kind);
        $this->assertSame($blanket->id, $callOff->blanket_po_id);
        $this->assertSame('standard', $this->service->createPurchaseOrder([
            'supplier_id' => $this->supplierA->id,
            'title' => 'PO biasa',
            'lines' => [['description' => 'x', 'qty' => 1, 'unit_price' => 1]],
        ], $this->admin)->kind);
    }

    // ── 33.5 PO Impor ───────────────────────────────────────────────────

    public function test_import_profile_computes_landed_cost_estimate(): void
    {
        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $this->supplierA->id,
            'title' => 'PO Impor',
            'lines' => [['description' => 'Meso', 'qty' => 1, 'unit_price' => 100_000_000]],
        ], $this->admin);

        $profile = $this->service->createImportProfile($po, [
            'currency' => 'USD', 'incoterm' => 'FOB', 'fx_rate' => 15_800,
            'origin_port' => 'Shanghai', 'destination_port' => 'Tanjung Priok',
            'freight_estimate_idr' => 5_000_000,
            'insurance_estimate_idr' => 1_000_000,
            'duty_estimate_idr' => 10_000_000,
        ]);

        $this->assertSame('import', $po->fresh()->kind);
        // 100jt + 5jt + 1jt + 10jt
        $this->assertSame(116_000_000, (int) $profile->landed_cost_estimate_idr);
        $this->assertSame('FOB', $profile->incoterm);
    }

    // ── 33.6 + 33.8 Dashboard ───────────────────────────────────────────

    public function test_dashboard_aggregates_documents_and_budget_warning(): void
    {
        $stats = $this->service->dashboard();

        $this->assertArrayHasKey('open_pr', $stats);
        $this->assertArrayHasKey('open_po', $stats);
        $this->assertArrayHasKey('overdue_po', $stats);
        $this->assertArrayHasKey('spend_idr', $stats);
        $this->assertArrayHasKey('budget_warning', $stats);

        // Encumbrance berlebih memicu peringatan.
        $center = $this->makeBudgetCenter(1_000_000);
        $this->service->encumber($center, 'po', 500, 5_000_000);

        $after = $this->service->dashboard();
        $this->assertTrue($after['budget_warning']);
    }

    public function test_dashboard_route_accessible_to_procurement_only(): void
    {
        $procurementUser = User::factory()->create(['role' => 'procurement']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($procurementUser)->get(route('procurement.dashboard'))->assertOk();
        $this->actingAs($customer)->get(route('procurement.dashboard'))->assertForbidden();
    }

    // ── 33.7 Integrasi Logistics: ShipmentBooking (idempoten per PO) ────

    public function test_inbound_shipment_is_booked_once_per_po(): void
    {
        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $this->supplierA->id,
            'title' => 'PO untuk pengiriman masuk',
            'expected_date' => now()->addDays(3)->toDateString(),
            'lines' => [['description' => 'Kargo inbound', 'qty' => 2, 'unit_price' => 1_000_000]],
        ], $this->admin);

        $inbound = app(InboundShipmentService::class);

        $result = $inbound->bookInbound($po, $this->admin, [
            'origin_code' => 'HUB-BDJ',
            'street' => 'Jl. Gudang No. 1',
            'city' => 'Banjarmasin',
            'consignee_name' => 'Penerima Procurement',
        ]);

        $this->assertNotEmpty($result['tracking_number']);

        // Replay (double-click / retry) tidak membuat resi ganda.
        $second = $inbound->bookInbound($po, $this->admin, [
            'origin_code' => 'HUB-BDJ',
            'street' => 'Jl. Gudang No. 1',
            'city' => 'Banjarmasin',
            'consignee_name' => 'Penerima Procurement',
        ]);

        $shipments = Shipment::where('source_type', 'procurement_po')
            ->where('source_id', $po->id)
            ->count();

        $this->assertSame(1, $shipments, 'Booking masuk harus idempoten per PO.');
        $this->assertSame($result['tracking_number'], $second['tracking_number']);
    }

    public function test_cancelled_po_cannot_be_booked_inbound(): void
    {
        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $this->supplierA->id,
            'title' => 'PO batal',
            'lines' => [['description' => 'x', 'qty' => 1, 'unit_price' => 1]],
        ], $this->admin);

        $this->service->cancelPurchaseOrder($po, 'Vendor batal');

        $inbound = app(InboundShipmentService::class);

        $this->expectException(\InvalidArgumentException::class);
        $inbound->bookInbound($po->fresh(), $this->admin, [
            'origin_code' => 'HUB-BDJ',
            'street' => 'Jl. Gudang No. 1',
            'city' => 'Banjarmasin',
        ]);
    }

    public function test_arrival_date_uses_po_expected_date(): void
    {
        $po = $this->service->createPurchaseOrder([
            'supplier_id' => $this->supplierA->id,
            'title' => 'PO jadwal tiba',
            'expected_date' => now()->addDays(9)->toDateString(),
            'lines' => [['description' => 'y', 'qty' => 1, 'unit_price' => 1]],
        ], $this->admin);

        $inbound = app(InboundShipmentService::class);

        $this->assertSame(now()->addDays(9)->toDateString(), $inbound->arrivalDate($po));
    }
}
