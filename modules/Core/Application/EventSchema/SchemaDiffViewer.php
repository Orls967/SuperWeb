<?php

declare(strict_types=1);

namespace Modules\Core\Application\EventSchema;

/**
 * Penampil dan Penganalisis Selisih Skema Event (SchemaDiffViewer)
 *
 * Mengidentifikasi perbedaan struktural antara skema lama dan usulan skema baru:
 * - Penambahan properti (properti baru)
 * - Penghapusan properti (breaking change)
 * - Perubahan batasan required (breaking change bila bertambah)
 * - Perubahan tipe data (breaking change bila inkompatibel)
 * - Perluasan atau pembatasan enum
 */
class SchemaDiffViewer
{
    /**
     * Membandingkan dua skema dan mengembalikan laporan perbedaan terstruktur.
     *
     * @param  array<string, mixed>  $oldSchema
     * @param  array<string, mixed>  $newSchema
     * @return array{
     *     is_breaking: bool,
     *     added_properties: array<string, mixed>,
     *     removed_properties: array<string, mixed>,
     *     type_changes: array<string, array{old: mixed, new: mixed}>,
     *     required_changes: array{added: array<string>, removed: array<string>},
     *     enum_changes: array<string, array{added: array<mixed>, removed: array<mixed>}>,
     *     diff_summary: string
     * }
     */
    public function diff(array $oldSchema, array $newSchema): array
    {
        $oldProps = (array) ($oldSchema['properties'] ?? []);
        $newProps = (array) ($newSchema['properties'] ?? []);

        $oldRequired = (array) ($oldSchema['required'] ?? []);
        $newRequired = (array) ($newSchema['required'] ?? []);

        $addedProps = [];
        $removedProps = [];
        $typeChanges = [];
        $enumChanges = [];

        // 1. Periksa properti yang ditambahkan
        foreach ($newProps as $propName => $propDef) {
            if (! array_key_exists($propName, $oldProps)) {
                $addedProps[$propName] = $propDef;
            }
        }

        // 2. Periksa properti yang dihapus dan modifikasi tipe
        foreach ($oldProps as $propName => $propDef) {
            if (! array_key_exists($propName, $newProps)) {
                $removedProps[$propName] = $propDef;

                continue;
            }

            $oldType = is_array($propDef) ? ($propDef['type'] ?? 'any') : 'any';
            $newType = is_array($newProps[$propName]) ? ($newProps[$propName]['type'] ?? 'any') : 'any';

            if ($oldType !== $newType) {
                $typeChanges[$propName] = [
                    'old' => $oldType,
                    'new' => $newType,
                ];
            }

            // Periksa perubahan enum jika ada
            $oldEnum = is_array($propDef) ? ($propDef['enum'] ?? null) : null;
            $newEnum = is_array($newProps[$propName]) ? ($newProps[$propName]['enum'] ?? null) : null;

            if (is_array($oldEnum) && is_array($newEnum)) {
                $addedEnumValues = array_values(array_diff($newEnum, $oldEnum));
                $removedEnumValues = array_values(array_diff($oldEnum, $newEnum));

                if (! empty($addedEnumValues) || ! empty($removedEnumValues)) {
                    $enumChanges[$propName] = [
                        'added' => $addedEnumValues,
                        'removed' => $removedEnumValues,
                    ];
                }
            }
        }

        // 3. Periksa perubahan status required
        $addedRequired = array_values(array_diff($newRequired, $oldRequired));
        $removedRequired = array_values(array_diff($oldRequired, $newRequired));

        // 4. Tentukan apakah perubahan tergolong breaking change
        $isBreaking = ! empty($removedProps)
            || ! empty($addedRequired)
            || ! empty($typeChanges)
            || $this->hasEnumShrinkage($enumChanges);

        $summary = $this->generateDiffSummary(
            $addedProps,
            $removedProps,
            $typeChanges,
            $addedRequired,
            $removedRequired,
            $enumChanges,
            $isBreaking
        );

        return [
            'is_breaking' => $isBreaking,
            'added_properties' => $addedProps,
            'removed_properties' => $removedProps,
            'type_changes' => $typeChanges,
            'required_changes' => [
                'added' => $addedRequired,
                'removed' => $removedRequired,
            ],
            'enum_changes' => $enumChanges,
            'diff_summary' => $summary,
        ];
    }

    /**
     * Memeriksa apakah ada enum yang nilainya dihapus / dipersempit.
     *
     * @param  array<string, array{added: array<mixed>, removed: array<mixed>}>  $enumChanges
     */
    protected function hasEnumShrinkage(array $enumChanges): bool
    {
        foreach ($enumChanges as $change) {
            if (! empty($change['removed'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Menyusun ringkasan teks perbedaan skema yang mudah dibaca manusia.
     *
     * @param  array<string, mixed>  $addedProps
     * @param  array<string, mixed>  $removedProps
     * @param  array<string, array{old: mixed, new: mixed}>  $typeChanges
     * @param  array<string>  $addedRequired
     * @param  array<string>  $removedRequired
     * @param  array<string, array{added: array<mixed>, removed: array<mixed>}>  $enumChanges
     */
    protected function generateDiffSummary(
        array $addedProps,
        array $removedProps,
        array $typeChanges,
        array $addedRequired,
        array $removedRequired,
        array $enumChanges,
        bool $isBreaking
    ): string {
        $lines = [];
        $lines[] = '=== RINGKASAN SELISIH SKEMA EVENT ===';
        $lines[] = 'Status: '.($isBreaking ? '[BREAKING / INKOMPATIBEL]' : '[ADITIF / KOMPATIBEL]');

        if (! empty($addedProps)) {
            $lines[] = 'Properti Ditambahkan ('.count($addedProps).'):';
            foreach (array_keys($addedProps) as $prop) {
                $lines[] = "  + {$prop}";
            }
        }

        if (! empty($removedProps)) {
            $lines[] = 'PERINGATAN: Properti Dihapus ('.count($removedProps).'):';
            foreach (array_keys($removedProps) as $prop) {
                $lines[] = "  - {$prop}";
            }
        }

        if (! empty($addedRequired)) {
            $lines[] = 'PERINGATAN: Required Fields Baru ('.count($addedRequired).'):';
            foreach ($addedRequired as $prop) {
                $lines[] = "  ! {$prop} (wajib)";
            }
        }

        if (! empty($removedRequired)) {
            $lines[] = 'Required Fields Dilonggarkan ('.count($removedRequired).'):';
            foreach ($removedRequired as $prop) {
                $lines[] = "  ~ {$prop} (menjadi opsional)";
            }
        }

        if (! empty($typeChanges)) {
            $lines[] = 'PERINGATAN: Perubahan Tipe Data ('.count($typeChanges).'):';
            foreach ($typeChanges as $prop => $types) {
                $lines[] = "  * {$prop}: {$types['old']} -> {$types['new']}";
            }
        }

        if (! empty($enumChanges)) {
            $lines[] = 'Perubahan Enum ('.count($enumChanges).'):';
            foreach ($enumChanges as $prop => $ch) {
                $addStr = empty($ch['added']) ? '' : ' +['.implode(', ', $ch['added']).']';
                $remStr = empty($ch['removed']) ? '' : ' -['.implode(', ', $ch['removed']).']';
                $lines[] = "  # {$prop}:{$addStr}{$remStr}";
            }
        }

        if (empty($addedProps) && empty($removedProps) && empty($typeChanges) && empty($addedRequired) && empty($removedRequired) && empty($enumChanges)) {
            $lines[] = 'Tidak ditemukan perbedaan struktural (skema identik).';
        }

        return implode("\n", $lines);
    }
}
