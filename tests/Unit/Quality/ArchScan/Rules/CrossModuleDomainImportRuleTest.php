<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\CrossModuleDomainImportRule;

it('reports an import of another module domain class', function (): void {
    $code = <<<'PHP'
        <?php
        namespace Modules\Hospital\Application\Services;
        use Modules\Banking\Domain\Models\LedgerAccount;
        PHP;

    $signatures = ruleSignatures(new CrossModuleDomainImportRule, 'modules/Hospital/Application/Services/X.php', $code);

    expect($signatures)->toBe(['use Modules\Banking\Domain\Models\LedgerAccount']);
});

it('reports every class of a group import and fully qualified references in code', function (): void {
    $code = <<<'PHP'
        <?php
        namespace Modules\Hotel\Application\Services;
        use Modules\Mall\Domain\{Models\Tenant, Enums\LeaseStatus as Status};
        final class X { public function y(): string { return \Modules\Resto\Domain\Models\Order::class; } }
        PHP;

    $signatures = ruleSignatures(new CrossModuleDomainImportRule, 'modules/Hotel/Application/Services/X.php', $code);

    expect($signatures)->toBe([
        'use Modules\Mall\Domain\Models\Tenant',
        'use Modules\Mall\Domain\Enums\LeaseStatus',
        'ref Modules\Resto\Domain\Models\Order',
    ]);
});

it('does not report own-module domain, contracts, application layers, closures, or the shared kernel', function (): void {
    $code = <<<'PHP'
        <?php
        namespace Modules\Hospital\Application\Services;
        use Modules\Hospital\Domain\Models\Admission;
        use Modules\Banking\Contracts\Ledger;
        use Modules\Banking\Application\DTOs\PostingDTO;
        use Modules\Banking\Domain\Kernel\AccountKind;
        $callback = function () use ($ledger) { return $ledger; };
        PHP;

    $signatures = ruleSignatures(new CrossModuleDomainImportRule, 'modules/Hospital/Application/Services/X.php', $code);

    expect($signatures)->toBe([]);
});

it('ignores files outside modules', function (): void {
    $code = "<?php\nuse Modules\\Mall\\Domain\\Models\\Tenant;";

    $signatures = ruleSignatures(new CrossModuleDomainImportRule, 'app/Http/Controllers/DashboardController.php', $code);

    expect($signatures)->toBe([]);
});
