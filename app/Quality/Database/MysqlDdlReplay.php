<?php

declare(strict_types=1);

namespace App\Quality\Database;

use Closure;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\MySqlConnection;
use PDO;
use Throwable;

/**
 * Compiles every migration to the SQL that MySQL 8 would receive, without a MySQL server
 * (PROGRESS R0.3.b, KNOWLEDGE K-33). Migrations run in Laravel's pretend mode on a MySQL
 * connection whose PDO never executes anything; `Schema::hasTable()` is answered from the tables
 * the replayed DDL has created so far, so guarded renames behave as on a fresh database.
 */
final class MysqlDdlReplay
{
    public const CONNECTION = 'mysql_ddl_replay';

    /**
     * @param  list<string>  $paths  migration directories to replay
     * @param  bool  $withRegisteredPaths  also replay directories registered by module service providers
     * @return list<array{migration: string, path: string, sql: list<string>, error: ?string}>
     */
    public static function run(Migrator $migrator, array $paths, string $root, bool $withRegisteredPaths = true): array
    {
        $connection = self::connection();
        $directories = $withRegisteredPaths ? [...$paths, ...$migrator->paths()] : $paths;
        $files = $migrator->getMigrationFiles(array_values(array_unique($directories)));
        $resolve = Closure::bind(fn (string $path): object => $this->resolvePath($path), $migrator, Migrator::class);
        $database = app('db');
        $previousDefault = $database->getDefaultConnection();
        $result = [];

        $database->setDefaultConnection(self::CONNECTION);

        try {
            foreach ($files as $name => $path) {
                $entry = ['migration' => $name, 'path' => ltrim(str_replace($root, '', $path), '/'), 'sql' => [], 'error' => null];

                try {
                    $migration = $resolve($path);
                    $queries = $connection->pretend(function () use ($migration): void {
                        $migration->up();
                    });
                    $entry['sql'] = array_values(array_column($queries, 'query'));
                } catch (Throwable $exception) {
                    $entry['error'] = $exception::class.': '.$exception->getMessage();
                }

                $result[] = $entry;
            }
        } finally {
            $database->setDefaultConnection($previousDefault);
            $database->purge(self::CONNECTION);
        }

        return $result;
    }

    private static function connection(): MySqlConnection
    {
        config(['database.connections.'.self::CONNECTION => [
            'driver' => self::CONNECTION,
            'name' => self::CONNECTION,
            'database' => 'replay',
            'prefix' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
        ]]);

        app('db')->extend(self::CONNECTION, fn (array $config): MySqlConnection => new class(new PDO('sqlite::memory:'), 'replay', '', $config) extends MySqlConnection
        {
            /** @var array<string, true> */
            private array $tables = [];

            public function statement($query, $bindings = []): bool
            {
                if (preg_match('/^create table `([^`]+)`/i', $query, $match) === 1) {
                    $this->tables[$match[1]] = true;
                } elseif (preg_match('/^rename table `([^`]+)` to `([^`]+)`/i', $query, $match) === 1) {
                    unset($this->tables[$match[1]]);
                    $this->tables[$match[2]] = true;
                } elseif (preg_match('/^drop table (?:if exists )?`([^`]+)`/i', $query, $match) === 1) {
                    unset($this->tables[$match[1]]);
                }

                return parent::statement($query, $bindings);
            }

            public function scalar($query, $bindings = [], $useReadPdo = true): mixed
            {
                if (preg_match("/table_name = '([^']+)'/", $query, $match) === 1) {
                    return isset($this->tables[$match[1]]) ? 1 : 0;
                }

                return parent::scalar($query, $bindings, $useReadPdo);
            }
        });
        app('db')->purge(self::CONNECTION);

        /** @var MySqlConnection */
        return app('db')->connection(self::CONNECTION);
    }
}
