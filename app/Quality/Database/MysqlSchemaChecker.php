<?php

declare(strict_types=1);

namespace App\Quality\Database;

/**
 * Replays MySQL DDL produced by {@see MysqlDdlReplay} statement by statement and reports what
 * MySQL 8.4 (InnoDB, utf8mb4, strict) would reject (PROGRESS R0.3.b, KNOWLEDGE K-33):
 *
 * - `compile`  the migration does not even compile with the MySQL grammar
 * - `1059`     identifier longer than 64 characters
 * - `1061`     duplicate index name in one table
 * - `1071`     index key longer than 3072 bytes
 * - `1101`     TEXT/BLOB/JSON column with a literal default
 * - `1118`     row of fixed and varchar columns longer than 65535 bytes
 * - `1170`     index on a TEXT/BLOB/JSON column without a prefix length
 * - `1822`     foreign key to a column that is not the first column of an index
 * - `1824`     foreign key to a table that does not exist yet
 * - `3780`     foreign key column type differs from the referenced column
 */
final class MysqlSchemaChecker
{
    private const LOB_TYPES = ['tinytext', 'text', 'mediumtext', 'longtext', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'json', 'geometry'];

    /** @var array<string, array{columns: array<string, string>, indexes: array<string, list<string>>}> */
    private array $tables = [];

    /** @var list<array{code: string, path: string, message: string}> */
    private array $findings = [];

    /**
     * @param  list<array{migration: string, path: string, sql: list<string>, error: ?string}>  $replay
     * @return list<array{code: string, path: string, message: string}>
     */
    public static function check(array $replay): array
    {
        $checker = new self;

        foreach ($replay as $migration) {
            if ($migration['error'] !== null) {
                $checker->report('compile', $migration['path'], $migration['error']);

                continue;
            }

            foreach ($migration['sql'] as $sql) {
                $checker->apply($migration['path'], trim($sql));
            }
        }

        return $checker->findings;
    }

    private function apply(string $path, string $sql): void
    {
        if (preg_match('/^create table `([^`]+)` \((.*)\)(?: default character set.*| engine.*)?$/is', $sql, $match) === 1) {
            $this->createTable($path, $match[1], $match[2]);
        } elseif (preg_match('/^alter table `([^`]+)` add (unique|index|fulltext|spatial) `([^`]+)`\s*\((.*)\)/i', $sql, $match) === 1) {
            $this->addIndex($path, $match[1], $match[3], self::splitList($match[4]));
        } elseif (preg_match('/^alter table `([^`]+)` add primary key (?:`[^`]+` ?)?\s*\((.*)\)/i', $sql, $match) === 1) {
            $this->addIndex($path, $match[1], 'PRIMARY', self::splitList($match[2]));
        } elseif (preg_match('/^alter table `([^`]+)` add constraint `([^`]+)` foreign key \((.*?)\) references `([^`]+)` \((.*?)\)/i', $sql, $match) === 1) {
            $this->addForeignKey($path, $match[1], $match[2], self::names($match[3]), $match[4], self::names($match[5]));
        } elseif (preg_match('/^alter table `([^`]+)` drop (?:index|unique|key) `([^`]+)`/i', $sql, $match) === 1) {
            unset($this->tables[$match[1]]['indexes'][$match[2]]);
        } elseif (preg_match('/^alter table `([^`]+)` drop `([^`]+)`/i', $sql, $match) === 1) {
            unset($this->tables[$match[1]]['columns'][$match[2]]);
        } elseif (preg_match('/^alter table `([^`]+)` (?:add|modify) `([^`]+)` (.+)$/is', $sql, $match) === 1 && isset($this->tables[$match[1]])) {
            $this->addColumn($path, $match[1], $match[2], $match[3]);
        } elseif (preg_match('/^rename table `([^`]+)` to `([^`]+)`/i', $sql, $match) === 1 && isset($this->tables[$match[1]])) {
            $this->tables[$match[2]] = $this->tables[$match[1]];
            unset($this->tables[$match[1]]);
        } elseif (preg_match('/^drop table (?:if exists )?`([^`]+)`/i', $sql, $match) === 1) {
            unset($this->tables[$match[1]]);
        }
    }

    private function createTable(string $path, string $table, string $body): void
    {
        $this->checkIdentifier($path, 'tabel', $table);
        $this->tables[$table] = ['columns' => [], 'indexes' => []];
        $rowBytes = 0;

        foreach (self::splitList($body) as $part) {
            if (preg_match('/^primary key\s*\((.*)\)$/i', $part, $primary) === 1) {
                $this->tables[$table]['indexes']['PRIMARY'] = self::names($primary[1]);

                continue;
            }

            if (preg_match('/^`([^`]+)` (.+)$/s', $part, $column) !== 1) {
                continue;
            }

            $type = $this->addColumn($path, $table, $column[1], $column[2]);
            $rowBytes += self::bytes($type) + (str_starts_with($type, 'varchar') ? 2 : 0);

            if (str_contains(strtolower($column[2]), 'primary key')) {
                $this->tables[$table]['indexes']['PRIMARY'] = [$column[1]];
            }
        }

        if ($rowBytes > 65535) {
            $this->report('1118', $path, "tabel `{$table}`: ukuran baris {$rowBytes} byte > 65535");
        }
    }

