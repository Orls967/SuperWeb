<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\KernelDependsOnBusinessModuleRule;

it('reports kernel modules depending on business modules', function (): void {
    $code = <<<'PHP'
        <?php
        namespace Modules\Shared\Application\Queries;
        use Modules\Mall\Domain\Models\Tenant;
        use Modules\Core\Domain\Models\Vehicle;
        final class GlobalSearchQuery { public function x(): string { return \Modules\Resto\Application\Actions\OpenShift::class; } }
        PHP;

    $signatures = ruleSignatures(new KernelDependsOnBusinessModuleRule, 'modules/Shared/Application/Queries/GlobalSearchQuery.php', $code);

    expect($signatures)->toBe([
        'use Modules\Mall\Domain\Models\Tenant',
        'ref Modules\Resto\Application\Actions\OpenShift',
    ]);
});

it('does not report business modules or shared-kernel namespaces', function (): void {
    $business = "<?php\nuse Modules\\Mall\\Domain\\Models\\Tenant;";
    $kernel = "<?php\nuse Modules\\Banking\\Domain\\Kernel\\AccountKind;";

    expect(ruleSignatures(new KernelDependsOnBusinessModuleRule, 'modules/Resto/Application/Services/X.php', $business))->toBe([])
        ->and(ruleSignatures(new KernelDependsOnBusinessModuleRule, 'modules/Core/Application/Services/X.php', $kernel))->toBe([]);
});
