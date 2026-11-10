<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects\Simulation;

use InvalidArgumentException;

/**
 * Value Object immutable untuk waktu virtual simulasi.
 * Mengontrol konversi antara detik virtual, hari virtual, dan epoch offset.
 */
final readonly class VirtualTimestamp
{
    public const SECONDS_PER_DAY = 86400;

    public function __construct(
        public int $totalSeconds,
    ) {
        if ($this->totalSeconds < 0) {
            throw new InvalidArgumentException("Waktu virtual tidak boleh negatif: {$this->totalSeconds}");
        }
    }

    public static function fromDayAndSecond(int $dayNumber, int $secondInDay = 0): self
    {
        if ($dayNumber < 1) {
            throw new InvalidArgumentException("Nomor hari virtual minimal 1: {$dayNumber}");
        }
        if ($secondInDay < 0 || $secondInDay >= self::SECONDS_PER_DAY) {
            throw new InvalidArgumentException("Detik dalam hari harus di antara 0 dan 86399: {$secondInDay}");
        }

        $seconds = (($dayNumber - 1) * self::SECONDS_PER_DAY) + $secondInDay;

        return new self($seconds);
    }

    public function dayNumber(): int
    {
        return intdiv($this->totalSeconds, self::SECONDS_PER_DAY) + 1;
    }

    public function secondInDay(): int
    {
        return $this->totalSeconds % self::SECONDS_PER_DAY;
    }

    public function addSeconds(int $seconds): self
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException("Penambahan detik virtual tidak boleh negatif: {$seconds}");
        }

        return new self($this->totalSeconds + $seconds);
    }

    public function addDays(int $days): self
    {
        if ($days < 0) {
            throw new InvalidArgumentException("Penambahan hari virtual tidak boleh negatif: {$days}");
        }

        return new self($this->totalSeconds + ($days * self::SECONDS_PER_DAY));
    }

    public function isBefore(self $other): bool
    {
        return $this->totalSeconds < $other->totalSeconds;
    }

    public function isAfter(self $other): bool
    {
        return $this->totalSeconds > $other->totalSeconds;
    }

    public function equals(self $other): bool
    {
        return $this->totalSeconds === $other->totalSeconds;
    }

    /**
     * @return array{total_seconds: int, day_number: int, second_in_day: int}
     */
    public function toArray(): array
    {
        return [
            'total_seconds' => $this->totalSeconds,
            'day_number' => $this->dayNumber(),
            'second_in_day' => $this->secondInDay(),
        ];
    }
}
