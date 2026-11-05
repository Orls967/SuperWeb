<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Enums;

enum WealthRiskProfile: string
{
    case CONSERVATIVE = 'CONSERVATIVE';
    case MODERATE = 'MODERATE';
    case AGGRESSIVE = 'AGGRESSIVE';

    /**
     * Target alokasi syariah: [sukuk, gold, equity] dalam bps (total 10000).
     *
     * @return array{sukuk_bps: int, gold_bps: int, equity_bps: int}
     */
    public function targetAllocationBps(): array
    {
        return match ($this) {
            self::CONSERVATIVE => ['sukuk_bps' => 7000, 'gold_bps' => 2000, 'equity_bps' => 1000],
            self::MODERATE => ['sukuk_bps' => 4000, 'gold_bps' => 3000, 'equity_bps' => 3000],
            self::AGGRESSIVE => ['sukuk_bps' => 2000, 'gold_bps' => 2000, 'equity_bps' => 6000],
        };
    }
}

