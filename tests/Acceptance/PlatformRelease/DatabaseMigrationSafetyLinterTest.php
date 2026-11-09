<?php

declare(strict_types=1);

namespace Tests\Acceptance\PlatformRelease;

use Illuminate\Support\Facades\Artisan;
use Modules\Platform\Domain\Algorithms\MigrationSafetyLinter;
use Tests\TestCase;

class DatabaseMigrationSafetyLinterTest extends TestCase
{
    public function test_linter_detects_unsafe_ddl_fixtures(): void
    {
        $linter = new MigrationSafetyLinter;

        // Unsafe 1: NOT NULL without DEFAULT on existing table
        $unsafeCode1 = <<<'PHP'
Schema::table('users', function (Blueprint $table) {
    $table->string('mandatory_tax_code')->nullable(false);
});
PHP;

        $violations1 = $linter->lintString($unsafeCode1, '2026_01_01_unsafe_tax.php');
        $this->assertNotEmpty($violations1);
        $this->assertTrue(collect($violations1)->contains('rule', 'NOT_NULL_WITHOUT_DEFAULT'));

        // Unsafe 2: Direct drop column without deprecation tag
        $unsafeCode2 = <<<'PHP'
Schema::table('orders', function (Blueprint $table) {
    $table->dropColumn('legacy_sku');
});
PHP;

        $violations2 = $linter->lintString($unsafeCode2, '2026_01_01_drop_sku.php');
        $this->assertNotEmpty($violations2);
        $this->assertTrue(collect($violations2)->contains('rule', 'DIRECT_DROP_COLUMN'));

        // Safe code: Add nullable column with index
        $safeCode = <<<'PHP'
Schema::table('orders', function (Blueprint $table) {
    $table->string('tracking_number')->nullable()->index();
});
PHP;

        $violations3 = $linter->lintString($safeCode, '2026_01_01_safe_tracking.php');
        $this->assertEmpty($violations3);
    }

    public function test_artisan_platform_migration_lint_command_executes_cleanly(): void
    {
        $exitCode = Artisan::call('platform:migration-lint', [
            '--path' => 'modules/Platform/database/migrations',
        ]);

        $this->assertSame(0, $exitCode);
    }
}
