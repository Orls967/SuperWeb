<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class InstantDdlCapabilityMatrix
{
    /**
     * Complete lookup matrix of MySQL 8.4 DDL operations, their supported algorithms and lock behaviors.
     *
     * @var array<string, array{algorithm: string, lock: string, notes: string}>
     */
    private const MATRIX = [
        'ADD_COLUMN_END' => ['algorithm' => 'INSTANT', 'lock' => 'NONE', 'notes' => 'Menambahkan kolom di akhir tabel dengan atau tanpa DEFAULT.'],
        'ADD_COLUMN_MIDDLE' => ['algorithm' => 'INSTANT', 'lock' => 'NONE', 'notes' => 'Menambahkan kolom di tengah tabel (didukung sejak MySQL 8.0.29+).'],
        'DROP_COLUMN' => ['algorithm' => 'INSTANT', 'lock' => 'NONE', 'notes' => 'Menghapus kolom secara instan metadata saja.'],
        'SET_DEFAULT' => ['algorithm' => 'INSTANT', 'lock' => 'NONE', 'notes' => 'Mengubah nilai default kolom.'],
        'DROP_DEFAULT' => ['algorithm' => 'INSTANT', 'lock' => 'NONE', 'notes' => 'Menghapus nilai default kolom.'],
        'ADD_INDEX' => ['algorithm' => 'INPLACE', 'lock' => 'NONE', 'notes' => 'Membangun indeks B-Tree sekunder tanpa mengunci DML.'],
        'DROP_INDEX' => ['algorithm' => 'INPLACE', 'lock' => 'NONE', 'notes' => 'Menghapus indeks sekunder tanpa mengunci DML.'],
        'CHANGE_COLUMN_TYPE' => ['algorithm' => 'COPY', 'lock' => 'SHARED', 'notes' => 'Perubahan tipe dasar kolom memicu full table copy.'],
        'ADD_PRIMARY_KEY' => ['algorithm' => 'INPLACE', 'lock' => 'NONE', 'notes' => 'Menambahkan primary key membutuhkan rebuild clustered index.'],
    ];

    public function lookup(string $operation): array
    {
        return self::MATRIX[$operation] ?? [
            'algorithm' => 'COPY',
            'lock' => 'EXCLUSIVE',
            'notes' => 'Operasi tidak dikenal, diasumsikan membutuhkan lock eksklusif.',
        ];
    }
}
