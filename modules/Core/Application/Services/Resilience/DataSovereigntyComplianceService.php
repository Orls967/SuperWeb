<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Resilience;

use Modules\Core\Application\DTOs\Resilience\DataSovereigntyCheckDto;
use Modules\Core\Application\DTOs\Resilience\KmsRotationDto;
use Modules\Core\Domain\Contracts\DataSovereigntyComplianceInterface;
use Modules\Core\Domain\Models\CoreDataSovereigntyPolicy;
use Modules\Core\Domain\Models\CoreKmsKeyRotation;

class DataSovereigntyComplianceService implements DataSovereigntyComplianceInterface
{
    /**
     * @return array{allowed: bool, violation: ?string, regulation: string}
     */
    public function evaluateResidency(DataSovereigntyCheckDto $dto): array
    {
        $policy = CoreDataSovereigntyPolicy::query()
            ->where('jurisdiction_code', $dto->jurisdictionCode)
            ->where('status', 'ACTIVE')
            ->first();

        $regulation = $policy ? $policy->regulation_reference : 'UU PDP No. 27/2022';
        $mandatoryRegion = $policy ? $policy->mandatory_storage_region : 'ap-southeast-3';

        // Check 1: Mandatory territorial residency for domestic personal/financial data
        if ($dto->jurisdictionCode === 'ID' && $dto->containsPii && $dto->storageRegion !== $mandatoryRegion) {
            return [
                'allowed' => false,
                'violation' => sprintf(
                    'Data kedaulatan Indonesia (%s) mewajibkan data pribadi/finansial tersimpan di region domestik (%s). Terdeteksi di: %s.',
                    $regulation,
                    $mandatoryRegion,
                    $dto->storageRegion
                ),
                'regulation' => $regulation,
            ];
        }

        // Check 2: Cross border transfer restrictions without compliance treaty
        $allowCrossBorder = $policy ? $policy->cross_border_transfer_allowed : false;
        if ($dto->isCrossBorderTransfer && ! $allowCrossBorder) {
            return [
                'allowed' => false,
                'violation' => sprintf(
                    'Transfer lintas batas data kedaulatan dilarang di bawah kepatuhan %s.',
                    $regulation
                ),
                'regulation' => $regulation,
            ];
        }

        return [
            'allowed' => true,
            'violation' => null,
            'regulation' => $regulation,
        ];
    }

    public function rotateKmsKey(KmsRotationDto $dto): CoreKmsKeyRotation
    {
        // Mark existing active keys for this alias as ROTATED
        CoreKmsKeyRotation::query()
            ->where('key_alias', $dto->keyAlias)
            ->where('status', 'ACTIVE')
            ->update(['status' => 'ROTATED']);

        $latestVersion = (int) CoreKmsKeyRotation::query()
            ->where('key_alias', $dto->keyAlias)
            ->max('key_version');

        $nextVersion = $latestVersion + 1;

        // Deterministic cryptographic key fingerprint derived from alias, version, and algo
        $keyFingerprint = hash(
            'sha256',
            sprintf('kms:%s:v%d:%s:entropy-vault-2026', $dto->keyAlias, $nextVersion, $dto->encryptionAlgorithm)
        );

        return CoreKmsKeyRotation::create([
            'key_alias' => $dto->keyAlias,
            'key_version' => $nextVersion,
            'status' => 'ACTIVE',
            'encryption_algorithm' => $dto->encryptionAlgorithm,
            'key_fingerprint' => $keyFingerprint,
            'rotation_reason' => $dto->rotationReason ?? 'Scheduled cryptographic key rotation cycle',
            'user_id' => $dto->userId,
            'rotated_at' => now(),
        ]);
    }
}
