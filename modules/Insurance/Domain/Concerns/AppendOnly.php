<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Concerns;

use Modules\Insurance\Domain\Exceptions\AppendOnlyViolationException;

/**
 * Fakta bersifat append-only (PR5): model menolak update/delete; koreksi lewat entri pembalik.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(static function (): never {
            throw new AppendOnlyViolationException(static::class, 'update');
        });
        static::deleting(static function (): never {
            throw new AppendOnlyViolationException(static::class, 'delete');
        });
    }
}
