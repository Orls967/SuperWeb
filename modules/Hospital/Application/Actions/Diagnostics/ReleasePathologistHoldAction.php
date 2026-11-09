<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Diagnostics;

use App\Models\User;
use Modules\Hospital\Application\Services\PathologistVerificationService;
use Modules\Hospital\Domain\Models\LabResult;
use RuntimeException;

class ReleasePathologistHoldAction
{
    public function __construct(
        protected PathologistVerificationService $service
    ) {}

    public function execute(
        int $resultId,
        int $pathologistUserId,
        ?string $interpretation = null,
        ?string $signatureHash = null
    ): LabResult {
        /** @var LabResult|null $result */
        $result = LabResult::find($resultId);

        if (! $result) {
            throw new RuntimeException("Hasil lab ID [{$resultId}] tidak ditemukan.");
        }

        /** @var User|null $pathologist */
        $pathologist = User::find($pathologistUserId);

        if (! $pathologist) {
            throw new RuntimeException("Pengguna patolog ID [{$pathologistUserId}] tidak ditemukan.");
        }

        return $this->service->verifyAndRelease(
            result: $result,
            pathologist: $pathologist,
            interpretation: $interpretation,
            signatureHash: $signatureHash
        );
    }
}
