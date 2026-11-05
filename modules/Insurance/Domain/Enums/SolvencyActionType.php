<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Enums;

enum SolvencyActionType: string
{
    case AddQuotaShare = 'add_quota_share';
    case ReduceExposure = 'reduce_exposure';
    case CapitalInjection = 'capital_injection';

    public function label(): string
    {
        return match ($this) {
            self::AddQuotaShare => 'Tambah quota share',
            self::ReduceExposure => 'Kurangi eksposur per kelas',
            self::CapitalInjection => 'Injeksi modal',
        };
    }
}
