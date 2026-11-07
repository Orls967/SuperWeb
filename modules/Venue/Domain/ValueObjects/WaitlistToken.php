<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

final class WaitlistToken
{
    public function __construct(
        public readonly string $token,
        public readonly \DateTimeImmutable $expiresAt
    ) {
        if (trim($this->token) === '') {
            throw new \InvalidArgumentException('Token tidak boleh kosong.');
        }
    }

    public static function generate(int $validityMinutes = 15): self
    {
        $raw = 'WLT-'.bin2hex(random_bytes(16));
        $expiry = (new \DateTimeImmutable)->modify("+{$validityMinutes} minutes");

        return new self($raw, $expiry);
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable;

        return $now > $this->expiresAt;
    }
}
