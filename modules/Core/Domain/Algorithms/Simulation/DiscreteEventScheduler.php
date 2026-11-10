<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation;

use Modules\Core\Domain\Algorithms\Simulation\Exceptions\InvalidAlgorithmCertificateException;
use Modules\Core\Domain\Algorithms\Simulation\Exceptions\PastEventScheduledException;

/**
 * Algoritma A1: Discrete Event Scheduler berbasis Binary Min-Heap.
 *
 * Kompleksitas Waktu:
 * - Schedule (Insert): O(log n)
 * - Poll (Extract-Min): O(log n)
 * - Peek: O(1)
 * Kompleksitas Ruang: O(n) di mana n adalah jumlah peristiwa antrean aktif.
 *
 * Seluruh perbandingan dan waktu menggunakan aritmetika bilangan bulat murni (detik virtual, prioritas ASC, seq ASC).
 */
class DiscreteEventScheduler
{
    /**
     * @var list<DiscreteEvent>
     */
    protected array $heap = [];

    protected int $globalSeq = 0;

    protected int $currentTime = 0;

    /**
     * @var list<array{time: int, priority: int, name: string, seq: int}>
     */
    protected array $executionLog = [];

    /**
     * Menjadwalkan peristiwa baru ke dalam min-heap antrean prioritas.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws PastEventScheduledException jika waktu yang dijadwalkan lebih kecil dari waktu virtual saat ini.
     */
    public function schedule(int $time, int $priority, string $name, array $payload = []): DiscreteEvent
    {
        if ($time < $this->currentTime) {
            throw new PastEventScheduledException($time, $this->currentTime, $name);
        }

        $this->globalSeq++;
        $event = new DiscreteEvent($time, $priority, $name, $this->globalSeq, $payload);

        $this->heap[] = $event;
        $this->heapifyUp(count($this->heap) - 1);

        return $event;
    }

    /**
     * Mengambil dan memproses peristiwa dengan prioritas tertinggi berikutnya (Extract-Min).
     */
    public function poll(): ?DiscreteEvent
    {
        if (empty($this->heap)) {
            return null;
        }

        $min = $this->heap[0];
        $lastIndex = count($this->heap) - 1;

        if ($lastIndex === 0) {
            $this->heap = [];
        } else {
            $this->heap[0] = array_pop($this->heap);
            $this->heapifyDown(0);
        }

        $this->currentTime = $min->time;
        $this->executionLog[] = $min->toArray();

        return $min;
    }

    /**
     * Melihat peristiwa teratas tanpa mengeluarkannya dari antrean.
     */
    public function peek(): ?DiscreteEvent
    {
        return $this->heap[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return empty($this->heap);
    }

    public function count(): int
    {
        return count($this->heap);
    }

    public function currentTime(): int
    {
        return $this->currentTime;
    }

    public function setCurrentTime(int $time): void
    {
        $this->currentTime = $time;
    }

    /**
     * Memulihkan properti min-heap ke atas setelah penambahan elemen.
     */
    protected function heapifyUp(int $index): void
    {
        while ($index > 0) {
            $parentIndex = ($index - 1) >> 1;
            if ($this->heap[$index]->compareTo($this->heap[$parentIndex]) < 0) {
                $temp = $this->heap[$index];
                $this->heap[$index] = $this->heap[$parentIndex];
                $this->heap[$parentIndex] = $temp;
                $index = $parentIndex;
            } else {
                break;
            }
        }
    }

    /**
     * Memulihkan properti min-heap ke bawah setelah penghapusan elemen akar.
     */
    protected function heapifyDown(int $index): void
    {
        $size = count($this->heap);
        while (true) {
            $left = ($index << 1) + 1;
            $right = ($index << 1) + 2;
            $smallest = $index;

            if ($left < $size && $this->heap[$left]->compareTo($this->heap[$smallest]) < 0) {
                $smallest = $left;
            }

            if ($right < $size && $this->heap[$right]->compareTo($this->heap[$smallest]) < 0) {
                $smallest = $right;
            }

            if ($smallest !== $index) {
                $temp = $this->heap[$index];
                $this->heap[$index] = $this->heap[$smallest];
                $this->heap[$smallest] = $temp;
                $index = $smallest;
            } else {
                break;
            }
        }
    }

    /**
     * Menghasilkan sertifikat kriptografis untuk audit integritas eksekusi algoritma A1.
     *
     * @return array{
     *     algorithm: string,
     *     total_events: int,
     *     final_time: int,
     *     log: list<array{time: int, priority: int, name: string, seq: int}>,
     *     sha256: string
     * }
     */
    public function generateCertificate(): array
    {
        $canonicalData = [
            'algorithm' => 'A1_DiscreteEventScheduler',
            'final_time' => $this->currentTime,
            'log' => $this->executionLog,
            'total_events' => count($this->executionLog),
        ];

        $json = (string) json_encode($canonicalData, JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $json);

        return array_merge($canonicalData, ['sha256' => $hash]);
    }

    /**
     * Memverifikasi validitas sertifikat eksekusi.
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
            throw new InvalidAlgorithmCertificateException('Sertifikat A1 gagal diverifikasi: hash integritas tidak cocok.');
        }

        return true;
    }
}
