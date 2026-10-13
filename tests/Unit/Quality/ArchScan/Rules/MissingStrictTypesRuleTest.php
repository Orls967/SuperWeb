<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\MissingStrictTypesRule;

it('reports module files without strict types', function (): void {
    $code = "<?php\n\nnamespace Modules\\Hotel\\Domain\\Models;\n\nfinal class Room {}";

    expect(ruleSignatures(new MissingStrictTypesRule, 'modules/Hotel/Domain/Models/Room.php', $code))
        ->toBe(['declare(strict_types=1) tidak ada']);
});

it('accepts strict types after a docblock and skips migrations', function (): void {
    $withDocblock = "<?php\n/** Room aggregate. */\ndeclare(strict_types=1);\n\nnamespace Modules\\Hotel\\Domain\\Models;";
    $migration = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\n\nreturn new class extends Migration {};";

    expect(ruleSignatures(new MissingStrictTypesRule, 'modules/Hotel/Domain/Models/Room.php', $withDocblock))->toBe([])
        ->and(ruleSignatures(new MissingStrictTypesRule, 'modules/Hotel/database/migrations/2026_01_01_000000_x.php', $migration))->toBe([]);
});
