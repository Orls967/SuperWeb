<?php

declare(strict_types=1);

namespace Modules\Core\Domain\StateMachines;

use DomainException;
use Modules\Core\Domain\Models\CoreSearchIndexJob;

/**
 * State machine untuk siklus pekerjaan indexing mesin pencari.
 * Mendukung 8 status deterministik dan sifat idempoten pada transisi status yang sama.
 */
class IndexJobStateMachine
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_INDEXED = 'indexed';

    public const STATUS_REINDEXING = 'reindexing';

    public const STATUS_DEGRADED = 'degraded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ARCHIVED = 'archived';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_INDEXED,
        self::STATUS_REINDEXING,
        self::STATUS_DEGRADED,
        self::STATUS_FAILED,
        self::STATUS_ARCHIVED,
    ];

    /**
     * Matriks transisi yang diizinkan: [current_status => [allowed_next_statuses]].
     */
    private const TRANSITIONS = [
        self::STATUS_PENDING => [
            self::STATUS_QUEUED,
            self::STATUS_PROCESSING,
            self::STATUS_FAILED,
            self::STATUS_ARCHIVED,
        ],
        self::STATUS_QUEUED => [
            self::STATUS_PROCESSING,
            self::STATUS_FAILED,
            self::STATUS_ARCHIVED,
        ],
        self::STATUS_PROCESSING => [
            self::STATUS_INDEXED,
            self::STATUS_REINDEXING,
            self::STATUS_DEGRADED,
            self::STATUS_FAILED,
        ],
        self::STATUS_REINDEXING => [
            self::STATUS_INDEXED,
            self::STATUS_DEGRADED,
            self::STATUS_FAILED,
        ],
        self::STATUS_INDEXED => [
            self::STATUS_REINDEXING,
            self::STATUS_PROCESSING,
            self::STATUS_DEGRADED,
            self::STATUS_ARCHIVED,
            self::STATUS_FAILED,
        ],
        self::STATUS_DEGRADED => [
            self::STATUS_REINDEXING,
            self::STATUS_PROCESSING,
            self::STATUS_INDEXED,
            self::STATUS_FAILED,
            self::STATUS_ARCHIVED,
        ],
        self::STATUS_FAILED => [
            self::STATUS_QUEUED,
            self::STATUS_PROCESSING,
            self::STATUS_ARCHIVED,
        ],
        self::STATUS_ARCHIVED => [],
    ];

    public function __construct(private CoreSearchIndexJob $job) {}

    public function getCurrentStatus(): string
    {
        return (string) $this->job->status;
    }

    /**
     * Memeriksa apakah transisi ke target status diizinkan.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        $current = $this->getCurrentStatus();

        if ($current === $targetStatus) {
            return true; // Idempotent
        }

        $allowed = self::TRANSITIONS[$current] ?? [];

        return in_array($targetStatus, $allowed, true);
    }

    /**
     * Melakukan mutasi transisi status secara idempoten dan menyimpan ke database.
     *
     * @throws DomainException jika transisi tidak valid
     */
    public function transitionTo(string $targetStatus, ?string $errorMessage = null): CoreSearchIndexJob
    {
        $current = $this->getCurrentStatus();

        // Idempotency: Jika status sudah sama, return tanpa error
        if ($current === $targetStatus) {
            if ($errorMessage !== null && $this->job->error_message !== $errorMessage) {
                $this->job->error_message = $errorMessage;
                $this->job->save();
            }

            return $this->job;
        }

        if (! $this->canTransitionTo($targetStatus)) {
            throw new DomainException(
                sprintf('Invalid state transition for IndexJob [%s] from [%s] to [%s].', $this->job->id, $current, $targetStatus)
            );
        }

        $this->job->status = $targetStatus;

        if ($errorMessage !== null) {
            $this->job->error_message = $errorMessage;
        }

        if ($targetStatus === self::STATUS_PROCESSING && $this->job->started_at === null) {
            $this->job->started_at = now();
        }

        if (in_array($targetStatus, [self::STATUS_INDEXED, self::STATUS_FAILED, self::STATUS_ARCHIVED], true)) {
            $this->job->completed_at = now();
        }

        $this->job->save();

        return $this->job;
    }
}
