<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * GenerativeEngineeringCopilotService (Fase 352)
 *
 * Implements:
 *  - 352.1 Design copilot: component suggestion & engineering change order (ECO) workflow
 *  - 352.2 Code copilot: mandatory human review & CI passing, zero direct production writes
 *  - 352.4 Tests: Design passes validation before ECO; code cannot merge without review+CI; ai:audit clean
 *  - 352.5 Edge case: Components failing Freedom to Operate (FTO) IP review are rejected until legal clearance
 *  - 352.6 Risk: Unverified AI code injected into production strictly blocked
 */
class GenerativeEngineeringCopilotService
{
    /**
     * Process design copilot suggestion and adopt into ECO workflow with FTO IP validation (352.1, 352.4, 352.5 Edge Case).
     */
    public function adoptDesignEco(
        string $ecoCode,
        string $componentName,
        bool $passedFto,
        bool $engineerValidated
    ): object {
        $eCode = strtoupper($ecoCode);

        // Edge case 352.5: Components failing FTO are rejected
        if (! $passedFto) {
            throw new InvalidArgumentException('Intellectual property violation: Component failed Freedom-To-Operate (FTO) IP review and cannot be adopted into ECO (352.5).');
        }

        // Core gate 352.4: Must be reviewed and validated by engineer before ECO adoption
        if (! $engineerValidated) {
            throw new InvalidArgumentException('Engineering governance breach: Design suggestion must be validated by licensed engineer before ECO adoption (352.4).');
        }

        $id = DB::table('copilot_engineering_design_ecos')->insertGetId([
            'eco_code' => $eCode,
            'component_name' => $componentName,
            'passed_fto_ip_review' => true,
            'engineer_reviewed_and_validated' => true,
            'eco_workflow_adopted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('copilot_engineering_design_ecos')->find($id);
    }

    /**
     * Merge copilot code proposal with human approval and CI gates, prohibiting direct write (352.2 & 352.4).
     */
    public function mergeCodeProposal(
        string $proposalCode,
        string $repoName,
        bool $humanApproved,
        bool $ciPassed,
        bool $directWriteAttempt = false
    ): object {
        $pCode = strtoupper($proposalCode);

        // Core gate 352.2: Zero direct production writes allowed
        if ($directWriteAttempt) {
            throw new InvalidArgumentException('Code safety violation: Copilot direct production writes are strictly prohibited (352.2).');
        }

        // Core gate 352.4: Code cannot merge without human approval + CI pass
        if (! $humanApproved || ! $ciPassed) {
            throw new InvalidArgumentException('CI/CD governance breach: Code proposal cannot merge without human approval and passing CI tests (352.4).');
        }

        $id = DB::table('copilot_code_generation_proposals')->insertGetId([
            'proposal_code' => $pCode,
            'repository_name' => $repoName,
            'human_approved' => true,
            'ci_tests_passed' => true,
            'direct_production_write_attempted' => false,
            'merged_to_production' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('copilot_code_generation_proposals')->find($id);
    }

    /**
     * AI Engineering Copilot Audit (`ai:audit`) (352.4, 352.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Adopted ECOs that failed FTO
        $infringingEcos = DB::table('copilot_engineering_design_ecos')
            ->where('eco_workflow_adopted', true)
            ->where('passed_fto_ip_review', false)
            ->count();

        // Discrepancy 2: Code merged without human approval or failing CI
        $unvettedCodeMerges = DB::table('copilot_code_generation_proposals')
            ->where('merged_to_production', true)
            ->where(function ($query) {
                $query->where('human_approved', false)
                    ->orWhere('ci_tests_passed', false);
            })
            ->count();

        $discrepancies = $infringingEcos + $unvettedCodeMerges;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_ecos' => DB::table('copilot_engineering_design_ecos')->count(),
            'total_proposals' => DB::table('copilot_code_generation_proposals')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
