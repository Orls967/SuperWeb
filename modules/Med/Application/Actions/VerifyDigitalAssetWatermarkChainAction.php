<?php

declare(strict_types=1);

namespace Modules\Med\Application\Actions;

use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Shared\Application\BaseAction;

class VerifyDigitalAssetWatermarkChainAction extends BaseAction
{
    /**
     * @return array{ip_code: string, is_valid: bool, watermark_hash: string, owner: string}
     */
    public function execute(string $ipAssetId, string $providedWatermarkHash): array
    {
        $ip = MedIpAsset::findOrFail($ipAssetId);

        $expectedHash = hash('sha256', "{$ip->id}:{$ip->ip_code}:{$ip->owner_entity_id}");
        $isValid = hash_equals($expectedHash, $providedWatermarkHash);

        $this->audit(
            action: 'med_watermark_verified',
            auditable: $ip,
            newValues: ['is_valid' => $isValid, 'watermark' => $providedWatermarkHash],
            impactType: 'copyright_protection',
        );

        return [
            'ip_code' => $ip->ip_code,
            'is_valid' => $isValid,
            'watermark_hash' => $providedWatermarkHash,
            'owner' => $ip->owner_entity_id,
        ];
    }
}
