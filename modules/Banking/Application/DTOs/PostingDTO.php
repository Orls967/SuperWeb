<?php

declare(strict_types=1);

namespace Modules\Banking\Application\DTOs;

use DateTimeInterface;

final readonly class PostingDTO
{
    /**
     * @param  array<int, PostingEntryDTO>  $entries
     */
    public function __construct(
        public string $type,
        public string $description,
        public string $idempotencyKey,
        public array $entries,
        public ?string $referenceType = null,
        public string|int|null $referenceId = null,
        public ?array $meta = null,
        public ?int $createdBy = null,
        public ?DateTimeInterface $postedAt = null,
    ) {}
}
