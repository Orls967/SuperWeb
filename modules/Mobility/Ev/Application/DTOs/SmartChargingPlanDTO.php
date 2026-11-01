<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Application\DTOs;

class SmartChargingPlanDTO
{
    /**
     * @param  array<string, int>  $allocations  session_code => Wh
     * @param  array<string, int>  $deficits  session_code => Wh
     * @param  array<string, mixed>  $certificate
     */
    public function __construct(
        public array $allocations,
        public array $deficits,
        public int $totalAllocatedWh,
        public int $totalDeficitWh,
        public array $certificate
    ) {}

    public function isFeasible(): bool
    {
        return $this->totalDeficitWh === 0;
    }

    public function totalAllocatedWh(): int
    {
        return $this->totalAllocatedWh;
    }

    public function totalDeficitWh(): int
    {
        return $this->totalDeficitWh;
    }

    public function getAllocatedWh(string $sessionCode): int
    {
        return $this->allocations[$sessionCode] ?? 0;
    }

    public function getDeficitWh(string $sessionCode): int
    {
        return $this->deficits[$sessionCode] ?? 0;
    }

    public function getDeficits(): array
    {
        return array_filter($this->deficits, fn ($v) => $v > 0);
    }

    public function setCertificate(array $cert): void
    {
        $this->certificate = $cert;
    }
}
