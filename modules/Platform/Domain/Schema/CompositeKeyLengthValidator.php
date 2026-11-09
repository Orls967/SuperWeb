<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class CompositeKeyLengthValidator
{
    private const MYSQL_MAX_KEY_LENGTH_BYTES = 3072; // InnoDB utf8mb4 max key prefix length

    /**
     * Estimated bytes per column type in utf8mb4 encoding (4 bytes per character).
     *
     * @var array<string, int>
     */
    private const TYPE_BYTES = [
        'biginteger' => 8,
        'integer' => 4,
        'smallinteger' => 2,
        'tinyinteger' => 1,
        'timestamp' => 4,
        'datetime' => 5,
        'date' => 3,
        'uuid' => 144, // 36 chars * 4 bytes
    ];

    /**
     * Validates that composite index total byte length does not exceed InnoDB limit.
     *
     * @param  list<array{name: string, type: string, length?: int}>  $columns
     * @return array{is_valid: bool, total_bytes: int, max_bytes: int, error_message: ?string}
     */
    public function validateKeyLength(array $columns): array
    {
        $totalBytes = 0;

        foreach ($columns as $col) {
            $type = strtolower($col['type'] ?? 'varchar');
            $len = (int) ($col['length'] ?? 255);

            if ($type === 'varchar' || $type === 'string') {
                $totalBytes += ($len * 4); // utf8mb4
            } else {
                $totalBytes += (self::TYPE_BYTES[$type] ?? 4);
            }
        }

        $isValid = ($totalBytes <= self::MYSQL_MAX_KEY_LENGTH_BYTES);
        $error = $isValid ? null : "Total panjang index komposit ({$totalBytes} byte) melampaui batas InnoDB (3072 byte).";

        return [
            'is_valid' => $isValid,
            'total_bytes' => $totalBytes,
            'max_bytes' => self::MYSQL_MAX_KEY_LENGTH_BYTES,
            'error_message' => $error,
        ];
    }
}
