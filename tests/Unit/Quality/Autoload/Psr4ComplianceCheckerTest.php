<?php

declare(strict_types=1);

use App\Quality\Autoload\Psr4ComplianceChecker;

it('reports classes whose path differs from their namespace only by letter case', function (): void {
    $checker = new Psr4ComplianceChecker(['Modules\\' => 'modules/', 'App\\' => 'app/']);
    $files = [
        sourceFile('modules/Asset/database/seeders/AssetSeeder.php', "<?php\nnamespace Modules\\Asset\\Database\\Seeders;\nfinal class AssetSeeder {}"),
        sourceFile('modules/Mall/database/seeders/MallSeeder.php', "<?php\nnamespace Modules\\Mall\\database\\seeders;\nfinal class MallSeeder {}"),
        sourceFile('app/Models/User.php', "<?php\nnamespace App\\Models;\nfinal class User {}"),
    ];

    expect($checker->mismatches($files))->toBe([[
        'class' => 'Modules\Asset\Database\Seeders\AssetSeeder',
        'path' => 'modules/Asset/database/seeders/AssetSeeder.php',
        'expected' => 'modules/Asset/Database/Seeders/AssetSeeder.php',
    ]]);
});

it('uses the longest matching prefix and ignores files without a named class', function (): void {
    $checker = new Psr4ComplianceChecker(['Database\\Seeders\\' => 'database/seeders/', 'Database\\' => 'db/']);
    $files = [
        sourceFile('database/seeders/DatabaseSeeder.php', "<?php\nnamespace Database\\Seeders;\nclass DatabaseSeeder {}"),
        sourceFile('database/migrations/2026_01_01_000000_x.php', "<?php\nreturn new class extends Migration { public function up(): void { \$x = Foo::class; } };"),
    ];

    expect($checker->mismatches($files))->toBe([]);
});
