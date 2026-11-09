<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Diagnostics;

use Modules\Hospital\Application\Services\DiagnosticImagingPacsIntegrationService;
use Modules\Hospital\Domain\Models\ImagingStudy;
use RuntimeException;

class EvaluateTurnaroundSlaAction
{
    public function __construct(
        protected DiagnosticImagingPacsIntegrationService $pacsService
    ) {}

    /**
     * @return array{is_within_sla: bool, status: string, target_minutes: int, actual_minutes: int}
     */
    public function execute(int $studyId, string $priority = 'ROUTINE'): array
    {
        /** @var ImagingStudy|null $study */
        $study = ImagingStudy::find($studyId);

        if (! $study) {
            throw new RuntimeException("Studi radiologi ID [{$studyId}] tidak ditemukan.");
        }

        return $this->pacsService->evaluateTurnaroundSla($study, $priority);
    }
}
