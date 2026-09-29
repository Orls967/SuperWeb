<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Exceptions;

use Carbon\CarbonInterface;
use RuntimeException;

class PinLockedException extends RuntimeException
{
    public function __construct(public ?CarbonInterface $lockedUntil = null)
    {
        $timeStr = $lockedUntil ? $lockedUntil->diffForHumans() : '15 menit';
        parent::__construct("PIN terkunci karena salah 5 kali. Coba lagi dalam {$timeStr}.");
    }
}
