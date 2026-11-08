<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Database\Seeders\MarineAquacultureT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marine\Application\Services\AquacultureTraceabilityQrPayloadFormatter;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Tests\TestCase;

class TraceabilityQrPayloadFormatterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarineAquacultureT1Seeder::class);
    }

    public function test_qr_payload_generation_and_decoding(): void
    {
        $formatter = new AquacultureTraceabilityQrPayloadFormatter;
        $lot = AquacultureExportLot::first();

        $qr = $formatter->formatCartonQrPayload($lot->id);

        $this->assertEquals('2.0-ASC', $qr['qr_payload_version']);
        $this->assertEquals($lot->lot_tracking_number, $qr['lot_tracking_number']);
        $this->assertNotEmpty($qr['base64_url_safe_token']);
        $this->assertEquals(64, strlen($qr['sha256_authenticity_hash']));

        $decoded = $formatter->decodeAndVerifyToken($qr['base64_url_safe_token'], $lot->lot_tracking_number);
        $this->assertEquals($lot->lot_tracking_number, $decoded['lot']);
        $this->assertEquals($lot->product_form, $decoded['form']);
    }
}
