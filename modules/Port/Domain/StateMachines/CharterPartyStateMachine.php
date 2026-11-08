<?php

declare(strict_types=1);

namespace Modules\Port\Domain\StateMachines;

use DomainException;

/**
 * State Machine Perjanjian Sewa Kapal (Charter Party State Machine).
 *
 * Mengatur 8 status komersial dari negosiasi fixture, konfirmasi on-hire,
 * insiden off-hire teknis, re-delivery bunker survey, hingga penyelesaian final atau sengketa arbitrase.
 */
final class CharterPartyStateMachine
{
    public const STATUS_NEGOTIATING = 'NEGOTIATING';

    public const STATUS_FIXTURE_AGREED = 'FIXTURE_AGREED';

    public const STATUS_ON_HIRE = 'ON_HIRE';

    public const STATUS_OFF_HIRE = 'OFF_HIRE';

    public const STATUS_OFF_HIRE_SURVEY = 'OFF_HIRE_SURVEY';

    public const STATUS_RE_DELIVERED = 'RE_DELIVERED';

    public const STATUS_SETTLED = 'SETTLED';

    public const STATUS_DISPUTED = 'DISPUTED';

    /**
     * @var array<string, array<int, string>>
     */
    private const VALID_TRANSITIONS = [
        self::STATUS_NEGOTIATING => [
            self::STATUS_FIXTURE_AGREED,
            self::STATUS_DISPUTED, // Negosiasi gagal
        ],
        self::STATUS_FIXTURE_AGREED => [
            self::STATUS_ON_HIRE,
            self::STATUS_DISPUTED, // Wanprestasi laycan cancellation
        ],
        self::STATUS_ON_HIRE => [
            self::STATUS_OFF_HIRE,
            self::STATUS_RE_DELIVERED,
            self::STATUS_DISPUTED,
        ],
        self::STATUS_OFF_HIRE => [
            self::STATUS_OFF_HIRE_SURVEY,
            self::STATUS_ON_HIRE, // Kembali beroperasi normal
            self::STATUS_DISPUTED,
        ],
        self::STATUS_OFF_HIRE_SURVEY => [
            self::STATUS_ON_HIRE,
            self::STATUS_RE_DELIVERED,
            self::STATUS_DISPUTED,
        ],
        self::STATUS_RE_DELIVERED => [
            self::STATUS_SETTLED,
            self::STATUS_DISPUTED,
        ],
        self::STATUS_DISPUTED => [
            self::STATUS_SETTLED, // Penyelesaian damai / putusan arbitrase LMAA
        ],
        self::STATUS_SETTLED => [],
    ];

    public function canTransition(string $currentStatus, string $newStatus): bool
    {
        $allowed = self::VALID_TRANSITIONS[$currentStatus] ?? [];

        return in_array($newStatus, $allowed, true);
    }

    public function transition(string $currentStatus, string $newStatus, array $context = []): string
    {
        if (! $this->canTransition($currentStatus, $newStatus)) {
            throw new DomainException("Transisi perjanjian sewa tidak sah dari {$currentStatus} ke {$newStatus}.");
        }

        if ($newStatus === self::STATUS_ON_HIRE) {
            if (empty($context['delivery_certificate_signed'])) {
                throw new DomainException('Status On-Hire mensyaratkan Delivery Certificate telah ditandatangani.');
            }
        }

        if ($newStatus === self::STATUS_RE_DELIVERED) {
            if (empty($context['bunker_survey_completed'])) {
                throw new DomainException('Re-delivery mensyaratkan On/Off-Hire Bunker Survey telah diselesaikan.');
            }
        }

        return $newStatus;
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedTransitions(string $currentStatus): array
    {
        return self::VALID_TRANSITIONS[$currentStatus] ?? [];
    }
}
