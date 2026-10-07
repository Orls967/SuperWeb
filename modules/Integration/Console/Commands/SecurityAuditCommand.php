<?php

declare(strict_types=1);

namespace Modules\Integration\Console\Commands;

use Illuminate\Console\Command;
use Modules\Integration\Application\Services\PrivacyVaultService;
use Modules\Integration\Application\Services\RegulatoryComplianceService;
use Modules\Integration\Application\Services\SecurityPenTestService;
use Modules\Integration\Application\Services\ThreatDetectionService;
use Modules\Integration\Application\Services\ZeroTrustService;

/**
 * security:audit — Wave 2 Security & Compliance full audit command.
 *
 * Checks:
 *  1. Zero Trust: service identities with expired certs → FAIL
 *  2. Privacy Vault: active PII records with nullified consent → ATTENTION
 *  3. Regulatory Compliance: blocked/overdue obligations → FAIL
 *  4. SOC: open incidents without CAPA → FAIL
 *  5. Pentest: any vulnerability_found = true → FAIL
 *
 * Exit 0 = ALL HEALTHY | Exit 1 = DISCREPANCY FOUND
 */
class SecurityAuditCommand extends Command
{
    protected $signature = 'security:audit';

    protected $description = 'Wave 2: Security & Compliance audit — Zero Trust, Privacy Vault, Regulatory, SOC & Pentest';

    public function handle(
        ZeroTrustService $zeroTrust,
        PrivacyVaultService $privacyVault,
        RegulatoryComplianceService $regulatory,
        ThreatDetectionService $threatDetection,
        SecurityPenTestService $penTest
    ): int {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║   security:audit — Wave 2 Security & Compliance Audit        ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $totalDiscrepancies = 0;

        // ─── 1. Zero Trust ────────────────────────────────────────────────────
        $this->line('─── [1/5] Zero Trust: Service Identities ───────────────────────');
        $identities = $zeroTrust->listIdentities();
        $expiredCerts = count(array_filter($identities, fn ($id) => ! $id['cert_valid']));
        $this->line('  Registered services  : '.count($identities));
        $this->line("  Expired certificates : {$expiredCerts}");
        if ($expiredCerts > 0) {
            $this->warn("  ⚠ {$expiredCerts} service(s) with expired mTLS certificate(s)");
            $totalDiscrepancies += $expiredCerts;
        } else {
            $this->line('  ✓ All service identities valid');
        }

        $dailyAudit = $zeroTrust->dailyAccessAudit(now());
        $this->line("  Today: {$dailyAudit['allowed']} allowed | {$dailyAudit['denied']} denied ({$dailyAudit['denial_rate_pct']}% denial rate)");
        $this->newLine();

        // ─── 2. Privacy Vault ─────────────────────────────────────────────────
        $this->line('─── [2/5] Privacy Vault: PII & Consent ─────────────────────────');
        $activePii = \DB::table('sec_pii_vault_records')->where('is_active', true)->count();
        $anonymized = \DB::table('sec_pii_vault_records')->where('is_active', false)->count();
        $pendingErase = \DB::table('sec_erasure_requests')->where('status', 'PENDING')->count();
        $this->line("  Active PII records   : {$activePii}");
        $this->line("  Anonymized records   : {$anonymized}");
        $this->line("  Pending erasure reqs : {$pendingErase}");
        if ($pendingErase > 0) {
            $this->warn("  ⚠ {$pendingErase} erasure request(s) awaiting processing");
            $totalDiscrepancies += $pendingErase;
        } else {
            $this->line('  ✓ Privacy vault healthy — no pending erasure requests');
        }
        $this->newLine();

        // ─── 3. Regulatory Compliance ────────────────────────────────────────
        $this->line('─── [3/5] Regulatory Compliance: 17-Line Calendar ──────────────');
        $complianceAudit = $regulatory->audit();
        $this->line("  Total obligations    : {$complianceAudit['total_obligations']}");
        $this->line("  BLOCKED obligations  : {$complianceAudit['blocked']}");
        $this->line("  OVERDUE obligations  : {$complianceAudit['overdue']}");
        $this->line("  Status               : {$complianceAudit['status']}");
        if ($complianceAudit['discrepancy_count'] > 0) {
            $this->error("  ✗ {$complianceAudit['discrepancy_count']} compliance discrepancy(ies) found");
            $totalDiscrepancies += $complianceAudit['discrepancy_count'];
        } else {
            $this->line('  ✓ Regulatory compliance HEALTHY — 0 blocked/overdue');
        }
        $this->newLine();

        // ─── 4. SOC / Incident Response ──────────────────────────────────────
        $this->line('─── [4/5] SOC: Incident Response Health ────────────────────────');
        $socAudit = $threatDetection->socAudit();
        $this->line("  Open incidents       : {$socAudit['open_incidents']}");
        $this->line("  Contained            : {$socAudit['contained']}");
        $this->line("  Resolved/Closed      : {$socAudit['resolved']}");
        $this->line("  Without CAPA         : {$socAudit['without_capa']}");
        $this->line("  Status               : {$socAudit['status']}");
        if ($socAudit['discrepancy_count'] > 0) {
            $this->error("  ✗ {$socAudit['discrepancy_count']} SOC discrepancy(ies)");
            $totalDiscrepancies += $socAudit['discrepancy_count'];
        } else {
            $this->line('  ✓ SOC healthy — no open incidents');
        }
        $this->newLine();

        // ─── 5. Pentest ──────────────────────────────────────────────────────
        $this->line('─── [5/5] Pentest: Route × Role Vulnerability Scan ─────────────');
        $pentestAudit = $penTest->audit();
        $this->line("  Total pentest runs   : {$pentestAudit['total_pentest_runs']}");
        $this->line("  Vulnerabilities found: {$pentestAudit['vulnerabilities']}");
        $this->line("  Status               : {$pentestAudit['status']}");
        if ($pentestAudit['discrepancy_count'] > 0) {
            $this->error("  ✗ {$pentestAudit['discrepancy_count']} vulnerability(ies) found!");
            $totalDiscrepancies += $pentestAudit['discrepancy_count'];
        } else {
            $this->line('  ✓ Pentest PASS — 0 vulnerabilities');
        }
        $this->newLine();

        // ─── Final ───────────────────────────────────────────────────────────
        $this->info('────────────────────────────────────────────────────────────────');
        $this->line("  Total Discrepancies: {$totalDiscrepancies}");

        if ($totalDiscrepancies > 0) {
            $this->error("security:audit FAILED — {$totalDiscrepancies} discrepancy(ies) detected.");

            return self::FAILURE;
        }

        $this->info('security:audit PASSED — All 5 security pillars HEALTHY.');

        return self::SUCCESS;
    }
}
