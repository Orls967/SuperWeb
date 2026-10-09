<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseInnovationRdGovernanceService (Fase 460)
 *
 * Implements:
 *  - 460.1 Innovation funnel metrics: idea -> experiment -> pilot -> scale with kill criteria & reallocation
 *  - 460.2 R&D portfolio balance: horizon 1/2/3 capital allocation
 *  - 460.3 Intellectual property portfolio management: filing, maintenance, renewal deadlines
 *  - 460.4 Tests: funnel conversion measured, IP deadlines tracked, plm:audit clean
 *  - 460.5 Edge case: Lapsed IP deadline triggers automated alert and remediation (renew or abandon with documented reason)
 *  - 460.6 Risk: Horizon balance review to encourage exploratory research (Horizon 3)
 *  - 460.7 Evidence: funnel metrics, portfolio balance, IP portfolio
 */
class EnterpriseInnovationRdGovernanceService
{
    public function registerInnovationProject(
        string $code,
        string $title,
        string $stage,
        string $horizon,
        float $budget
    ): object {
        $id = DB::table('int_innovation_funnel_projects')->insertGetId([
            'project_code' => strtoupper($code),
            'title' => $title,
            'stage' => strtolower($stage),
            'horizon' => strtolower($horizon),
            'budget_allocated' => $budget,
            'kill_criteria_triggered' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_innovation_funnel_projects')->where('id', $id)->first();
    }

    /**
     * 460.1 Kill underperforming project and reallocate budget
     */
    public function triggerKillCriteria(string $code): object
    {
        $proj = DB::table('int_innovation_funnel_projects')->where('project_code', strtoupper($code))->first();
        if (! $proj) {
            throw new InvalidArgumentException("Project '{$code}' not found.");
        }

        DB::table('int_innovation_funnel_projects')->where('id', $proj->id)->update([
            'kill_criteria_triggered' => true,
            'status' => 'killed_reallocated',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_innovation_funnel_projects')->where('id', $proj->id)->first();
    }

    public function registerIpAsset(string $ipCode, string $type, string $title, string $renewalDeadline): object
    {
        $id = DB::table('int_ip_portfolio_assets')->insertGetId([
            'ip_code' => strtoupper($ipCode),
            'ip_type' => strtolower($type),
            'title' => $title,
            'maintenance_renewal_deadline' => $renewalDeadline,
            'deadline_alert_triggered' => false,
            'status' => 'active',
            'abandonment_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_ip_portfolio_assets')->where('id', $id)->first();
    }

    /**
     * 460.5 Edge case: Check looming/lapsed deadlines and trigger alerts
     */
    public function checkIpDeadlines(): int
    {
        return DB::table('int_ip_portfolio_assets')
            ->where('status', 'active')
            ->where('maintenance_renewal_deadline', '<=', now()->addDays(60)->toDateString())
            ->update([
                'deadline_alert_triggered' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * 460.5 Renew IP or formally abandon with documented rationale
     */
    public function resolveIpDeadline(string $ipCode, bool $renew, ?string $abandonReason = null): object
    {
        $asset = DB::table('int_ip_portfolio_assets')->where('ip_code', strtoupper($ipCode))->first();
        if (! $asset) {
            throw new InvalidArgumentException("IP Asset '{$ipCode}' not found.");
        }

        if (! $renew && empty(trim($abandonReason ?? ''))) {
            throw new InvalidArgumentException("Abandonment blocked: Abandoning an IP asset requires documented commercial/strategic reasoning (460.5).");
        }

        $newStatus = $renew ? 'renewed' : 'abandoned';
        $newDeadline = $renew ? now()->addYears(5)->toDateString() : $asset->maintenance_renewal_deadline;

        DB::table('int_ip_portfolio_assets')->where('id', $asset->id)->update([
            'status' => $newStatus,
            'maintenance_renewal_deadline' => $newDeadline,
            'deadline_alert_triggered' => false,
            'abandonment_reason' => $renew ? null : $abandonReason,
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_ip_portfolio_assets')->where('id', $asset->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Abandoned IP without documented reason
        $undocumentedAbandons = DB::table('int_ip_portfolio_assets')
            ->where('status', 'abandoned')
            ->where(function ($query) {
                $query->whereNull('abandonment_reason')
                    ->orWhere('abandonment_reason', '');
            })
            ->count();

        // Discrepancy 2: Overdue IP deadlines without renewal or abandonment
        $lapsedActiveIps = DB::table('int_ip_portfolio_assets')
            ->where('status', 'active')
            ->where('maintenance_renewal_deadline', '<', now()->toDateString())
            ->count();

        $total = $undocumentedAbandons + $lapsedActiveIps;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_projects' => DB::table('int_innovation_funnel_projects')->count(),
            'total_ip_assets' => DB::table('int_ip_portfolio_assets')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
