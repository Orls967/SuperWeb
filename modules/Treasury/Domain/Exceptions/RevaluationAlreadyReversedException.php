<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Exceptions;

use RuntimeException;

class RevaluationAlreadyReversedException extends RuntimeException
{
    public function __construct(string $message = 'Jurnal revaluasi ini telah dibalik sebelumnya.')
    {
        parent::__construct($message);
    }
}
