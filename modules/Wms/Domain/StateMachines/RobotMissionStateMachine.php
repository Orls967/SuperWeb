<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\StateMachines;

use Modules\Wms\Domain\Exceptions\RobotMissionTransitionException;
use Modules\Wms\Models\RobotMission;

/**
 * State machine 8 status untuk misi robotik gudang (D279-1, Fase 279).
 *
 * Status: queued, assigned, moving, picking, delivering, completed, failed, fallback_manual.
 * Status akhir: completed.
 */
final class RobotMissionStateMachine
{
    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_ASSIGNED = 'assigned';

    public const string STATUS_MOVING = 'moving';

    public const string STATUS_PICKING = 'picking';

    public const string STATUS_DELIVERING = 'delivering';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_FALLBACK_MANUAL = 'fallback_manual';

    public const int MAX_RETRY_ATTEMPTS = 2;

    public const int MIN_BATTERY_FOR_ASSIGNMENT_PCT = 15;

    /**
     * Tabel transisi resmi 8 status (D279-1).
     *
     * @var array<string, list<string>>
     */
    public const array ALLOWED_TRANSITIONS = [
        self::STATUS_QUEUED => [
            self::STATUS_ASSIGNED,
        ],
        self::STATUS_ASSIGNED => [
            self::STATUS_MOVING,
            self::STATUS_FAILED,
        ],
        self::STATUS_MOVING => [
            self::STATUS_PICKING,
            self::STATUS_FAILED,
        ],
        self::STATUS_PICKING => [
            self::STATUS_DELIVERING,
            self::STATUS_FAILED,
        ],
        self::STATUS_DELIVERING => [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
        ],
        self::STATUS_FAILED => [
            self::STATUS_QUEUED,
            self::STATUS_FALLBACK_MANUAL,
        ],
        self::STATUS_FALLBACK_MANUAL => [
            self::STATUS_COMPLETED,
        ],
        self::STATUS_COMPLETED => [],
    ];

    /**
     * @return list<string>
     */
    public static function allStates(): array
    {
        return array_keys(self::ALLOWED_TRANSITIONS);
    }

