<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Events;

/**
 * Algoritma A6: SchemaAdditiveCompatibilityChecker [Fase R8]
 *
 * Membandingkan dua skema JSON Schema (v1 lama dan v2 baru) untuk memastikan
 * perubahan skema bersifat aditif murni (backward-compatible).
 *
 * Pelanggaran yang dideteksi:
 * - PROPERTY_REMOVED: Properti lama dihapus.
 * - TYPE_CHANGED: Tipe data properti diubah.
 * - REQUIRED_ADDED: Field baru ditambahkan ke daftar required.
 * - ENUM_VALUE_REMOVED: Nilai yang diizinkan pada enum dihapus.
 * - RANGE_NARROWED: Batasan minimum dinaikkan atau maximum diturunkan.
 *
 * Mengembalikan daftar pelanggaran terurut menurut (path ASC, code ASC).
 */
class SchemaAdditiveCompatibilityChecker
{
    public const CODE_PROPERTY_REMOVED = 'PROPERTY_REMOVED';

    public const CODE_TYPE_CHANGED = 'TYPE_CHANGED';

    public const CODE_REQUIRED_ADDED = 'REQUIRED_ADDED';

    public const CODE_ENUM_VALUE_REMOVED = 'ENUM_VALUE_REMOVED';

    public const CODE_RANGE_NARROWED = 'RANGE_NARROWED';

    /**
     * Periksa kompatibilitas backward antara skema lama dan baru.
     *
     * @param  array<string, mixed>  $oldSchema
     * @param  array<string, mixed>  $newSchema
     * @return array<int, array{path: string, code: string}>
     */
    public function checkCompatibility(array $oldSchema, array $newSchema): array
    {
        $violations = [];

        $oldProps = (array) ($oldSchema['properties'] ?? []);
        $newProps = (array) ($newSchema['properties'] ?? []);

        // 1. Periksa penghapusan properti & perubahan atribut per properti
        foreach ($oldProps as $propName => $oldPropDef) {
            $propPath = "/properties/{$propName}";

            if (! array_key_exists($propName, $newProps)) {
                $violations[] = [
                    'path' => $propPath,
                    'code' => self::CODE_PROPERTY_REMOVED,
                ];

                continue;
            }

            $newPropDef = (array) $newProps[$propName];
            $oldPropDef = (array) $oldPropDef;

            // Periksa tipe data
            if (isset($oldPropDef['type'], $newPropDef['type']) && $oldPropDef['type'] !== $newPropDef['type']) {
                $violations[] = [
                    'path' => "{$propPath}/type",
                    'code' => self::CODE_TYPE_CHANGED,
                ];
            }

            // Periksa batasan minimum (RANGE_NARROWED jika minimum baru > minimum lama)
            if (isset($oldPropDef['minimum'], $newPropDef['minimum']) && $newPropDef['minimum'] > $oldPropDef['minimum']) {
                $violations[] = [
                    'path' => "{$propPath}/minimum",
                    'code' => self::CODE_RANGE_NARROWED,
                ];
            }

            // Periksa batasan maximum (RANGE_NARROWED jika maximum baru < maximum lama)
            if (isset($oldPropDef['maximum'], $newPropDef['maximum']) && $newPropDef['maximum'] < $oldPropDef['maximum']) {
                $violations[] = [
                    'path' => "{$propPath}/maximum",
                    'code' => self::CODE_RANGE_NARROWED,
                ];
            }

            // Periksa enum values
            if (isset($oldPropDef['enum']) && is_array($oldPropDef['enum'])) {
                $newEnum = is_array($newPropDef['enum'] ?? null) ? $newPropDef['enum'] : [];
                foreach ($oldPropDef['enum'] as $enumVal) {
                    if (! in_array($enumVal, $newEnum, true)) {
                        $violations[] = [
                            'path' => "{$propPath}/enum/{$enumVal}",
                            'code' => self::CODE_ENUM_VALUE_REMOVED,
                        ];
                    }
                }
            }
        }

        // 2. Periksa penambahan required fields
        $oldRequired = (array) ($oldSchema['required'] ?? []);
        $newRequired = (array) ($newSchema['required'] ?? []);

        foreach ($newRequired as $field) {
            if (! in_array($field, $oldRequired, true)) {
                $violations[] = [
                    'path' => "/required/{$field}",
                    'code' => self::CODE_REQUIRED_ADDED,
                ];
            }
        }

        // 3. Urutkan pelanggaran menurut (path ASC, code ASC)
        usort($violations, function (array $a, array $b): int {
            $cmpPath = strcmp($a['path'], $b['path']);
            if ($cmpPath !== 0) {
                return $cmpPath;
            }

            return strcmp($a['code'], $b['code']);
        });

        return $violations;
    }

    /**
     * Apakah skema baru sepenuhnya kompatibel aditif?
     *
     * @param  array<string, mixed>  $oldSchema
     * @param  array<string, mixed>  $newSchema
     */
    public function isCompatible(array $oldSchema, array $newSchema): bool
    {
        return empty($this->checkCompatibility($oldSchema, $newSchema));
    }
}
