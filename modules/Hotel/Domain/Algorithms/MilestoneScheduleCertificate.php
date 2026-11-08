<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final readonly class MilestoneScheduleCertificate
{
    /**
     * @param  array<int, array{
     *     milestone_index: int,
     *     name: string,
     *     percentage: float,
     *     amount_idr: int,
     *     due_date: string
     * }>  $milestones
     */
    public function __construct(
        public string $contractId,
        public int $totalContractAmountIdr,
        public string $signingDate,
        public string $eventStartDate,
        public int $totalMilestonesSumIdr,
        public bool $isConserved,
        public array $milestones,
        public string $signatureHash
    ) {}
}