    public static function isTerminal(string $status): bool
    {
        return $status === self::STATUS_COMPLETED;
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw RobotMissionTransitionException::invalidTransition($from, $to);
        }
    }

    /**
     * Evaluasi guard domain untuk setiap transisi D279-1.
     *
     * @param  array{
     *     robot_online?: bool,
     *     robot_battery_pct?: int,
     *     has_hungarian_allocation?: bool,
     *     has_active_zone_reservation?: bool,
     *     zone_staff_blocked?: bool,
     *     interlock_passed?: bool,
     *     arrived_at_source_bin?: bool,
     *     item_scanned?: bool,
     *     arrived_at_destination?: bool,
     *     warehouse_task_confirmed?: bool,
     *     reason_code?: string|null,
     *     manual_task_created?: bool,
     *     wave_replanned?: bool
     * }  $context
     */
    public static function assertTransitionWithGuards(RobotMission $mission, string $to, array $context = []): void
    {
        $from = (string) $mission->status;
        self::assertTransition($from, $to);

        match ("{$from}->{$to}") {
            'queued->assigned' => self::guardQueuedToAssigned($from, $to, $context),
            'assigned->moving' => self::guardAssignedToMoving($from, $to, $context),
            'moving->picking' => self::guardMovingToPicking($from, $to, $context),
            'picking->delivering' => self::guardPickingToDelivering($from, $to, $context),
            'delivering->completed' => self::guardDeliveringToCompleted($from, $to, $context),
            'assigned->failed', 'moving->failed', 'picking->failed', 'delivering->failed' => self::guardToFailed($from, $to, $context),
            'failed->queued' => self::guardFailedToQueued($mission, $from, $to),
            'failed->fallback_manual' => self::guardFailedToFallbackManual($from, $to, $context),
            'fallback_manual->completed' => self::guardFallbackManualToCompleted($from, $to, $context),
            default => throw RobotMissionTransitionException::invalidTransition($from, $to),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function guardQueuedToAssigned(string $from, string $to, array $context): void
    {
        if (! ($context['has_hungarian_allocation'] ?? false)) {
            throw RobotMissionTransitionException::guardFailed('ALLOCATION_REQUIRED', $from, $to, 'Alokasi Hungarian wajib tersedia sebelum assigned.');
        }
        if (! ($context['robot_online'] ?? false)) {
            throw RobotMissionTransitionException::guardFailed('ROBOT_OFFLINE', $from, $to, 'Robot berstatus offline tidak dapat dialokasikan.');
        }
        $battery = (int) ($context['robot_battery_pct'] ?? 0);
        if ($battery < self::MIN_BATTERY_FOR_ASSIGNMENT_PCT) {
            throw RobotMissionTransitionException::guardFailed('ROBOT_LOW_BATTERY', $from, $to, "Baterai robot {$battery}% di bawah ambang minimum.");
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function guardAssignedToMoving(string $from, string $to, array $context): void
    {
        if ($context['zone_staff_blocked'] ?? false) {
            throw RobotMissionTransitionException::guardFailed('ZONE_INTERLOCK_STAFF_BLOCKED', $from, $to, 'Zona lalu lintas sedang diblokir staf (interlock).');
        }
        if (! ($context['interlock_passed'] ?? true)) {
            throw RobotMissionTransitionException::guardFailed('ZONE_INTERLOCK_FAILED', $from, $to, 'Pemeriksaan safety interlock zona gagal.');
        }
        if (! ($context['has_active_zone_reservation'] ?? false)) {
            throw RobotMissionTransitionException::guardFailed('ACTIVE_RESERVATION_REQUIRED', $from, $to, 'Misi wajib memegang reservasi zona aktif J1 sebelum moving.');
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function guardMovingToPicking(string $from, string $to, array $context): void
    {
        if (! ($context['arrived_at_source_bin'] ?? true)) {
            throw RobotMissionTransitionException::guardFailed('SOURCE_BIN_NOT_REACHED', $from, $to, 'Robot belum tiba di bin sumber.');
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function guardPickingToDelivering(string $from, string $to, array $context): void
    {
        if (! ($context['item_scanned'] ?? true)) {
            throw RobotMissionTransitionException::guardFailed('ITEM_NOT_SCANNED', $from, $to, 'Item belum terpindai saat picking.');
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function guardDeliveringToCompleted(string $from, string $to, array $context): void
    {
        if (! ($context['arrived_at_destination'] ?? true)) {
            throw RobotMissionTransitionException::guardFailed('DESTINATION_NOT_REACHED', $from, $to, 'Robot belum tiba di tujuan.');
        }
        if (! ($context['warehouse_task_confirmed'] ?? true)) {
            throw RobotMissionTransitionException::guardFailed('WAREHOUSE_TASK_NOT_CONFIRMED', $from, $to, 'WarehouseTask Fase 41 terkait wajib terkonfirmasi.');
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function guardToFailed(string $from, string $to, array $context): void
    {
        $reason = trim((string) ($context['reason_code'] ?? ''));
        if ($reason === '') {
            throw RobotMissionTransitionException::guardFailed('FAILURE_REASON_REQUIRED', $from, $to, 'Kode alasan kegagalan misi wajib diisi.');
        }
    }

    private static function guardFailedToQueued(RobotMission $mission, string $from, string $to): void
    {
        if ((int) $mission->retry_count >= self::MAX_RETRY_ATTEMPTS) {
            throw RobotMissionTransitionException::guardFailed(
                'MAX_RETRY_EXCEEDED',
                $from,
                $to,
                'Batas coba ulang maksimum ('.self::MAX_RETRY_ATTEMPTS.' kali) telah tercapai; wajib fallback manual.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function guardFailedToFallbackManual(string $from, string $to, array $context): void
    {
        if (! ($context['manual_task_created'] ?? false)) {
            throw RobotMissionTransitionException::guardFailed('MANUAL_TASK_REQUIRED', $from, $to, 'Task manual Fase 41 wajib dibuat untuk fallback_manual.');
        }
        if (! ($context['wave_replanned'] ?? false)) {
            throw RobotMissionTransitionException::guardFailed('WAVE_REPLAN_REQUIRED', $from, $to, 'Wave wajib disusun ulang saat fallback_manual.');
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function guardFallbackManualToCompleted(string $from, string $to, array $context): void
    {
        if (! ($context['warehouse_task_confirmed'] ?? false)) {
            throw RobotMissionTransitionException::guardFailed('MANUAL_TASK_NOT_CONFIRMED', $from, $to, 'Task manual terkait wajib berstatus confirmed.');
        }
    }
}
