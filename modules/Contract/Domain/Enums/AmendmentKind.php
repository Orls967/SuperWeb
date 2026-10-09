<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Enums;

enum AmendmentKind: string
{
    case Amendment = 'amendment';
    case Addendum = 'addendum';

    public function label(): string
    {
        return match ($this) {
            self::Amendment => 'Amandemen',
            self::Addendum => 'Addendum',
        };
    }
}
