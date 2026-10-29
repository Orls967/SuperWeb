<?php

declare(strict_types=1);

namespace Tests\Perilaku\R2;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Integration\Application\Services\EnterpriseDigitalTrustService;
use Modules\Integration\Application\Services\IntegrationService;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('perilaku')]
class R2_8Test extends TestCase
{
    use RefreshDatabase;

    protected IntegrationService $integrationService;

    protected EnterpriseDigitalTrustService $trustService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integrationService = app(IntegrationService::class);
        $this->trustService = app(EnterpriseDigitalTrustService::class);
    }

    public function test_klausul_1_webhook_gagal_mencatat_status_failed_bukan_delivered(): void
    {
        Http::fake([
            'https://partner-down.example.com/webhook' => Http::response('Service Unavailable', 503),
        ]);

        $sub = $this->integrationService->registerWebhook(
            partnerId: 'PTN-ERR-R28',
            eventType: 'order.cancelled',
            targetUrl: 'https://partner-down.example.com/webhook'
        );

        $delivery = $this->integrationService->dispatchWebhook(
            sub: $sub,
            eventId: 'EVT-FAIL-R28',
            payload: ['reason' => 'inventory_shortage']
        );

        // Klausul 1 Terbukti: status harus failed, bukan faked delivered
        $this->assertSame('failed', $delivery->delivery_status);
        $this->assertSame(503, $delivery->http_status);
        $this->assertDatabaseHas('intg_webhook_deliveries', [
            'id' => $delivery->id,
            'delivery_status' => 'failed',
            'http_status' => 503,
        ]);
    }

    public function test_klausul_2_pesan_edi_malformed_ditolak_bukan_faked_accepted(): void
    {
        $malformedEdi = 'INVALID RAW CONTENT NOT CONFORMING TO EDI STANDARDS';

        $edi = $this->integrationService->processEdiMessage(
            standard: 'X12',
            txSet: '850',
            senderId: 'SENDER_TEST',
            receiverId: 'RECEIVER_TEST',
            rawMessage: $malformedEdi
        );

        // Klausul 2 Terbukti: Malformed EDI ditolak (rejected), bukan faked accepted
        $this->assertSame('rejected', $edi->functional_status);
        $this->assertDatabaseHas('intg_edi_messages', [
            'id' => $edi->id,
            'functional_status' => 'rejected',
        ]);
    }

    public function test_klausul_3_klaim_digital_trust_tanpa_dokumen_menjadi_simulated_bukan_published_verified(): void
    {
        $evidenceHash = hash('sha256', 'UNVERIFIED-RAW-DATA');

        // Klaim tanpa lampiran dokumen bukti di core_documents
        $claim = $this->trustService->publishClaim(
            code: 'CLAIM-R28-UNVERIFIED',
            type: 'safety',
            statement: 'Workshop Emergency Fire Protocols Audited',
            evidenceHash: $evidenceHash,
            publicVerifiable: true
        );

        // Klausul 3 Terbukti: Ditandai sebagai simulated_unverified, dilarang langsung published_verified
        $this->assertSame('simulated_unverified', $claim->status);
    }
}
