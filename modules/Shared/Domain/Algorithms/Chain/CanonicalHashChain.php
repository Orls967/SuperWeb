<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Chain;

use InvalidArgumentException;
use JsonException;

/**
 * Pustaka rantai hash kanonik (S4).
 *
 * Kompleksitas:
 * - canonicalize: O(K log K) per payload dengan K kunci (sortir rekursif + serialisasi JSON).
 * - link: O(M) di mana M adalah panjang byte payload kanonik (SHA-256).
 * - verify: O(N * M) untuk N baris riwayat.
 */
final class CanonicalHashChain
{
    public const string GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Menghasilkan representasi JSON kanonik:
     * - Kunci terurut alfabetis secara rekursif (ksort).
     * - Semua nilai skalar dinormalisasi menjadi string ("5" == 5).
     * - JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    public function canonicalize(array $payload): string
    {
        $normalized = $this->normalizeArray($payload);

        return json_encode(
            $normalized,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Menghitung hash baris baru dari prev_hash dan payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function link(string $prevHash, array $payload): string
    {
        if (strlen($prevHash) !== 64 || ! ctype_xdigit($prevHash)) {
            throw new InvalidArgumentException("prevHash harus berupa 64 karakter heksadesimal: '{$prevHash}'");
        }

        $canonical = $this->canonicalize($payload);

        return hash('sha256', $prevHash."\n".$canonical);
    }

    /**
     * Memverifikasi keutuhan rantai hash baris per baris.
     * Mengembalikan indeks 0-based baris pertama yang rusak, atau null bila seluruh rantai valid.
     *
     * Format per baris:
     * array{prev_hash: string, payload: array<string, mixed>, hash: string}
     *
     * @param  iterable<array{prev_hash: string, payload: array<string, mixed>, hash: string}>  $rows
     */
    public function verify(iterable $rows): ?int
    {
        $expectedPrev = self::GENESIS_HASH;
        $index = 0;

        foreach ($rows as $row) {
            if (! isset($row['prev_hash'], $row['payload'], $row['hash']) || ! is_array($row['payload'])) {
                return $index;
            }

            if ($row['prev_hash'] !== $expectedPrev) {
                return $index;
            }

            $computed = $this->link($row['prev_hash'], $row['payload']);
            if (! hash_equals($computed, $row['hash'])) {
                return $index;
            }

            $expectedPrev = $row['hash'];
            $index++;
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function normalizeArray(array $data): array
    {
        ksort($data);

        $result = [];
        foreach ($data as $key => $value) {
            $stringKey = (string) $key;
            if (is_array($value)) {
                $result[$stringKey] = $this->normalizeArray($value);
            } elseif (is_bool($value)) {
                $result[$stringKey] = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $result[$stringKey] = '';
            } else {
                $result[$stringKey] = (string) $value;
            }
        }

        return $result;
    }
}
