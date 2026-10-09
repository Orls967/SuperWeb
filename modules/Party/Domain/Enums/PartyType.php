<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Enums;

enum PartyType: string
{
    case Person = 'person';
    case Company = 'company';
}
