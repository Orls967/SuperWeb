<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\StateMachines;

use DomainException;
use Modules\Hospital\Domain\Models\BpjsClaim;

class BpjsClaimStateMachine
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_ADJUDICATED_APPROVED = 'APPROVED';
    public const STATUS_ADJUDICATED_DISPUTED = 'DISPUTED';
    public const STATUS_REVISED = 'REVISED';
    public const STATUS_PAID = 'PAID';
    public const STATUS_REJECTED = 'REJECTED';

    /**
     * @var array<string, list<string>>
     */
    private const VALID_TRANSITIONS = [
        self::STATUS_DRAFT => [
            self::STATUS_VERIFIED,
            self::STATUS_REJECTED,
        ],
        self::STATUS_VERIFIED => [
            self::STATUS_SUBMITTED,
            self::STATUS_DRAFT,
        ],
        self::STATUS_SUBMITTED => [
            self::STATUS_ADJUDICATED_APPROVED,
            self::STATUS_ADJUDICATED_DISPUTED,
            self::STATUS_REJECTED,
        ],
        self::STATUS_ADJUDICATED_DISPUTED => [
            self::STATUS_REVISED,
            self::STATUS_REJECTED,
        ],
        self::STATUS_REVISED => [
            self::STATUS_SUBMITTED,
            self::STATUS_REJECTED,
        ],
        self::STATUS_ADJUDICATED_APPROVED => [
            self::STATUS_PAID,
        ],
        self::STATUS_PAID => [],
        self::STATUS_REJECTED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

    public static function transition(BpjsClaim $claim, string $targetStatus, string $note = ''): BpjsClaim
    {
        $current = $claim->claim_status ?? self::STATUS_DRAFT;

        if ($current === $targetStatus) {
            return $claim;
        }

        if (! self::canTransition($current, $targetStatus)) {
            throw new DomainException("Transisi status klaim BPJS dari [{$current}] ke [{$targetStatus}] tidak valid.");
        }

        $claim->claim_status = $targetStatus;
        if ($targetStatus === self::STATUS_ADJUDICATED_APPROVED || $targetStatus === self::STATUS_PAID) {
            $claim->adjudicated_at = now();
        }
        $claim->save();

        return $claim;
    }
}
