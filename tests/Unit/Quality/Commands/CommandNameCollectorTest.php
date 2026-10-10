<?php

declare(strict_types=1);

use App\Quality\Commands\CommandNameCollector;

it('collects command names from properties, attributes and console closures', function (): void {
    $files = [
        sourceFile('modules/A/Console/Commands/Audit.php', "<?php\nfinal class Audit extends Command { protected \$signature = 'api:audit {--fix : Perbaiki}'; }"),
        sourceFile('modules/B/Console/Commands/Report.php', "<?php\nfinal class Report extends \\Illuminate\\Console\\Command { protected string \$name = 'b:report'; }"),
        sourceFile('app/Console/Commands/Scan.php', "<?php\n#[AsCommand(name: 'arch:scan')]\nfinal class Scan extends Command {}"),
        sourceFile('app/Console/Commands/Gate.php', "<?php\n#[Signature('gate:run\n {--fast}')]\nfinal class Gate extends Command {}"),
        sourceFile('routes/console.php', "<?php\nArtisan::command('inspire', fn () => null);"),
    ];

    $names = array_column(CommandNameCollector::collect($files), 'name');

    expect($names)->toBe(['api:audit', 'b:report', 'arch:scan', 'gate:run', 'inspire']);
});

it('reports a command name declared by two classes with their locations', function (): void {
    $files = [
        sourceFile('modules/Integration/Console/Commands/AuditIntegrationCommand.php', "<?php\nfinal class AuditIntegrationCommand extends Command\n{\n    protected \$signature = 'api:audit';\n}"),
        sourceFile('modules/Integration/Console/Commands/ApiAuditCommand.php', "<?php\nfinal class ApiAuditCommand extends Command\n{\n    protected \$signature = 'api:audit';\n}"),
        sourceFile('modules/Mall/Console/Commands/X.php', "<?php\nfinal class X extends Command { protected \$signature = 'mall:audit-billing'; }"),
    ];

    $duplicates = CommandNameCollector::duplicates(CommandNameCollector::collect($files));

    expect($duplicates)->toBe(['api:audit' => [
        'modules/Integration/Console/Commands/AuditIntegrationCommand.php:4',
        'modules/Integration/Console/Commands/ApiAuditCommand.php:4',
    ]]);
});

it('ignores $name and $signature outside command classes', function (): void {
    $files = [
        sourceFile('modules/A/Domain/Models/Brand.php', "<?php\nfinal class Brand extends Model { protected \$name = 'brand'; }"),
        sourceFile('modules/A/Application/Services/Signer.php', "<?php\nfinal class Signer { public function sign(): void { \$signature = 'x'; } }"),
    ];

    expect(CommandNameCollector::collect($files))->toBe([]);
});
