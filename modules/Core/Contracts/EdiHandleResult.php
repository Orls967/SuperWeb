<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Deterministic result returned by owner module EdiDocumentHandler implementations.
 */
final readonly class EdiHandleResult
{
    public bool $isSuccess;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $succeeded,
        public string $ownerModule = '',
        public string $targetReference = '',
        public string $idempotencyKey = '',
        public string $message = 'Processed',
        public array $metadata = [],
    ) {
        $this->isSuccess = $succeeded;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function success(
        string $targetReference,
        string $message = 'Processed',
        string $ownerModule = '',
        string $idempotencyKey = '',
        array $metadata = [],
    ): self {
        return new self(
            succeeded: true,
            ownerModule: $ownerModule,
            targetReference: $targetReference,
            idempotencyKey: $idempotencyKey,
            message: $message,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function failure(
        string $message,
        string $targetReference = '',
        string $ownerModule = '',
        string $idempotencyKey = '',
        array $metadata = [],
    ): self {
        return new self(
            succeeded: false,
            ownerModule: $ownerModule,
            targetReference: $targetReference,
            idempotencyKey: $idempotencyKey,
            message: $message,
            metadata: $metadata,
        );
    }
}
