<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Exceptions;

use Exception;

class RecipeCycleDetected extends Exception
{
    public function __construct(string $message = 'Terdeteksi ketergantungan resep sirkular (recursive loop).')
    {
        parent::__construct($message);
    }
}
