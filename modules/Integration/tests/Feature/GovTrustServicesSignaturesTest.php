<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\GovTrustServicesSignaturesService;
use Tests\TestCase;

class GovTrustServicesSignaturesTest extends TestCase
{
    use RefreshDatabase;

    protected GovTrustServicesSignaturesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GovTrustServicesSignaturesService::class);
    }

    public function test_document_signature_and_modification_tamper_detection(): void
    {
        $originalContract = 'This is a binding commercial nickel supply agreement between Party A and Party B.';

        // 1. Sign original document (292.1 & 292.4)
        $signature = $this->service->signDocument(
            documentCode: 'DOC-AGREEMENT-2026-NICKEL',
            signerIdentityId: 'DIRECTOR_BUDI',
            signerRole: 'LEGAL_DIRECTOR',
            documentContent: $originalContract
        );

        // 2. Unmodified document validates successfully (292.4)
        $isValidOriginal = $this->service->verifyDocumentSignature($signature->signature_id, $originalContract);
        $this->assertTrue($isValidOriginal);

        // 3. Modified document invalidates signature (tamper detection) (292.4)
        $tamperedContract = 'This is a binding commercial nickel supply agreement between Party A and Party B. Mod: Price is $0.';
        $isValidTampered = $this->service->verifyDocumentSignature($signature->signature_id, $tamperedContract);
        $this->assertFalse($isValidTampered);
    }

    public function test_verifiable_credential_issuance_and_revocation_stops_access(): void
    {
        // 1. Issue medical license credential (292.2 & 292.4)
        $this->service->issueCredential(
            credentialId: 'CRED-MED-DR-SITI-01',
            subjectId: 'DR_SITI_RAHMAH',
            credentialType: 'MEDICAL_LICENSE',
            issuerTrustAnchor: 'KEMENKES_RI',
            expiryDate: now()->addYears(2)->toDateString()
        );

        // 2. Valid credential permits operation (292.4)
        $hasAccessBeforeRevoke = $this->service->validateCredentialAccess('CRED-MED-DR-SITI-01');
        $this->assertTrue($hasAccessBeforeRevoke);

        // 3. Credential revoked mid-session immediately stops transaction access (292.4 & 292.5 Edge Case)
        $this->service->revokeCredential('CRED-MED-DR-SITI-01');
        $hasAccessAfterRevoke = $this->service->validateCredentialAccess('CRED-MED-DR-SITI-01');
        $this->assertFalse($hasAccessAfterRevoke);
    }

    public function test_cross_border_trust_corridor_registry(): void
    {
        // Register bilateral Singapore - Indonesia trust anchor corridor (292.3 & 292.6)
        $corridor = $this->service->registerTrustCorridor(
            corridorCode: 'CORRIDOR-ID-SG-FINTECH',
            originAnchor: 'KOMINFO_RI',
            destAnchor: 'IMDA_SINGAPORE',
            policy: 'ETSI_PADES_CROSS_BORDER'
        );

        $this->assertTrue((bool) $corridor->is_corridor_active);
        $this->assertEquals('ETSI_PADES_CROSS_BORDER', $corridor->accepted_signature_policy);
    }

    public function test_gov_trust_services_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->signDocument('DOC-AUD', 'USER-1', 'CEO', 'Content');
        $this->service->issueCredential('CRED-AUD', 'SUB-1', 'TYPE', 'ANCHOR', now()->addYear()->toDateString());
        $this->service->registerTrustCorridor('COR-AUD', 'A1', 'A2', 'POLICY');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: expired credential not marked revoked
        DB::table('gov_verifiable_credentials')->insert([
            'credential_id' => 'CRED-EXPIRED-ACTIVE',
            'subject_id' => 'USER_ROGUE',
            'credential_type' => 'PILOT_CERT',
            'issuer_trust_anchor' => 'DEPHUB',
            'is_revoked' => false,
            'expiry_date' => now()->subDay()->toDateString(), // Expired!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
