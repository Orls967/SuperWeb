<?php

declare(strict_types=1);

namespace Modules\Integration\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Integration\Application\Services\EnterpriseDigitalTrustService;
use Modules\Integration\Application\Services\IntegrationService;
use Tests\TestCase;

class FakeStatusEliminationTest extends TestCase
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

    public function test_webhook_dispatch_failure_records_failed_status_instead_of_fake_success(): void
    {
        Http::fake([
            'https://partner-down.example.com/webhook' => Http::response('Service Unavailable', 503),
        ]);

        $sub = $this->integrationService->registerWebhook(
            partnerId: 'PTN-ERR-01',
            eventType: 'order.cancelled',
            targetUrl: 'https://partner-down.example.com/webhook'
        );

        $delivery = $this->integrationService->dispatchWebhook(
            sub: $sub,
            eventId: 'EVT-FAIL-999',
            payload: ['reason' => 'inventory_shortage']
        );

        // Status must be recorded as failed, not silently faked as delivered (K-31, X9)
        $this->assertSame('failed', $delivery->delivery_status);
        $this->assertSame(503, $delivery->http_status);
        $this->assertDatabaseHas('intg_webhook_deliveries', [
            'id' => $delivery->id,
            'delivery_status' => 'failed',
            'http_status' => 503,
        ]);
    }

    public function test_webhook_connection_exception_records_failed_500_status(): void
    {
        Http::fake([
            'https://partner-timeout.example.com/webhook' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $sub = $this->integrationService->registerWebhook(
            partnerId: 'PTN-ERR-02',
            eventType: 'payment.failed',
            targetUrl: 'https://partner-timeout.example.com/webhook'
        );

        $delivery = $this->integrationService->dispatchWebhook(
            sub: $sub,
            eventId: 'EVT-TIMEOUT-001',
            payload: ['amount' => 100000]
        );

        $this->assertSame('failed', $delivery->delivery_status);
        $this->assertSame(500, $delivery->http_status);
    }

    public function test_malformed_edi_message_is_rejected_rather_than_faked_accepted(): void
    {
        // Malformed EDI: missing ST/SE transaction markers and delimiters
        $malformedMessage = 'INVALID RAW CONTENT NOT CONFORMING TO EDI STANDARDS';

        $edi = $this->integrationService->processEdiMessage(
            standard: 'X12',
            txSet: '850',
            senderId: 'SENDER_TEST',
            receiverId: 'RECEIVER_TEST',
            rawMessage: $malformedMessage
        );

        $this->assertSame('rejected', $edi->functional_status);
        $this->assertDatabaseHas('intg_edi_messages', [
            'id' => $edi->id,
            'edi_standard' => 'X12',
            'transaction_set' => '850',
            'functional_status' => 'rejected',
        ]);
    }

    public function test_valid_edi_message_is_accepted_based_on_real_syntax_verification(): void
    {
        $validX12 = 'ISA*00*          *00*          *ZZ*SENDER         *ZZ*RECEIVER       *261006*1200*U*00401*000000001*0*P*>~GS*PO*SENDER*RECEIVER*20261006*1200*1*X*004010~ST*850*0001~BEG*00*SA*PO-99120**20261006~SE*4*0001~GE*1*1~IEA*1*000000001~';

        $edi = $this->integrationService->processEdiMessage(
            standard: 'X12',
            txSet: '850',
            senderId: 'SENDER_TEST',
            receiverId: 'RECEIVER_TEST',
            rawMessage: $validX12
        );

        $this->assertSame('accepted', $edi->functional_status);
    }

    public function test_claim_without_evidence_attachment_is_marked_simulated_unverified(): void
    {
        $rawEvidence = 'AUDIT-REPORT-UNVERIFIED-2026';
        $evidenceHash = hash('sha256', $rawEvidence);

        $claim = $this->trustService->publishClaim(
            code: 'CLAIM-UNVERIFIED-01',
            type: 'safety',
            statement: 'Workshop Emergency Fire Protocols Audited',
            evidenceHash: $evidenceHash,
            publicVerifiable: true
        );

        // Attachment does not exist in core_documents -> marked simulated_unverified (K-31, X9)
        $this->assertSame('simulated_unverified', $claim->status);
    }

    public function test_claim_with_persisted_evidence_document_is_published_verified(): void
    {
        $rawEvidence = 'ISO-9001-CERTIFIED-BATCH-SERIAL-998234-VALID-2026';
        $evidenceHash = hash('sha256', $rawEvidence);

        // Persist verified cryptographic evidence in core_documents
        DB::table('core_documents')->insert([
            'uuid' => (string) Str::uuid(),
            'documentable_type' => 'claim',
            'documentable_id' => 1,
            'document_type' => 'iso_certificate',
            'original_filename' => 'iso9001-audit.pdf',
            'stored_path' => 'evidence/iso9001-audit.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 2048,
            'checksum_sha256' => $evidenceHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $claim = $this->trustService->publishClaim(
            code: 'CLAIM-VERIFIED-01',
            type: 'quality',
            statement: '100% Automotive Workshop Service Operations Certified to ISO 9001:2015',
            evidenceHash: $evidenceHash,
            publicVerifiable: true
        );

        $this->assertSame('published_verified', $claim->status);
    }
}
