<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Database\Seeders\MarineAquacultureT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marine\Application\Services\AquacultureLotPassportService;
use Modules\Marine\Application\Services\BuyerProcurementPortalService;
use Modules\Marine\Application\Services\ExportDocumentationService;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Tests\TestCase;

class BuyerProcurementAndDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarineAquacultureT1Seeder::class);
    }

    public function test_health_certificate_and_coo_generation(): void
    {
        $docService = new ExportDocumentationService;
        $lot = AquacultureExportLot::first();

        $hc = $docService->issueHealthCertificate($lot->id, 'Inspector Supriyadi');
        $this->assertNotEmpty($hc['certificate_number']);
        $this->assertEquals('OFFICIALLY_CLEARED_FOR_EXPORT', $hc['export_permit_status']);

        $coo = $docService->generateCertificateOfOrigin($lot->id, 'FORM_D', 'US');
        $this->assertTrue($coo['is_valid']);
        $this->assertEquals('INDONESIA', $coo['origin_country']);
    }

    public function test_defrost_shrinkage_claim_evaluation(): void
    {
        $procurement = new BuyerProcurementPortalService;
        $lot = AquacultureExportLot::first();

        // Bobot kirim 2178 kg. Jika terima 2150 kg (shrinkage 1.28% <= 2% batas), klaim ditolak
        $normalClaim = $procurement->evaluateDefrostShrinkageClaim($lot->id, 2150.0, 1150);
        $this->assertFalse($normalClaim['claim_accepted']);
        $this->assertEquals('NORMAL_SHRINKAGE_WITHIN_CONTRACT_TOLERANCE', $normalClaim['resolution_verdict']);

        // Jika terima 2000 kg (shrinkage 8.17% > 2% batas), klaim disetujui & ada pinalti
        $excessClaim = $procurement->evaluateDefrostShrinkageClaim($lot->id, 2000.0, 1150);
        $this->assertTrue($excessClaim['claim_accepted']);
        $this->assertGreaterThan(0, $excessClaim['penalty_amount_minor_cents']);
    }

    public function test_public_lot_passport_generation_and_tamper_evident_seal_verification(): void
    {
        $passportService = new AquacultureLotPassportService;
        $lot = AquacultureExportLot::first();

        $passport = $passportService->generatePublicLotPassport($lot->id);

        $this->assertNotEmpty($passport['passport_uuid']);
        $this->assertNotEmpty($passport['redacted_geo_grid']);
        $this->assertNotEmpty($passport['public_tamper_evident_seal']);

        $isValidSeal = $passportService->verifyPassportSeal(
            $passport['passport_uuid'],
            $passport['lot_tracking_number'],
            $passport['merkle_root_hash'],
            $passport['public_tamper_evident_seal']
        );
        $this->assertTrue($isValidSeal);
    }
}
