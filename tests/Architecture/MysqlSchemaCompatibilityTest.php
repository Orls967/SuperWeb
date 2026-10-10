<?php

declare(strict_types=1);

use App\Quality\Database\MysqlDdlReplay;
use App\Quality\Database\MysqlSchemaChecker;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/*
| PROGRESS R0.3.b (KNOWLEDGE K-33): every migration compiles to MySQL 8.4 DDL that MySQL accepts.
| The migrations are replayed with Laravel's MySQL grammar in pretend mode, so the check runs in
| the SQLite suite without a MySQL server and reports every problem at once instead of the first
| one the CI MySQL job happens to hit.
*/

uses(TestCase::class);

/**
 * @param  array<string, string>  $migrations  file name => body of up()
 * @return list<array{code: string, path: string, message: string}>
 */
function mysqlFindingsFor(array $migrations): array
{
    $directory = storage_path('framework/testing/mysql-replay-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($directory);

    try {
        foreach ($migrations as $file => $up) {
            file_put_contents("{$directory}/{$file}", <<<PHP
                <?php
                use Illuminate\\Database\\Migrations\\Migration;
                use Illuminate\\Database\\Schema\\Blueprint;
                use Illuminate\\Support\\Facades\\Schema;
                return new class extends Migration {
                    public function up(): void { {$up} }
                };
                PHP);
        }

        return MysqlSchemaChecker::check(MysqlDdlReplay::run(app('migrator'), [$directory], $directory, withRegisteredPaths: false));
    } finally {
        File::deleteDirectory($directory);
    }
}

it('compiles every project migration to MySQL 8.4 DDL that MySQL accepts', function (): void {
    $replay = MysqlDdlReplay::run(app('migrator'), [database_path('migrations')], base_path());
    $findings = array_map(
        static fn (array $finding): string => "[{$finding['code']}] {$finding['path']}: {$finding['message']}",
        MysqlSchemaChecker::check($replay),
    );

    expect(count($replay))->toBeGreaterThan(500)
        ->and($findings)->toBe([]);
});

it('reports each kind of DDL that MySQL 8.4 rejects', function (string $up, string $code): void {
    $codes = array_column(mysqlFindingsFor(['2000_01_01_000001_fixture.php' => $up]), 'code');

    expect($codes)->toContain($code);
})->with([
    'comment as array' => ["Schema::create('t1', function (Blueprint \$t) { \$t->id(); \$t->json('a')->comment(['x']); });", 'compile'],
    'index name over 64' => ["Schema::create('t1', function (Blueprint \$t) { \$t->id(); \$t->string('a', 10)->index(str_repeat('i', 65)); });", '1059'],
    'auto foreign key name over 64' => ["Schema::create('parent_records', function (Blueprint \$t) { \$t->id(); }); Schema::create(str_repeat('c', 40), function (Blueprint \$t) { \$t->id(); \$t->foreignId('parent_record_identifier_id')->constrained('parent_records'); });", '1059'],
    'key over 3072 bytes' => ["Schema::create('t1', function (Blueprint \$t) { \$t->id(); \$t->string('a'); \$t->string('b'); \$t->string('c'); \$t->string('d'); \$t->unique(['a', 'b', 'c', 'd']); });", '1071'],
    'text with literal default' => ["Schema::create('t1', function (Blueprint \$t) { \$t->id(); \$t->text('a')->default('x'); });", '1101'],
    'index on text without prefix' => ["Schema::create('t1', function (Blueprint \$t) { \$t->id(); \$t->text('a')->index(); });", '1170'],
    'foreign key to missing table' => ["Schema::create('t1', function (Blueprint \$t) { \$t->id(); \$t->foreignId('x_id')->constrained('missing_table'); });", '1824'],
    'foreign key type mismatch' => ["Schema::create('p1', function (Blueprint \$t) { \$t->id(); }); Schema::create('t1', function (Blueprint \$t) { \$t->id(); \$t->integer('p1_id'); \$t->foreign('p1_id')->references('id')->on('p1'); });", '3780'],
    'foreign key to unindexed column' => ["Schema::create('p1', function (Blueprint \$t) { \$t->id(); \$t->string('code', 20); }); Schema::create('t1', function (Blueprint \$t) { \$t->id(); \$t->string('p1_code', 20); \$t->foreign('p1_code')->references('code')->on('p1'); });", '1822'],
    'duplicate index name' => ["Schema::create('t1', function (Blueprint \$t) { \$t->id(); \$t->string('a', 10); \$t->string('b', 10); \$t->index('a', 'same_name'); \$t->index('b', 'same_name'); });", '1061'],
]);

it('accepts DDL that MySQL 8.4 accepts, including guarded renames and uuid foreign keys', function (): void {
    $findings = mysqlFindingsFor([
        '2000_01_01_000001_create_cars.php' => "Schema::create('cars', function (Blueprint \$t) { \$t->id(); \$t->string('plate', 20)->unique(); });",
        '2000_01_01_000002_rename_cars.php' => "if (Schema::hasTable('cars') && ! Schema::hasTable('dex_cars')) { Schema::rename('cars', 'dex_cars'); }",
        '2000_01_01_000003_create_parties.php' => "Schema::create('parties', function (Blueprint \$t) { \$t->uuid('id')->primary(); \$t->string('name'); \$t->json('meta')->nullable(); });",
        '2000_01_01_000004_create_vehicles.php' => "Schema::create('vehicles', function (Blueprint \$t) { \$t->id(); \$t->foreignId('car_id')->nullable()->constrained('dex_cars')->nullOnDelete(); \$t->foreignUuid('party_id')->constrained('parties'); \$t->string('code', 64); \$t->text('notes')->nullable(); \$t->index(['code', 'car_id'], 'veh_code_car_idx'); });",
    ]);

    expect($findings)->toBe([]);
});
