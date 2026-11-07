<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use InvalidArgumentException;

/**
 * State Machine Siklus Hidup Pendaftaran & Akses Biometrik (Face-ID).
 * Menjamin kepatuhan privasi satu arah: PENDING_CONSENT -> CONSENTED -> CAPTURE_IN_PROGRESS ->
 * LIVENESS_VERIFIED -> TEMPLATE_HASHED -> ENROLLED_ACTIVE -> REVOKED.
 */
final class BiometricEnrolmentStateMachine
{
    public const string STATUS_PENDING_CONSENT = 'PENDING_CONSENT';

    public const string STATUS_CONSENTED = 'CONSENTED';

    public const string STATUS_CAPTURE_IN_PROGRESS = 'CAPTURE_IN_PROGRESS';

    public const string STATUS_LIVENESS_VERIFIED = 'LIVENESS_VERIFIED';

    public const string STATUS_TEMPLATE_HASHED = 'TEMPLATE_HASHED';

    public const string STATUS_ENROLLED_ACTIVE = 'ENROLLED_ACTIVE';

    public const string STATUS_REVOKED = 'REVOKED';

    /**
     * @var array<string, array<int, string>>
     */
    private const array VALID_TRANSITIONS = [
        self::STATUS_PENDING_CONSENT => [self::STATUS_CONSENTED, self::STATUS_REVOKED],
        self::STATUS_CONSENTED => [self::STATUS_CAPTURE_IN_PROGRESS, self::STATUS_REVOKED],
        self::STATUS_CAPTURE_IN_PROGRESS => [self::STATUS_LIVENESS_VERIFIED, self::STATUS_REVOKED],
        self::STATUS_LIVENESS_VERIFIED => [self::STATUS_TEMPLATE_HASHED, self::STATUS_REVOKED],
        self::STATUS_TEMPLATE_HASHED => [self::STATUS_ENROLLED_ACTIVE, self::STATUS_REVOKED],
        self::STATUS_ENROLLED_ACTIVE => [self::STATUS_REVOKED],
        self::STATUS_REVOKED => [], // Sink terminal state
    ];

    /**
     * Validasi apakah transisi status legal.
     */
    public function canTransition(string $fromStatus, string $toStatus): bool
    {
        $allowed = self::VALID_TRANSITIONS[$fromStatus] ?? [];

        return in_array($toStatus, $allowed, true);
    }

    /**
     * Terapkan transisi status atau lempar eksepsi bila melanggar aturan privasi.
     *
     * @throws InvalidArgumentException
     */
    public function transition(string $fromStatus, string $toStatus): string
    {
        if (! $this->canTransition($fromStatus, $toStatus)) {
            throw new InvalidArgumentException(sprintf(
                'Transisi status biometrik ilegal dari [%s] ke [%s]',
                $fromStatus,
                $toStatus
            ));
        }

        return $toStatus;
    }

    /**
     * Daftar seluruh status yang sah.
     *
     * @return array<int, string>
     */
    public static function allStatuses(): array
    {
        return [
            self::STATUS_PENDING_CONSENT,
            self::STATUS_CONSENTED,
            self::STATUS_CAPTURE_IN_PROGRESS,
            self::STATUS_LIVENESS_VERIFIED,
            self::STATUS_TEMPLATE_HASHED,
            self::STATUS_ENROLLED_ACTIVE,
            self::STATUS_REVOKED,
        ];
    }
}
