<?php

declare(strict_types=1);

namespace Modules\Core\Application\DTOs\Ai;

final readonly class AnomalyEvaluationResult
{
    /**
     * @param  list<string>  $reasons
     * @param  array<string, mixed>  $metrics
     */
    public function __construct(
        public string $transactionRef,
        public float $riskScore,
        public bool $isQuarantined,
        public array $reasons,
        public ?string $quarantineCode,
        public array $metrics,
        public string $decisionHash,
        public string $decisionCode,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'transaction_ref' => $this->transactionRef,
            'risk_score' => $this->riskScore,
            'is_quarantined' => $this->isQuarantined,
            'reasons' => $this->reasons,
            'quarantine_code' => $this->quarantineCode,
            'metrics' => $this->metrics,
            'decision_hash' => $this->decisionHash,
            'decision_code' => $this->decisionCode,
        ];
    }
}
