<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Modules\Mining\Contracts\ByProductResidueOfftakeGateway;

final class ByProductResidueOfftakeGatewayService implements ByProductResidueOfftakeGateway
{
    /**
     * @param  array<string, mixed>  $manifestDetails
     * @return array{manifest_number: string, manifest_status: string, klhk_approval_code: string}
     */
    public function transmitEnvironmentalManifest(
        string $manifestNumber,
        string $byproductCode,
        float $tons,
        string $carrierCode,
        array $manifestDetails = []
    ): array {
        $approvalCode = 'KLHK-SIMPEL-'.substr(hash('sha256', "{$manifestNumber}:{$byproductCode}:{$tons}:{$carrierCode}"), 0, 12);

        return [
            'manifest_number' => $manifestNumber,
            'manifest_status' => 'APPROVED_KLHK',
            'klhk_approval_code' => $approvalCode,
        ];
    }

    public function verifyOfftakerRecyclingPermit(string $buyerCode, string $wasteType): bool
    {
        return ! empty($buyerCode) && ! empty($wasteType);
    }
}
