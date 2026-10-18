<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature\Resilience;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\DTOs\Resilience\DataSovereigntyCheckDto;
use Modules\Core\Application\DTOs\Resilience\KmsRotationDto;
use Modules\Core\Application\Services\Resilience\DataSovereigntyComplianceService;
use Modules\Core\Domain\Models\CoreDataSovereigntyPolicy;
use Modules\Core\Domain\Models\CoreKmsKeyRotation;
use Tests\TestCase;

class DataSovereigntyComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_validates_data_sovereignty_residency_under_uu_pdp(): void
    {
        CoreDataSovereigntyPolicy::create([
            'jurisdiction_code' => 'ID',
            'regulation_reference' => 'UU PDP No. 27/2022',
            'mandatory_storage_region' => 'ap-southeast-3',
            'pii_encryption_required' => true,
            'cross_border_transfer_allowed' => false,
            'status' => 'ACTIVE',
        ]);

        /** @var DataSovereigntyComplianceService $service */
        $service = app(DataSovereigntyComplianceService::class);

        // Valid domestic storage
        $domesticDto = new DataSovereigntyCheckDto(
            jurisdictionCode: 'ID',
            dataType: 'CUSTOMER_FINANCIAL_PROFILE',
            storageRegion: 'ap-southeast-3',
            containsPii: true,
            isCrossBorderTransfer: false
        );

        $res1 = $service->evaluateResidency($domesticDto);
        $this->assertTrue($res1['allowed']);
        $this->assertNull($res1['violation']);

        // Illegal storage outside domestic region
        $illegalDto = new DataSovereigntyCheckDto(
            jurisdictionCode: 'ID',
            dataType: 'CUSTOMER_FINANCIAL_PROFILE',
            storageRegion: 'us-east-1',
            containsPii: true,
            isCrossBorderTransfer: false
        );

        $res2 = $service->evaluateResidency($illegalDto);
        $this->assertFalse($res2['allowed']);
        $this->assertStringContainsString('Data kedaulatan Indonesia', $res2['violation']);

        // Cross-border transfer without approval
        $crossBorderDto = new DataSovereigntyCheckDto(
            jurisdictionCode: 'ID',
            dataType: 'CUSTOMER_FINANCIAL_PROFILE',
            storageRegion: 'ap-southeast-3',
            containsPii: true,
            isCrossBorderTransfer: true
        );

        $res3 = $service->evaluateResidency($crossBorderDto);
        $this->assertFalse($res3['allowed']);
        $this->assertStringContainsString('Transfer lintas batas data kedaulatan dilarang', $res3['violation']);
    }

    public function test_rotates_kms_keys_deterministically_with_active_version_progression(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        /** @var DataSovereigntyComplianceService $service */
        $service = app(DataSovereigntyComplianceService::class);

        // Initial key rotation (v1)
        $dto1 = new KmsRotationDto(
            keyAlias: 'master-database-envelope-key',
            encryptionAlgorithm: 'AES-256-GCM',
            rotationReason: 'Initial enterprise bootstrap key',
            userId: $admin->id
        );

        $v1 = $service->rotateKmsKey($dto1);
        $this->assertSame(1, $v1->key_version);
        $this->assertSame('ACTIVE', $v1->status);
        $this->assertSame(64, strlen($v1->key_fingerprint));

        // Subsequent rotation (v2)
        $dto2 = new KmsRotationDto(
            keyAlias: 'master-database-envelope-key',
            encryptionAlgorithm: 'AES-256-GCM',
            rotationReason: 'Quarterly compliance security rotation',
            userId: $admin->id
        );

        $v2 = $service->rotateKmsKey($dto2);
        $this->assertSame(2, $v2->key_version);
        $this->assertSame('ACTIVE', $v2->status);
        $this->assertNotSame($v1->key_fingerprint, $v2->key_fingerprint);

        // Previous key must now be marked ROTATED
        $v1Fresh = CoreKmsKeyRotation::find($v1->id);
        $this->assertSame('ROTATED', $v1Fresh->status);
    }
}
