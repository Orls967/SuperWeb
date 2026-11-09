<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

final class DecisionMetrics
{
    public function __construct(
        public readonly int $decisionPoints,
        public readonly int $executableStatements,
        public readonly float $density,
        public readonly int $methodCount,
        public readonly int $propertyCount,
        public readonly string $certificate = '',
    ) {}

    /**
     * DTO anemik jika memiliki banyak properti tetapi 0 decision points dan method minim
     */
    public function isAnemic(): bool
    {
        return $this->decisionPoints === 0 && ($this->propertyCount >= 3 || $this->methodCount === 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'decision_points' => $this->decisionPoints,
            'executable_statements' => $this->executableStatements,
            'density' => $this->density,
            'method_count' => $this->methodCount,
            'property_count' => $this->propertyCount,
            'is_anemic' => $this->isAnemic(),
            'certificate' => $this->certificate,
        ];
    }
}
