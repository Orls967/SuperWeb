<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Operations;

use DomainException;
use Modules\Hospital\Domain\Models\MedicalWasteManifest;
use Modules\Hospital\Domain\Services\MedicalWasteManifestService;

final class CertifyWasteDestructionAction
{
    public function __construct(
        private readonly MedicalWasteManifestService $wasteService
    ) {}

    /**
     * @param  array{manifest_id: int, destruction_facility_name: string, destruction_certificate_hash: string}  $data
     */
    public function execute(array $data): MedicalWasteManifest
    {
        $manifestId = (int) $data['manifest_id'];
        $manifest = MedicalWasteManifest::find($manifestId);

        if (! $manifest) {
            throw new DomainException("Manifest limbah B3 #{$manifestId} tidak ditemukan.");
        }

        return $this->wasteService->certifyDestruction(
            manifest: $manifest,
            destructionFacilityName: $data['destruction_facility_name'],
            destructionCertificateHash: $data['destruction_certificate_hash']
        );
    }
}
