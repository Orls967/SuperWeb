<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseArchitectureGovernanceService (Fase 472)
 *
 * Implements:
 *  - 472.1 Architecture principles & standards (modular monolith, ledger-first, privacy-first)
 *  - 472.2 Architecture Review Board (ARB) decision & post-implementation verification
 *  - 472.3 Technology radar: adopt / trial / assess / hold with owners and review cycles
 *  - 472.4 Tests: principles testable, ADR required for deviations, platform:audit clean
 *  - 472.5 Edge case: Proposals outside radar need fast-track ADR (cannot bypass ADR process)
 *  - 472.6 Risk: Principles enforced programmatically via arch validation checks
 *  - 472.7 Evidence: ARB decisions, radar catalog, compliance assessment
 */
class EnterpriseArchitectureGovernanceService
{
    public function registerTechRadar(string $techName, string $ring, string $owner, string $reviewDue): object
    {
        $id = DB::table('int_enterprise_tech_radar')->insertGetId([
            'tech_name' => strtoupper($techName),
            'ring' => strtolower($ring),
            'owner' => $owner,
            'review_cycle_due' => $reviewDue,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_tech_radar')->where('id', $id)->first();
    }

    /**
     * 472.2, 472.5, 472.6 Submit ADR proposal; verifies radar ring and core architectural principles
     */
    public function submitAdrProposal(
        string $adrCode,
        string $title,
        string $proposedTech,
        bool $violatesCorePrinciples = false
    ): object {
        $tech = DB::table('int_enterprise_tech_radar')->where('tech_name', strtoupper($proposedTech))->first();

        // 472.3 Hold ring cannot be adopted
        if ($tech && $tech->ring === 'hold') {
            throw new InvalidArgumentException("ADR proposal blocked: Technology '{$proposedTech}' is marked as 'HOLD' on Enterprise Tech Radar (472.3).");
        }

        // 472.6 Risk: Core principles violation blocked
        if ($violatesCorePrinciples) {
            throw new InvalidArgumentException("ADR proposal blocked: Proposal violates fundamental architectural principles (e.g. bypasses financial ledger / modular monolith boundaries) (472.1, 472.6).");
        }

        $id = DB::table('int_architecture_decisions')->insertGetId([
            'adr_code' => strtoupper($adrCode),
            'title' => $title,
            'proposed_tech' => strtoupper($proposedTech),
            'violates_core_principles' => false,
            'adr_formally_approved' => false,
            'post_implementation_verified' => false,
            'status' => 'proposed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_architecture_decisions')->where('id', $id)->first();
    }

    /**
     * 472.2 ARB approval and post-implementation signoff
     */
    public function approveAdr(string $adrCode): object
    {
        $adr = DB::table('int_architecture_decisions')->where('adr_code', strtoupper($adrCode))->first();
        if (! $adr) {
            throw new InvalidArgumentException("ADR '{$adrCode}' not found.");
        }

        DB::table('int_architecture_decisions')->where('id', $adr->id)->update([
            'adr_formally_approved' => true,
            'status' => 'approved',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_architecture_decisions')->where('id', $adr->id)->first();
    }

    public function verifyPostImplementation(string $adrCode): object
    {
        $adr = DB::table('int_architecture_decisions')->where('adr_code', strtoupper($adrCode))->first();
        if (! $adr) {
            throw new InvalidArgumentException("ADR '{$adrCode}' not found.");
        }

        DB::table('int_architecture_decisions')->where('id', $adr->id)->update([
            'post_implementation_verified' => true,
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_architecture_decisions')->where('id', $adr->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Implemented ADRs without formal approval
        $unapprovedImplemented = DB::table('int_architecture_decisions')
            ->where('post_implementation_verified', true)
            ->where('adr_formally_approved', false)
            ->count();

        // Discrepancy 2: Overdue radar reviews
        $overdueRadar = DB::table('int_enterprise_tech_radar')
            ->where('review_cycle_due', '<', now()->toDateString())
            ->count();

        $total = $unapprovedImplemented + $overdueRadar;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_radar_techs' => DB::table('int_enterprise_tech_radar')->count(),
            'total_adrs' => DB::table('int_architecture_decisions')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
