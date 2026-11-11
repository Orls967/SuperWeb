<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use Modules\Core\Domain\Models\CoreBoundaryViolation;

/**
 * Service to generate concrete refactoring recipes for boundary violations (DR4-1, DR4-4).
 *
 * Provides actionable code snippets, contract templates, and migration checklists
 * to guide developers through the 7-stage state machine from detected to closed.
 */
final class BoundaryRemediationPlanner
{
    public function __construct(
        private readonly ?ContractMigrationOrchestrator $orchestrator = null,
    ) {}

    /**
     * Generate a complete remediation plan for a boundary violation.
     *
     * @param  CoreBoundaryViolation  $violation
     * @return array{
     *     violationId: int,
     *     ruleId: string,
     *     suggestedContractFqn: string,
     *     contractTemplateCode: string,
     *     serviceProviderBindingCode: string,
     *     callSiteRefactoringSnippet: string,
     *     verificationSteps: list<string>
     * }
     */
    public function plan(CoreBoundaryViolation $violation): array
    {
        $client = $violation->client_module;
        $owner = $violation->owner_module;
        $rule = $violation->rule_id;

        // 1. Suggest contract name and FQN
        $entityName = 'Entity';
        if (preg_match('#(?:Models|Domain)\\\\([A-Za-z0-9]+)#', $violation->signature, $m) === 1) {
            $entityName = $m[1];
        }

        $contractName = "{$entityName}Directory";
        $suggestedContractFqn = "Modules\\{$owner}\\Contracts\\{$contractName}";
        $suggestedSnapshotFqn = "Modules\\{$owner}\\Contracts\\{$entityName}Snapshot";

        // 2. Generate PHP Interface contract code template
        $contractCode = <<<PHP
<?php

declare(strict_types=1);

namespace Modules\\{$owner}\\Contracts;

interface {$contractName}
{
    /**
     * Find {$entityName} snapshot by ID.
     */
    public function findById(int \$id): ?{$entityName}Snapshot;

    /**
     * Retrieve active snapshots for a given owner/user.
     *
     * @return list<{$entityName}Snapshot>
     */
    public function activeForOwner(int \$ownerId): array;
}
PHP;

        // 3. Generate Service Provider binding code snippet
        $bindingCode = <<<PHP
// In Modules\\{$owner}\\{$owner}ServiceProvider.php:
\$this->app->singleton(
    \\Modules\\{$owner}\\Contracts\\{$contractName}::class,
    \\Modules\\{$owner}\\Infrastructure\\Repositories\\Eloquent{$contractName}::class
);
PHP;

        // 4. Generate call-site refactoring replacement
        $oldImport = $violation->signature;
        $newImport = "use {$suggestedContractFqn};\nuse {$suggestedSnapshotFqn};";

        $callSiteSnippet = <<<PHP
// BEFORE:
{$oldImport}
\$entity = {$entityName}::find(\$id);

// AFTER:
{$newImport}
\$directory = app({$contractName}::class);
\$snapshot = \$directory->findById(\$id);
PHP;

        // 5. Verification checklist
        $verificationSteps = [
            "1. Buat berkas interface {$suggestedContractFqn} dan {$suggestedSnapshotFqn}.",
            "2. Buat implementasi repositori di Modules/{$owner}/Infrastructure/Repositories/.",
            "3. Daftarkan binding singleton di {$owner}ServiceProvider.",
            "4. Perbarui baris use dan pemanggilan pada {$violation->file_path}:{$violation->line_number}.",
            '5. Jalankan pest tests/ unit modul terkait.',
            '6. Jalankan php artisan arch:scan --check untuk memastikan pelanggaran tereliminasi.',
            '7. Transisikan status via BoundaryViolationStateMachine: migrating -> verified -> closed.',
        ];

        return [
            'violationId' => (int) $violation->id,
            'ruleId' => $rule,
            'suggestedContractFqn' => $suggestedContractFqn,
            'contractTemplateCode' => trim($contractCode),
            'serviceProviderBindingCode' => trim($bindingCode),
            'callSiteRefactoringSnippet' => trim($callSiteSnippet),
            'verificationSteps' => $verificationSteps,
        ];
    }
}

