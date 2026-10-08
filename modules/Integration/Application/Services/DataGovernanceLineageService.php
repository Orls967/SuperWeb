<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * DataGovernanceLineageService (Fase 241)
 *
 * Implements:
 *  - 241.1 Data governance council & stewardship across 30 lines
 *  - 241.2 Data quality rules registry (completeness, timeliness, validity, consistency, uniqueness) & bad data quarantine
 *  - 241.3 Column-level data lineage graph & impact analysis change gate
 *  - 241.4 Data catalog & business glossary
 *  - 241.6 Edge case: Unassigned steward strictly bans onboarding
 *  - 241.7 Lineage breakage detection on schema refactor
 */
class DataGovernanceLineageService
{
    /**
     * Onboard a data domain with mandatory steward assignment (241.1 & 241.6).
     */
    public function onboardDomain(
        string $domainName,
        ?string $stewardId,
        int $retentionDays = 365,
        string $classification = 'INTERNAL'
    ): object {
        if (empty($stewardId)) {
            throw new InvalidArgumentException('Cannot onboard domain without assigned data steward (241.6).');
        }

        $id = DB::table('data_governance_domains')->insertGetId([
            'domain_name' => strtoupper($domainName),
            'steward_id' => strtoupper($stewardId),
            'retention_days' => $retentionDays,
            'access_classification' => strtoupper($classification),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('data_governance_domains')->find($id);
    }

    /**
     * Evaluate data quality rule, compute DQ score, quarantine bad data, and file owner ticket (241.2).
     */
    public function evaluateDataQuality(
        string $domainName,
        string $datasetName,
        string $ruleType,
        float $dqScorePct,
        int $failedRecordCount = 0
    ): object {
        $ticketCode = null;
        if ($dqScorePct < 90.0 || $failedRecordCount > 0) {
            $ticketCode = 'TKT-DQ-'.strtoupper(Str::random(8));
        }

        $id = DB::table('data_quality_rule_evaluations')->insertGetId([
            'domain_name' => strtoupper($domainName),
            'dataset_name' => $datasetName,
            'rule_type' => strtoupper($ruleType),
            'dq_score_pct' => $dqScorePct,
            'quarantined_record_count' => $failedRecordCount,
            'owner_ticket_code' => $ticketCode,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('data_quality_rule_evaluations')->find($id);
    }

    /**
     * Register column-level data lineage node (241.3).
     */
    public function registerLineage(
        string $sourceColumn,
        string $transformOperation,
        string $targetArtifact,
        string $consumerModule
    ): object {
        $id = DB::table('data_lineage_nodes')->insertGetId([
            'source_column' => $sourceColumn,
            'transform_operation' => strtoupper($transformOperation),
            'target_artifact' => $targetArtifact,
            'consumer_module' => strtoupper($consumerModule),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('data_lineage_nodes')->find($id);
    }

    /**
     * Detect downstream impact for column modification / schema refactor (241.3 & 241.7).
     */
    public function detectLineageImpact(string $sourceColumn): array
    {
        $downstream = DB::table('data_lineage_nodes')
            ->where('source_column', $sourceColumn)
            ->get();

        $affectedConsumers = $downstream->pluck('consumer_module')->unique()->values()->all();
        $affectedArtifacts = $downstream->pluck('target_artifact')->unique()->values()->all();

        return [
            'source_column' => $sourceColumn,
            'affected_node_count' => $downstream->count(),
            'affected_consumers' => $affectedConsumers,
            'affected_artifacts' => $affectedArtifacts,
            'has_downstream_impact' => $downstream->count() > 0,
        ];
    }

    /**
     * Register a business glossary term (241.4 & 241.5).
     */
    public function registerGlossaryTerm(
        string $termKey,
        string $domainName,
        string $definition,
        bool $approvedBySteward = false
    ): object {
        $keyUpper = strtoupper($termKey);

        $existing = DB::table('data_glossary_terms')->where('term_key', $keyUpper)->first();
        if ($existing) {
            throw new InvalidArgumentException("Duplicate glossary term '{$keyUpper}' detected (241.5).");
        }

        $id = DB::table('data_glossary_terms')->insertGetId([
            'term_key' => $keyUpper,
            'domain_name' => strtoupper($domainName),
            'definition' => $definition,
            'approved_by_steward' => $approvedBySteward,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('data_glossary_terms')->find($id);
    }

    /**
     * Steward approval for glossary term (241.5).
     */
    public function approveGlossaryTerm(int $termId, string $stewardId): object
    {
        $term = DB::table('data_glossary_terms')->find($termId);
        if (! $term) {
            throw new InvalidArgumentException("Glossary term #{$termId} not found.");
        }

        DB::table('data_glossary_terms')
            ->where('id', $termId)
            ->update([
                'approved_by_steward' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('data_glossary_terms')->find($termId);
    }

    /**
     * Data Governance Platform Audit (`platform:audit`) (241.5, 241.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Domains lacking assigned data steward
        $unownedDomains = DB::table('data_governance_domains')
            ->whereNull('steward_id')
            ->orWhere('steward_id', '')
            ->count();

        // Discrepancy 2: Data quality failures missing owner ticket
        $unaddressedDqIssues = DB::table('data_quality_rule_evaluations')
            ->where(function ($q) {
                $q->where('dq_score_pct', '<', 90.0)
                  ->orWhere('quarantined_record_count', '>', 0);
            })
            ->whereNull('owner_ticket_code')
            ->count();

        // Discrepancy 3: Unapproved glossary terms
        $unapprovedTerms = DB::table('data_glossary_terms')
            ->where('approved_by_steward', false)
            ->count();

        $discrepancies = $unownedDomains + $unaddressedDqIssues + $unapprovedTerms;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_domains' => DB::table('data_governance_domains')->count(),
            'total_dq_evaluations' => DB::table('data_quality_rule_evaluations')->count(),
            'total_lineage_nodes' => DB::table('data_lineage_nodes')->count(),
            'total_glossary_terms' => DB::table('data_glossary_terms')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