    private function addColumn(string $path, string $table, string $column, string $definition): string
    {
        $this->checkIdentifier($path, "kolom `{$table}`.", $column);
        $type = self::columnType($definition);
        $this->tables[$table]['columns'][$column] = $type;

        if (self::isLob($type) && preg_match("/ default (?:'[^']*'|-?\\d)/i", $definition) === 1) {
            $this->report('1101', $path, "kolom {$type} `{$table}`.`{$column}` punya default literal");
        }

        return $type;
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndex(string $path, string $table, string $name, array $columns): void
    {
        $this->checkIdentifier($path, "index `{$table}`.", $name);

        if (! isset($this->tables[$table])) {
            return;
        }

        if ($name !== 'PRIMARY' && isset($this->tables[$table]['indexes'][$name])) {
            $this->report('1061', $path, "nama index ganda `{$name}` di `{$table}`");
        }

        $bytes = 0;
        $names = [];

        foreach ($columns as $column) {
            $columnName = trim(explode('(', $column)[0], '` ');
            $type = $this->tables[$table]['columns'][$columnName] ?? 'varchar(255)';
            $names[] = $columnName;

            if (self::isLob($type) && ! str_contains($column, '(')) {
                $this->report('1170', $path, "index `{$name}` pada kolom {$type} `{$table}`.`{$columnName}` tanpa panjang prefix");
            }

            $bytes += self::bytes($type);
        }

        if ($bytes > 3072) {
            $this->report('1071', $path, "index `{$name}` di `{$table}` = {$bytes} byte > 3072");
        }

        $this->tables[$table]['indexes'][$name] = $names;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $referenced
     */
    private function addForeignKey(string $path, string $table, string $name, array $columns, string $referencedTable, array $referenced): void
    {
        $this->checkIdentifier($path, 'foreign key ', $name);

        if (! isset($this->tables[$referencedTable])) {
            $this->report('1824', $path, "FK `{$name}`: tabel rujukan `{$referencedTable}` belum ada pada urutan migrasi ini");

            return;
        }

        $indexed = false;

        foreach ($this->tables[$referencedTable]['indexes'] as $indexColumns) {
            $indexed = $indexed || array_slice($indexColumns, 0, count($referenced)) === $referenced;
        }

        if (! $indexed) {
            $this->report('1822', $path, "FK `{$name}`: `{$referencedTable}`(".implode(', ', $referenced).') tidak menjadi awal index mana pun');
        }

        foreach ($columns as $position => $column) {
            $own = $this->tables[$table]['columns'][$column] ?? null;
            $other = $this->tables[$referencedTable]['columns'][$referenced[$position] ?? ''] ?? null;

            if ($own !== null && $other !== null && self::foreignKeyType($own) !== self::foreignKeyType($other)) {
                $this->report('3780', $path, "FK `{$name}`: `{$table}`.`{$column}` ({$own}) ≠ `{$referencedTable}`.`{$referenced[$position]}` ({$other})");
            }
        }
    }

    private function checkIdentifier(string $path, string $kind, string $identifier): void
    {
        if (mb_strlen($identifier) > 64) {
            $this->report('1059', $path, "{$kind}`{$identifier}` panjangnya ".mb_strlen($identifier).' karakter > 64');
        }
    }

    private function report(string $code, string $path, string $message): void
    {
        $this->findings[] = ['code' => $code, 'path' => $path, 'message' => $message];
    }

    /**
     * Column type as MySQL compares it for foreign keys: `bigint unsigned`, `char(36)`, `varchar(64)`.
     */
    private static function columnType(string $definition): string
    {
        preg_match('/^([a-z]+(?:\([^)]*\))?)( unsigned)?/i', trim($definition), $match);

        return strtolower(($match[1] ?? 'unknown').($match[2] ?? ''));
    }

    /**
     * MySQL requires the same size and sign for numeric foreign keys, while string lengths may
     * differ (character set and collation are table-wide here), so strings compare as one type.
     */
    private static function foreignKeyType(string $type): string
    {
        return in_array(explode('(', $type)[0], ['char', 'varchar'], true) ? 'string' : $type;
    }

    private static function isLob(string $type): bool
    {
        return in_array(explode('(', $type)[0], self::LOB_TYPES, true);
    }

    /**
     * Bytes a column contributes to an index key or a row (utf8mb4 = 4 bytes per character).
     */
    private static function bytes(string $type): int
    {
        $base = explode('(', str_replace(' unsigned', '', $type))[0];
        $length = preg_match('/\((\d+)/', $type, $match) === 1 ? (int) $match[1] : null;

        return match (true) {
            in_array($base, ['varchar', 'char'], true) => ($length ?? 255) * 4,
            $base === 'bigint', $base === 'double', $base === 'datetime', $base === 'timestamp' => 8,
            $base === 'int', $base === 'float' => 4,
            $base === 'mediumint' => 3,
            $base === 'smallint' => 2,
            $base === 'tinyint' => 1,
            $base === 'date', $base === 'time' => 3,
            $base === 'year' => 1,
            $base === 'decimal' => intdiv($length ?? 10, 2) + 1,
            $base === 'enum' => 2,
            self::isLob($type) => 12,
            default => 8,
        };
    }

    /**
     * Splits a comma-separated SQL list, ignoring commas inside parentheses and quotes.
     *
     * @return list<string>
     */
    private static function splitList(string $list): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quoted = false;
        $length = strlen($list);

        for ($i = 0; $i < $length; $i++) {
            $char = $list[$i];

            if ($char === "'" && ($i === 0 || $list[$i - 1] !== '\\')) {
                $quoted = ! $quoted;
            } elseif (! $quoted && $char === '(') {
                $depth++;
            } elseif (! $quoted && $char === ')') {
                $depth--;
            }

            if ($char === ',' && $depth === 0 && ! $quoted) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private static function names(string $list): array
    {
        return array_map(static fn (string $name): string => trim($name, '` '), explode(',', $list));
    }
}
