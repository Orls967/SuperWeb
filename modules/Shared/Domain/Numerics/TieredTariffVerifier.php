<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use UnexpectedValueException;

/**
 * Verifier for tiered tariff calculations.
 */
final class TieredTariffVerifier
{
    /**
     * @param  list<array{up_to: int|null, rate: int}>  $tiers
     * @param  array{total_cost: int, breakdown: list<array{tier_index: int, units: int, rate: int, cost: int}>}  $result
     *
     * @throws UnexpectedValueException
     */
    public static function verify(int $units, array $tiers, array $result): bool
    {
        $sumUnits = 0;
        $sumCost = 0;

        foreach ($result['breakdown'] as $i => $item) {
            if ($item['units'] < 0 || $item['cost'] < 0) {
                throw new UnexpectedValueException("Negative units or cost in tier {$i}.");
            }
            if ($item['cost'] !== $item['units'] * $item['rate']) {
                throw new UnexpectedValueException("Cost mismatch in tier {$i}: got {$item['cost']}, expected {$item['units']} * {$item['rate']}");
            }
            $sumUnits += $item['units'];
            $sumCost += $item['cost'];
        }

        if ($sumUnits !== $units) {
            throw new UnexpectedValueException("Total breakdown units {$sumUnits} does not match input units {$units}.");
        }

        if ($sumCost !== $result['total_cost']) {
            throw new UnexpectedValueException("Total breakdown cost {$sumCost} does not match total_cost {$result['total_cost']}.");
        }

        return true;
    }
}
