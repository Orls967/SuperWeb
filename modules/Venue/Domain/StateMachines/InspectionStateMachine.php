<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use DomainException;
use Modules\Venue\Domain\Models\VenueBagInspection;

final class InspectionStateMachine
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_INSPECTING = 'inspecting';
    public const STATUS_CLEARED = 'cleared';
    public const STATUS_CONFISCATED = 'confiscated';
    public const STATUS_POLICE_REFERRED = 'police_referred';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_QUEUED => [
            self::STATUS_INSPECTING,
            self::STATUS_CLEARED,
        ],
        self::STATUS_INSPECTING => [
            self::STATUS_CLEARED,
            self::STATUS_CONFISCATED,
            self::STATUS_POLICE_REFERRED,
        ],
        self::STATUS_CONFISCATED => [
            self::STATUS_POLICE_REFERRED,
            self::STATUS_CLEARED,
        ],
        self::STATUS_CLEARED => [],
        self::STATUS_POLICE_REFERRED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function transition(VenueBagInspection $inspection, string $targetStatus, ?string $reason = null): void
    {
        $current = $inspection->status;

        if (! self::canTransition($current, $targetStatus)) {
            throw new DomainException("Transisi inspeksi tas tidak sah dari '{$current}' ke '{$targetStatus}'");
        }

        $inspection->status = $targetStatus;
        $inspection->save();
    }
}
