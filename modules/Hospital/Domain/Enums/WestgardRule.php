<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Enums;

enum WestgardRule: string
{
    case RULE_1_2S = '1-2S';
    case RULE_1_3S = '1-3S';
    case RULE_2_2S = '2-2S';
    case RULE_R_4S = 'R-4S';
    case RULE_4_1S = '4-1S';
    case RULE_10_X = '10-X';

    public function isRejection(): bool
    {
        return $this !== self::RULE_1_2S;
    }

    public function description(): string
    {
        return match ($this) {
            self::RULE_1_2S => 'Peringatan: 1 nilai kontrol di luar rentang +/- 2 SD',
            self::RULE_1_3S => 'Penolakan Keras (Random Error): 1 nilai kontrol di luar +/- 3 SD',
            self::RULE_2_2S => 'Penolakan (Systematic Error): 2 nilai kontrol berturut di luar rentang 2 SD pada sisi sama',
            self::RULE_R_4S => 'Penolakan (Random Error): Rentang antar-kontrol dalam run melebihi 4 SD',
            self::RULE_4_1S => 'Penolakan (Systematic Drift): 4 nilai kontrol berturut di luar rentang 1 SD pada sisi sama',
            self::RULE_10_X => 'Penolakan (Systematic Shift): 10 nilai kontrol berturut pada satu sisi rata-rata',
        };
    }
}
