<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Exceptions;

use DomainException;

/**
 * Exception domain saat transisi atau guard state machine RobotMission ditolak (D279-1).
 */
final class RobotMissionTransitionException extends DomainException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(
            'ROBOT_MISSION_INVALID_TRANSITION',
            $from,
            $to,
            "Transisi misi robot tidak valid: {$from} -> {$to}.",
        );
    }

    public static function guardFailed(string $code, string $from, string $to, string $reason): self
    {
        return new self(
            $code,
            $from,
            $to,
            "Guard transisi {$from} -> {$to} ditolak ({$code}): {$reason}",
        );
    }
}
