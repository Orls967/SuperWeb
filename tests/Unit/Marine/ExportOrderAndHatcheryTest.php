<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Database\Seeders\MarineAquacultureT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marine\Application\Services\AquacultureExportOrderService;
use Modules\Marine\Application\Services\AquacultureHatcheryCertificationService;
use Modules\Marine\Domain\Models\AquacultureExportOrder;
use Modules\Marine\Domain\Models\AquacultureHatchery;
use Tests\TestCase;

class ExportOrderAndHatcheryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarineAquacultureT1Seeder::class);
    }

    public function test_letter_of_credit_compliance_evaluation(): void
    {
        $orderService = new AquacultureExportOrderService;
        $order = AquacultureExportOrder::first();

        $validLc = [
            'lc_number' => 'LC-CITI-2026-001',
            'issuing_bank_swift' => 'CITIUS33XXX',
            'lc_amount_minor_cents' => 3000000, // $30,000 >= $25,000 order
            'expiry_date' => now()->addDays(60)->toDateString(),
            'latest_shipment_date' => now()->addDays(30)->toDateString(),
            'partial_shipments_allowed' => true,
            'transshipment_allowed' => false,
            'presented_documents' => [
                'SIGNED_COMMERCIAL_INVOICE',
                'CLEAN_ON_BOARD_OCEAN_BILL_OF_LADING',
                'SPS_HEALTH_CERTIFICATE_KKP',
                'CERTIFICATE_OF_ORIGIN_FORM',
                'PACKING_LIST',
            ],
        ];

        $res = $orderService->verifyLetterOfCreditCompliance($order->id, $validLc);

        $this->assertTrue($res['is_compliant']);
        $this->assertEquals('UCP600_DOCUMENTS_CLEARED', $res['ucp600_clearance']);
        $this->assertEmpty($res['discrepancies']);
    }

    public function test_shipment_volume_tolerance_under_ucp600(): void
    {
        $orderService = new AquacultureExportOrderService;
        $order = AquacultureExportOrder::first();

        // Kuota 2178 kg. Muat 2200 kg (deviasi 1.01% <= 5% batas UCP 600) -> lulus
        $this->assertTrue($orderService->verifyShipmentVolumeTolerance($order->id, 2200.0));

        // Muat 2400 kg (deviasi 10.19% > 5% batas) -> gagal
        $this->assertFalse($orderService->verifyShipmentVolumeTolerance($order->id, 2400.0));
    }

    public function test_hatchery_spf_certificate_verification(): void
    {
        $hatcheryService = new AquacultureHatcheryCertificationService;
        $hatchery = AquacultureHatchery::first();

        $res = $hatcheryService->verifySpfCertificateValidity($hatchery->id);

        $this->assertTrue($res['is_valid']);
        $this->assertGreaterThan(0, $res['days_until_expiration']);
        $this->assertEquals('SPF_CERTIFICATE_ACTIVE', $res['status']);
    }
}
