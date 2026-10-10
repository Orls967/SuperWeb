<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\ForeignTablePrefixRule;

it('reports tables created, altered or queried outside their owner module', function (): void {
    $code = <<<'PHP'
        <?php
        Schema::create('hsp_admissions', function (Blueprint $table) {});
        Schema::table('bank_ledger_accounts', function (Blueprint $table) {});
        $rows = DB::table('htl_folios')->get();
        PHP;

    $signatures = ruleSignatures(new ForeignTablePrefixRule, 'modules/Integration/database/migrations/2026_01_01_000000_x.php', $code);

    expect($signatures)->toBe([
        "Schema::create('hsp_admissions')",
        "Schema::table('bank_ledger_accounts')",
        "DB::table('htl_folios')",
    ]);
});

it('reports a table whose prefix is not registered and a model mapped to a foreign table', function (): void {
    $code = <<<'PHP'
        <?php
        Schema::create('global_ai_scores', function (Blueprint $table) {});
        final class Folio extends Model { protected $table = 'htl_folios'; }
        PHP;

    $violations = (new ForeignTablePrefixRule)->check(sourceFile('modules/Hospital/Domain/Models/Folio.php', $code), qualityTestRegistry());

    expect(array_map(fn ($violation) => $violation->message, $violations))->toBe([
        "Modul Hospital: prefiks tabel 'global_ai_scores' tidak terdaftar di config/modules.php.",
        "Modul Hospital: tabel 'htl_folios' milik modul Hotel.",
    ]);
});

it('does not report the owner module, its legacy tables, or dynamic table names', function (): void {
    $code = <<<'PHP'
        <?php
        Schema::create('hsp_beds', function (Blueprint $table) {});
        DB::table('hsp_beds')->count();
        DB::table($tableName)->count();
        final class Bed extends Model { protected $table = 'hsp_beds'; }
        PHP;

    $hospital = ruleSignatures(new ForeignTablePrefixRule, 'modules/Hospital/Domain/Models/Bed.php', $code);
    $legacy = ruleSignatures(new ForeignTablePrefixRule, 'modules/AutoServe/Domain/Models/Booking.php', "<?php\nDB::table('bookings')->count();");

    expect($hospital)->toBe([])
        ->and($legacy)->toBe([]);
});
