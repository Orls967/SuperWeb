<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum RecipeLineType: string
{
    case INGREDIENT = 'ingredient';
    case SUB_RECIPE = 'sub_recipe';

    public function label(): string
    {
        return match ($this) {
            self::INGREDIENT => 'Bahan Baku Mentah',
            self::SUB_RECIPE => 'Sub-Resep / Bumbu Dasar',
        };
    }
}
