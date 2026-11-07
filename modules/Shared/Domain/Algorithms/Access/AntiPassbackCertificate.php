<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Access;

final readonly class AntiPassbackCertificate
{
    /**
     * @param  list<array{direction: string, nonce: string, timestamp: string, hash: string}>  $history
     */
    public function __construct(
        public string $ticketId,
        public int $maxReentry,
        public int $reentryCount,
        public string $currentDirection,
        public int $netInMinusOut,
        public array $history,
        public string $hmacSignature,
    ) {}
}
