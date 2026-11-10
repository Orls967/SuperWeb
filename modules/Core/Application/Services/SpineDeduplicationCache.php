<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

/**
 * Tembolok Deduplikasi L1 Dalam-Memori (SpineDeduplicationCache)
 *
 * Menerapkan cache LRU (Least Recently Used) in-memory sebagai lapisan perlindungan L1
 * sebelum membebani basis data L2 `core_consumer_inbox` untuk menguji idempotensi pesan.
 */
class SpineDeduplicationCache
{
    /**
     * @var array<string, int>
     */
    protected array $cache = [];

    public function __construct(
        protected int $capacity = 5000,
        protected int $ttlSeconds = 3600
    ) {}

    /**
     * Memeriksa apakah pasangan (consumer_group, message_id) sudah ada di cache dan masih berlaku.
     */
    public function has(string $consumerGroup, string $messageId): bool
    {
        $key = "{$consumerGroup}:{$messageId}";

        if (! isset($this->cache[$key])) {
            return false;
        }

        $expiresAt = $this->cache[$key];
        if (time() > $expiresAt) {
            unset($this->cache[$key]);

            return false;
        }

        // Pindahkan ke akhir array untuk mempertahankan prinsip LRU
        $val = $this->cache[$key];
        unset($this->cache[$key]);
        $this->cache[$key] = $val;

        return true;
    }

    /**
     * Menyimpan tanda pesan yang telah diproses ke dalam cache L1.
     */
    public function put(string $consumerGroup, string $messageId): void
    {
        $key = "{$consumerGroup}:{$messageId}";

        // Jika kapasitas terlampaui, hapus item tertua (FIFO/LRU head)
        if (count($this->cache) >= $this->capacity) {
            array_shift($this->cache);
        }

        $this->cache[$key] = time() + $this->ttlSeconds;
    }

    /**
     * Menghapus rekaman tertentu dari cache (misal saat replay).
     */
    public function forget(string $consumerGroup, string $messageId): void
    {
        $key = "{$consumerGroup}:{$messageId}";
        unset($this->cache[$key]);
    }

    /**
     * Mengosongkan seluruh cache memori.
     */
    public function clear(): void
    {
        $this->cache = [];
    }

    /**
     * Mengambil jumlah entri aktif di cache saat ini.
     */
    public function count(): int
    {
        return count($this->cache);
    }
}
