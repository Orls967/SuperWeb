<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use DomainException;
use Modules\Venue\Domain\Models\DynamicBundlePackage;

final class DynamicBundlePackageStateMachine
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    public const STATUS_SUSPENDED = 'SUSPENDED';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_DRAFT => [
            self::STATUS_PUBLISHED,
            self::STATUS_ARCHIVED,
        ],
        self::STATUS_PUBLISHED => [
            self::STATUS_SUSPENDED,
            self::STATUS_ARCHIVED,
        ],
        self::STATUS_SUSPENDED => [
            self::STATUS_PUBLISHED,
            self::STATUS_ARCHIVED,
        ],
        self::STATUS_ARCHIVED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $fromUpper = strtoupper($from);
        $toUpper = strtoupper($to);

        $allowed = self::TRANSITIONS[$fromUpper] ?? [];

        return in_array($toUpper, $allowed, true);
    }

    public static function transition(DynamicBundlePackage $bundle, string $to): DynamicBundlePackage
    {
        $current = strtoupper((string) $bundle->status);
        $target = strtoupper($to);

        if (! self::canTransition($current, $target)) {
            throw new DomainException(sprintf('Transisi status paket bundling dinamis dari %s ke %s tidak diizinkan', $current, $target));
        }

        $bundle->status = $target;

        return $bundle;
    }
}
