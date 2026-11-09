<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\StateMachines;

use DomainException;
use Modules\Hospital\Domain\Models\MedFridgeBreach;

class ColdChainStateMachine
{
    public const STATUS_OPTIMAL = 'OPTIMAL';
    public const STATUS_WARNING = 'WARNING';
    public const STATUS_QUARANTINED = 'QUARANTINED';
    public const STATUS_DISPOSED = 'DISPOSED';
    public const STATUS_RELEASED = 'RELEASED';

    /**
     * @var array<string, list<string>>
     */
    private const VALID_TRANSITIONS = [
        self::STATUS_OPTIMAL => [
            self::STATUS_WARNING,
            self::STATUS_QUARANTINED,
        ],
        self::STATUS_WARNING => [
            self::STATUS_OPTIMAL,
            self::STATUS_QUARANTINED,
        ],
        self::STATUS_QUARANTINED => [
            self::STATUS_DISPOSED,
            self::STATUS_RELEASED,
        ],
        self::STATUS_DISPOSED => [],
        self::STATUS_RELEASED => [
            self::STATUS_WARNING,
            self::STATUS_QUARANTINED,
        ],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

    public static function transition(MedFridgeBreach $breach, string $targetStatus, string $rationale = ''): MedFridgeBreach
    {
        $current = $breach->status ?? self::STATUS_QUARANTINED;

        if ($current === $targetStatus) {
            return $breach;
        }

        if (! self::canTransition($current, $targetStatus)) {
            throw new DomainException("Transisi status insiden cold-chain dari [{$current}] ke [{$targetStatus}] tidak diizinkan.");
        }

        // Invariant: pembebasan (RELEASED) dari karantina wajib memiliki keterangan uji stabilitas klinis
        if ($targetStatus === self::STATUS_RELEASED && empty(trim($rationale))) {
            throw new DomainException('Pembebasan lot dari karantina cold-chain wajib menyertakan rasionalisasi validasi stabilitas klinis.');
        }

        $breach->status = $targetStatus;
        $breach->save();

        return $breach;
    }
}
