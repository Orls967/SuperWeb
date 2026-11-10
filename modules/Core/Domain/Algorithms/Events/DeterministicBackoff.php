<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Events;

/**
 * Algoritma A5: DeterministicBackoff [Fase R8]
 *
 * Menghitung jeda retry eksponensial deterministik dengan jitter teratur
 * berbasis Linear Congruential Generator (LCG) MINSTD Park-Miller (a=48271, m=2^31 - 1).
 *
 * Seluruh kalkulasi beroperasi pada aritmetika bilangan bulat murni tanpa float,
 * tanpa pemanggilan fungsi sistem acak (rand(), mt_rand()), dan tanpa sleep().
 * Kompleksitas: O(1) waktu dan O(1) memori.
 */
class DeterministicBackoff
{
    public const MODULUS = 2147483647; // 2^31 - 1

    public const MULTIPLIER = 48271;

    public function __construct(
        protected int $baseSeconds = 30,
        protected int $maxSeconds = 3600
    ) {}

    /**
     * Hitung waktu jeda retry deterministik.
     *
     * @param  int|string  $messageId  ID pesan atau integer hash
     * @param  int  $attempt  Nomor percobaan (1-based, n >= 1)
     * @return array{raw: int, jitter: int, delay: int}
     */
    public function calculate(int|string $messageId, int $attempt): array
    {
        $n = max(1, $attempt);
        $m = self::MODULUS;
        $a = self::MULTIPLIER;

        // Hitung eksponensial mentah: min(batas, dasar * 2^(n - 1))
        $exponent = $n - 1;
        if ($exponent >= 30) {
            $raw = $this->maxSeconds;
        } else {
            $raw = min($this->maxSeconds, $this->baseSeconds * (1 << $exponent));
        }

        // Konversi messageId menjadi bilangan bulat positif
        $idInt = is_int($messageId) ? $messageId : abs(crc32((string) $messageId));
        if ($idInt < 0) {
            $idInt = abs($idInt);
        }

        // seed = (idPesan mod m) * (n + 1) mod m (bila 0 -> 1)
        $seed = (($idInt % $m) * (($n + 1) % $m)) % $m;
        if ($seed === 0) {
            $seed = 1;
        }

        // LCG step: x = seed * 48271 mod m
        $x = ($seed * $a) % $m;

        // jitter = x mod (mentah / 4 + 1)
        $divisor = intdiv($raw, 4) + 1;
        $jitter = $x % $divisor;

        // jeda = mentah + jitter
        $delay = $raw + $jitter;

        return [
            'raw' => $raw,
            'jitter' => $jitter,
            'delay' => $delay,
        ];
    }

    /**
     * Hitung total detik jeda secara langsung.
     */
    public function calculateDelaySeconds(int|string $messageId, int $attempt): int
    {
        return $this->calculate($messageId, $attempt)['delay'];
    }
}
