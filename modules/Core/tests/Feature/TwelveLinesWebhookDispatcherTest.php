<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Modules\Core\Application\Services\TwelveLinesWebhookDispatcherService;
use Tests\TestCase;

class TwelveLinesWebhookDispatcherTest extends TestCase
{
    protected TwelveLinesWebhookDispatcherService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TwelveLinesWebhookDispatcherService::class);
    }

    public function test_hmac_sha256_webhook_signing_and_verification(): void
    {
        $secret = 'webhook_secret_key_12lines_conglomerate';
        $event = 'hospital.code_blue_triggered';
        $data = ['patient_id' => 'P-991', 'room' => 'ICU-04'];

        $signed = $this->service->generateSignedPayload($event, $data, $secret);

        $this->assertNotEmpty($signed['signature']);
        $this->assertJson($signed['payload']);

        // Positive verification
        $valid = $this->service->verifyWebhookSignature($signed['payload'], $signed['signature'], $secret);
        $this->assertTrue($valid);

        // Tampered payload verification
        $tamperedPayload = str_replace('ICU-04', 'ICU-99', $signed['payload']);
        $invalid = $this->service->verifyWebhookSignature($tamperedPayload, $signed['signature'], $secret);
        $this->assertFalse($invalid);
    }
}
