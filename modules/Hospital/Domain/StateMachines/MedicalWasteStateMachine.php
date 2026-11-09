<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\StateMachines;

use DomainException;
use Modules\Hospital\Domain\Models\MedicalWasteManifest;

class MedicalWasteStateMachine
{
    public const STATUS_LOGGED_IN_WARD = 'LOGGED_IN_WARD';

    public const STATUS_RECEIVED_AT_TPS = 'RECEIVED_AT_TPS';

    public const STATUS_HANDED_TO_TRANSPORTER = 'HANDED_TO_TRANSPORTER';

    public const STATUS_IN_TRANSIT = 'IN_TRANSIT';

    public const STATUS_INCINERATED = 'INCINERATED';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_DISCREPANCY_FLAGGED = 'DISCREPANCY_FLAGGED';

    /**
     * @var array<string, list<string>>
     */
    private const VALID_TRANSITIONS = [
        self::STATUS_LOGGED_IN_WARD => [
            self::STATUS_RECEIVED_AT_TPS,
            self::STATUS_DISCREPANCY_FLAGGED,
        ],
        self::STATUS_RECEIVED_AT_TPS => [
            self::STATUS_HANDED_TO_TRANSPORTER,
            self::STATUS_DISCREPANCY_FLAGGED,
        ],
        self::STATUS_HANDED_TO_TRANSPORTER => [
            self::STATUS_IN_TRANSIT,
            self::STATUS_INCINERATED,
            self::STATUS_DISCREPANCY_FLAGGED,
        ],
        self::STATUS_IN_TRANSIT => [
            self::STATUS_INCINERATED,
            self::STATUS_DISCREPANCY_FLAGGED,
        ],
        self::STATUS_INCINERATED => [
            self::STATUS_COMPLETED,
        ],
        self::STATUS_DISCREPANCY_FLAGGED => [
            self::STATUS_RECEIVED_AT_TPS,
            self::STATUS_COMPLETED,
        ],
        self::STATUS_COMPLETED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

    public static function transition(MedicalWasteManifest $manifest, string $targetStatus, string $reason = ''): MedicalWasteManifest
    {
        $current = $manifest->status ?? self::STATUS_LOGGED_IN_WARD;

        if ($current === $targetStatus) {
            return $manifest;
        }

        if (! self::canTransition($current, $targetStatus)) {
            throw new DomainException("Transisi rantai limbah medis dari [{$current}] ke [{$targetStatus}] tidak sah.");
        }

        // Invariant: Penyelesaian manifest wajib memiliki sertifikat pemusnahan (destruction certificate)
        if ($targetStatus === self::STATUS_COMPLETED && empty($manifest->destruction_certificate_hash)) {
            throw new DomainException('Manifest limbah B3 tidak dapat diselesaikan tanpa hash sertifikat pemusnahan berizin.');
        }

        $manifest->status = $targetStatus;
        $manifest->save();

        return $manifest;
    }
}
