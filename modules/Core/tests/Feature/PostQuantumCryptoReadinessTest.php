<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\PostQuantumCryptoReadinessService;
use Tests\TestCase;

class PostQuantumCryptoReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected PostQuantumCryptoReadinessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PostQuantumCryptoReadinessService::class);
    }

    public function test_eight_merkle_chains_cryptographic_integrity_verification(): void
    {
        $audit = $this->service->auditEightMerkleChains();

        $this->assertSame(8, $audit['total_chains']);
        $this->assertSame(8, $audit['valid_chains_count']);
        $this->assertSame(0, $audit['broken_chains_count']);

        $expectedChains = [
            'VEHICLE_PASSPORT_MERKLE_CHAIN',
            'PATIENT_HEALTH_RECORD_PASSPORT_CHAIN',
            'VENUE_EVENT_TICKETING_MERKLE_CHAIN',
            'LOGISTICS_SPECIMEN_CUSTODY_CHAIN',
            'SMART_LEGAL_CONTRACT_MERKLE_CHAIN',
            'PHYSICAL_ASSET_DEPRECIATION_CHAIN',
            'MINING_DISPATCH_WEIGHBRIDGE_CHAIN',
            'ESG_CARBON_SCOPE_CERTIFICATION_CHAIN',
        ];

        $chainNames = array_column($audit['chains'], 'chain_name');
        $this->assertSame($expectedChains, $chainNames);
    }

    public function test_lattice_based_post_quantum_key_exchange_handshake(): void
    {
        $clientPubkey = hash('sha256', 'CLIENT_PUBLIC_KEY_DETERMINISTIC_2026');
        $handshake = $this->service->simulateLatticeKeyExchange($clientPubkey);

        $this->assertSame('ML-KEM-768-LATTICE', $handshake['algorithm']);
        $this->assertSame('ESTABLISHED', $handshake['handshake_status']);
        $this->assertNotEmpty($handshake['shared_secret_hash']);
        $this->assertNotEmpty($handshake['ciphertext_hash']);
    }

    public function test_quantum_resistant_digital_signature_verification(): void
    {
        $payload = 'TRANSACTION_ORDER_2026_BLOCK_HASH';
        $pubkey = 'PUBKEY_KYBER_DILITHIUM_STABLE';
        $validSignature = hash('sha256', "{$payload}:{$pubkey}");

        $isValid = $this->service->verifyQuantumResistantSignature($payload, $validSignature, $pubkey);
        $this->assertTrue($isValid);

        $isTamperedValid = $this->service->verifyQuantumResistantSignature($payload, 'TAMPERED_SIG_HASH_VALUE', $pubkey);
        $this->assertFalse($isTamperedValid);
    }

    public function test_master_key_envelope_rotation_preserves_old_records(): void
    {
        $oldFingerprint = 'MASTER_KEY_FINGERPRINT_EPOCH_2025';
        $rotation = $this->service->rotateMasterKeyEnvelope($oldFingerprint);

        $this->assertSame($oldFingerprint, $rotation['old_fingerprint']);
        $this->assertNotSame($oldFingerprint, $rotation['new_fingerprint']);
        $this->assertSame('ROTATED_AND_ANCHORED', $rotation['status']);
        $this->assertGreaterThan(0, $rotation['records_re_encrypted']);
    }
}
