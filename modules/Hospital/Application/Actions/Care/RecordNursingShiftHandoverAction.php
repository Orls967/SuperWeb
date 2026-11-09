<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Services\InpatientHandoverService;

final class RecordNursingShiftHandoverAction
{
    private InpatientHandoverService $handoverService;

    public function __construct(InpatientHandoverService $handoverService)
    {
        $this->handoverService = $handoverService;
    }

    /**
     * @param array{
     *     admission_id: int,
     *     patient_id: int,
     *     shift_name: string,
     *     departing_nurse_id: int,
     *     incoming_nurse_id: int,
     *     situation: string,
     *     background: string,
     *     assessment: string,
     *     recommendation: string,
     *     acuity_grade: string
     * } $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        return $this->handoverService->recordHandover($data);
    }
}
