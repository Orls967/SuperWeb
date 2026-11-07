<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final class FamilyPoolDTO
{
    /**
     * @param  list<array{pass_id: string, pass_number: string, relationship: string, joined_at: string}>  $members
     */
    public function __construct(
        public string $poolId,
        public string $headPassId,
        public string $poolName,
        public int $totalMembers,
        public string $status,
        public array $members,
        public string $createdAt
    ) {}
}
