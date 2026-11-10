<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Services;

use InvalidArgumentException;

/**
 * Matriks tarif denda demurrage & detention armada logistik pelabuhan.
 */
final class DemurrageTariffMatrixService
{
    /** @var array<string, array{free_days: int, tier1_rate: int, tier2_rate: int}> */
    private array $tariffs = [
        '20GP' => ['free_days' => 5, 'tier1_rate' => 200000, 'tier2_rate' => 400000],
        '40GP' => ['free_days' => 5, 'tier1_rate' => 350000, 'tier2_rate' => 700000],
        '40HC' => ['free_days' => 5, 'tier1_rate' => 400000, 'tier2_rate' => 800000],
        '20RF' => ['free_days' => 3, 'tier1_rate' => 500000, 'tier2_rate' => 1000000],
    ];

    public function calculateDemurrage(string $isoType, int $dwellDays): int
    {
        if ($dwellDays < 0) {
            throw new InvalidArgumentException("Hari penumpukan tidak boleh negatif: {$dwellDays}");
        }

        $config = $this->tariffs[$isoType] ?? $this->tariffs['20GP'];
        $freeDays = $config['free_days'];

        if ($dwellDays <= $freeDays) {
            return 0;
        }

        $overdue = $dwellDays - $freeDays;

        if ($overdue <= 5) {
            return $overdue * $config['tier1_rate'];
        }

        $tier1Total = 5 * $config['tier1_rate'];
        $tier2Days = $overdue - 5;

        return $tier1Total + ($tier2Days * $config['tier2_rate']);
    }

    /**
     * @return array<string, array{free_days: int, tier1_rate: int, tier2_rate: int}>
     */
    public function getTariffTable(): array
    {
        return $this->tariffs;
    }
}
