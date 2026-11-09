<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\StateMachines;

use DomainException;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\AdmissionTransition;

/**
 * State Machine untuk siklus hidup admisi pasien rawat inap (8 status).
 * requested -> bed_reserved -> admitted -> transferred -> discharge_planned -> discharged -> readmitted -> cancelled
 */
final class AdmissionStateMachine
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_BED_RESERVED = 'bed_reserved';
    public const STATUS_ADMITTED = 'admitted';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_DISCHARGE_PLANNED = 'discharge_planned';
    public const STATUS_DISCHARGED = 'discharged';
    public const STATUS_READMITTED = 'readmitted';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array<string, list<string>> Tabel transisi sah
     */
    private const TRANSITIONS = [
        self::STATUS_REQUESTED => [self::STATUS_BED_RESERVED, self::STATUS_CANCELLED],
        self::STATUS_BED_RESERVED => [self::STATUS_ADMITTED, self::STATUS_CANCELLED],
        self::STATUS_ADMITTED => [self::STATUS_TRANSFERRED, self::STATUS_DISCHARGE_PLANNED, self::STATUS_CANCELLED],
        self::STATUS_TRANSFERRED => [self::STATUS_ADMITTED, self::STATUS_DISCHARGE_PLANNED],
        self::STATUS_DISCHARGE_PLANNED => [self::STATUS_DISCHARGED, self::STATUS_ADMITTED],
        self::STATUS_DISCHARGED => [self::STATUS_READMITTED],
        self::STATUS_READMITTED => [self::STATUS_ADMITTED, self::STATUS_DISCHARGE_PLANNED],
        self::STATUS_CANCELLED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $allowed = self::TRANSITIONS[$from] ?? [];
        return in_array($to, $allowed, true);
    }

    public static function transition(
        Admission $admission,
        string $toStatus,
        string $trigger,
        ?int $actorId = null,
        ?array $metadata = null
    ): void {
        $fromStatus = $admission->status;
        if (! self::canTransition($fromStatus, $toStatus)) {
            throw new DomainException("Transisi admisi tidak valid dari '{$fromStatus}' ke '{$toStatus}'.");
        }

        $admission->status = $toStatus;
        if ($toStatus === self::STATUS_ADMITTED && $admission->admitted_at === null) {
            $admission->admitted_at = now();
        }
        if ($toStatus === self::STATUS_DISCHARGED) {
            $admission->discharged_at = now();
        }
        $admission->save();

        AdmissionTransition::create([
            'admission_id' => $admission->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'trigger' => $trigger,
            'actor_id' => $actorId,
            'metadata' => $metadata,
            'transitioned_at' => now(),
        ]);
    }
}
