<?php

declare(strict_types=1);

namespace Modules\Core\Domain\StateMachines\Exceptions;

use RuntimeException;

class InvalidSimulationTransitionException extends RuntimeException
{
    public function __construct(string $fromStatus, string $toStatus, string $reason = '')
    {
        $message = "Transisi status simulasi ilegal dari '{$fromStatus}' ke '{$toStatus}'.";
        if ($reason !== '') {
            $message .= " Alasan penolakan: {$reason}";
        }
        parent::__construct($message);
    }
}
