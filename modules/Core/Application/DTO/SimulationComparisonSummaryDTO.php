<?php

declare(strict_types=1);

namespace Modules\Core\Application\DTO;

final readonly class SimulationComparisonSummaryDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $runAId,
        public string $runBId,
        public bool $identical,
        public ?int $divergenceDay,
        public string $summaryMessage,
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'run_a_id' => $this->runAId,
            'run_b_id' => $this->runBId,
            'identical' => $this->identical,
            'divergence_day' => $this->divergenceDay,
            'summary_message' => $this->summaryMessage,
            'metadata' => $this->metadata,
        ];
    }
}
