<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Exceptions;

use Exception;

class ShortageException extends Exception
{
    /**
     * @param  array<int, array{ingredient_id: int, ingredient_name: string, needed_base_unit: string, available_base_unit: string, shortage_base_unit: string, base_unit: string}>  $shortages
     */
    public function __construct(
        public readonly array $shortages,
        public readonly int $suggestedMaxPortions = 0,
        string $message = 'Stok bahan tidak mencukupi untuk memasak batch ini.'
    ) {
        parent::__construct($message);
    }
}
