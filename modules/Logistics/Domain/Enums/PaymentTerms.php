<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum PaymentTerms: string
{
    case Prepaid = 'prepaid';
    case Postpaid = 'postpaid';

    public function label(): string
    {
        return match ($this) {
            self::Prepaid => 'Prabayar (Prepaid)',
            self::Postpaid => 'Pascabayar (Postpaid B2B)',
        };
    }
}
