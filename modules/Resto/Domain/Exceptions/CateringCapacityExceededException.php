<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Exceptions;

use RuntimeException;

class CateringCapacityExceededException extends RuntimeException
{
    public function __construct(string $message = 'Kapasitas produksi katering outlet pada tanggal tersebut telah penuh.')
    {
        parent::__construct($message);
    }
}
