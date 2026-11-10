<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Events;

/**
 * Kalkulator Hash Proyeksi Kanonikal SHA-256 [Fase R8]
 *
 * Mengurutkan kunci array secara rekursif (ksort) sebelum encoding JSON kanonikal,
 * lalu menghasilkan hash SHA-256 sepanjang 64 karakter heksadesimal.
 */
class ProjectionHashCalculator
{
    /**
     * Hitung SHA-256 dari state proyeksi kanonikal.
     *
     * @param  array<string, mixed>  $state
     */
    public function computeHash(array $state): string
    {
        $canonicalJson = $this->canonicalJson($state);

        return hash('sha256', $canonicalJson);
    }

    /**
     * Serialisasi array ke JSON kanonikal deterministik dengan ksort rekursif.
     *
     * @param  array<string, mixed>  $data
     */
    public function canonicalJson(array $data): string
    {
        $sorted = $this->recursiveKsort($data);

        return (string) json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<mixed, mixed>  $array
     * @return array<mixed, mixed>
     */
    private function recursiveKsort(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->recursiveKsort($value);
            }
        }
        ksort($array);

        return $array;
    }
}
