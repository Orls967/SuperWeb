<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation;

use Modules\Core\Domain\Algorithms\Simulation\Exceptions\InvalidAlgorithmCertificateException;

/**
 * Algoritma A3: Stream Seed Partitioning (MINSTD) dan Rantai Fingerprint Kriptografis Harian.
 *
 * Kompleksitas Waktu:
 * - split: O(L) di mana L adalah panjang string nama aliran (komputasi CRC32)
 * - nextChainHash: O(K log K + B) di mana K adalah jumlah kunci ringkasan dan B adalah ukuran byte JSON ringkasan
 * Kompleksitas Ruang: O(K + B)
 *
 * Seluruh aritmetika modulus menggunakan integer murni 64-bit PHP tanpa float.
 */
class StreamSeedAndFingerprint
{
    public const MODULUS = 2147483647;

    public const MULTIPLIER = 48271;

    /**
     * @var list<array{day: int, prev_hash: string, new_hash: string, summary: array<string, mixed>}>
     */
    protected array $chainLog = [];

    protected ?string $initialHash = null;

    /**
     * Membagi seed global menjadi seed aliran terisolasi untuk handler tertentu secara deterministik.
     */
    public function split(int $seed, string $name): int
    {
        $m = self::MODULUS;
        $crc = crc32($name);
        $s = $crc % $m;
        if ($s === 0) {
            $s = 1;
        }

        $seedMod = $seed % $m;
        if ($seedMod <= 0) {
            $seedMod = 1;
        }

        $child = ($seedMod * $s) % $m;
        if ($child === 0) {
            $child = 1;
        }

        return $child;
    }

    /**
     * Membuat generator aliran acak baru dari seed turunan.
     */
    public function createStream(int $seed): SimulationStream
    {
        return new SimulationStream($seed);
    }

    /**
     * Menghasilkan hash rantai awal (h0) dari seed run.
     * h0 = SHA-256("sim-run|seed=<seed>")
     */
    public function initialChainHash(int $seed): string
    {
        $hash = hash('sha256', "sim-run|seed={$seed}");
        $this->initialHash = $hash;

        return $hash;
    }

    /**
     * Menghasilkan hash rantai hari berikutnya (h_i) secara rekursif append-only.
     * h_i = SHA-256(h_{i-1} + "|" + hari + "|" + canonicalJson(ringkasan))
     *
     * @param  array<string, mixed>  $summary
     */
    public function nextChainHash(string $prevHash, int $day, array $summary): string
    {
        $canonicalJson = $this->canonicalJson($summary);
        $dataToHash = "{$prevHash}|{$day}|{$canonicalJson}";
        $newHash = hash('sha256', $dataToHash);

        $this->chainLog[] = [
            'day' => $day,
            'new_hash' => $newHash,
            'prev_hash' => $prevHash,
            'summary' => $summary,
        ];

        return $newHash;
    }

    /**
     * Menghasilkan JSON kanonik dengan kunci terurut leksikografis dan tanpa spasi redundan.
     *
     * @param  array<string, mixed>  $data
     */
    public function canonicalJson(array $data): string
    {
        $sorted = $data;
        ksort($sorted);

        foreach ($sorted as $key => $value) {
            if (is_array($value)) {
                $sorted[$key] = json_decode($this->canonicalJson($value), true);
            }
        }

        return (string) json_encode($sorted, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Menghasilkan sertifikat kriptografis untuk audit integritas eksekusi algoritma A3.
     *
     * @return array{
     *     algorithm: string,
     *     initial_hash: string|null,
     *     total_days: int,
     *     chain_log: list<array{day: int, prev_hash: string, new_hash: string, summary: array<string, mixed>}>,
     *     sha256: string
     * }
     */
    public function generateCertificate(): array
    {
        $canonicalData = [
            'algorithm' => 'A3_StreamSeedAndFingerprint',
            'chain_log' => $this->chainLog,
            'initial_hash' => $this->initialHash,
            'total_days' => count($this->chainLog),
        ];

        $json = (string) json_encode($canonicalData, JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $json);

        return array_merge($canonicalData, ['sha256' => $hash]);
    }

    /**
     * Memverifikasi validitas sertifikat eksekusi A3.
     *
     * @param  array<string, mixed>  $certificate
     *
     * @throws InvalidAlgorithmCertificateException
     */
    public function verifyCertificate(array $certificate): bool
    {
        $expectedHash = $certificate['sha256'] ?? '';
        $dataToHash = $certificate;
        unset($dataToHash['sha256']);

        $json = (string) json_encode($dataToHash, JSON_UNESCAPED_SLASHES);
        $computedHash = hash('sha256', $json);

        if (! hash_equals($expectedHash, $computedHash)) {
            throw new InvalidAlgorithmCertificateException('Sertifikat A3 gagal diverifikasi: hash integritas tidak cocok.');
        }

        return true;
    }
}
