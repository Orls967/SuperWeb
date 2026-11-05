<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Enums;

enum TreatyType: string
{
    case QuotaShare = 'quota_share';
    case Surplus = 'surplus';
    case XolRisk = 'xol_risk';
    case XolCat = 'xol_cat';
    case StopLoss = 'stop_loss';
    case Facultative = 'facultative';

    public function label(): string
    {
        return match ($this) {
            self::QuotaShare => 'Quota Share',
            self::Surplus => 'Surplus',
            self::XolRisk => 'Excess of Loss per Risk',
            self::XolCat => 'Cat Excess of Loss',
            self::StopLoss => 'Stop Loss',
            self::Facultative => 'Fakultatif',
        };
    }

    public function isProportional(): bool
    {
        return in_array($this, [self::QuotaShare, self::Surplus, self::Facultative], true);
    }

    public function isXol(): bool
    {
        return in_array($this, [self::XolRisk, self::XolCat], true);
    }
}
