<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\Carbon;
use Modules\Syariah\Domain\Models\SybDpsComplianceReview;
use Modules\Syariah\Domain\Models\SybHalalMerchant;
use Modules\Syariah\Domain\Models\SybNpfClassification;
use Modules\Syariah\Domain\Models\SybOpsIncidentLog;
use Modules\Syariah\Domain\Models\SybRestructuringRequest;
use Modules\Syariah\Domain\Models\SybUniversalWalletTransaction;

final class SyariahOperationsCommandCenterService
{
    /**
     * Mengambil ringkasan metrik dashboard operasional syariah (165.1).
     *
     * @return array<string, mixed>
     */
    public function getDashboardMetrics(): array
    {
        $latestNpf = SybNpfClassification::orderByDesc('id')->first();

        $totalWalletVolume = (int) SybUniversalWalletTransaction::where('status', 'SUCCESS')->sum('gross_amount_minor');
        $totalWalletCount = SybUniversalWalletTransaction::where('status', 'SUCCESS')->count();

        $activeMerchants = SybHalalMerchant::where('status', 'ACTIVE')->where('is_blacklisted', false)->count();
        $expiredMerchants = SybHalalMerchant::where('expires_at', '<', Carbon::now()->toDateString())->count();
        $blacklistedMerchants = SybHalalMerchant::where('is_blacklisted', true)->count();

        $pendingRestructuring = SybRestructuringRequest::whereIn('status', ['REQUESTED', 'DPS_REVIEWED'])->count();
        $executedRestructuring = SybRestructuringRequest::where('status', 'EXECUTED')->count();

        $openIncidents = SybOpsIncidentLog::where('resolution_status', 'OPEN')->count();
        $criticalIncidents = SybOpsIncidentLog::where('severity', 'CRITICAL')->where('resolution_status', 'OPEN')->count();

        return [
            'portfolio' => [
                'latest_evaluation_date' => $latestNpf?->evaluation_date?->toDateString(),
                'period_label' => $latestNpf?->period_label ?? 'N/A',
                'total_contracts' => $latestNpf?->total_contracts ?? 0,
                'total_portfolio_minor' => $latestNpf?->total_portfolio_minor ?? 0,
                'performing_principal_minor' => $latestNpf?->performing_principal_minor ?? 0,
                'non_performing_principal_minor' => $latestNpf?->non_performing_principal_minor ?? 0,
                'npf_gross_bps' => $latestNpf?->npf_gross_bps ?? 0,
                'npf_net_bps' => $latestNpf?->npf_net_bps ?? 0,
                'is_compliant' => $latestNpf?->is_compliant ?? true,
                'total_provision_minor' => $latestNpf?->total_required_provision_minor ?? 0,
            ],
            'wallet_30_lines' => [
                'total_volume_minor' => $totalWalletVolume,
                'total_transactions' => $totalWalletCount,
                'active_halal_merchants' => $activeMerchants,
                'expired_certificates' => $expiredMerchants,
                'blacklisted_merchants' => $blacklistedMerchants,
            ],
            'restructuring' => [
                'pending_approval' => $pendingRestructuring,
                'total_executed' => $executedRestructuring,
            ],
            'compliance' => [
                'open_incidents' => $openIncidents,
                'critical_incidents' => $criticalIncidents,
            ],
        ];
    }

    /**
     * Mencatat temuan insiden operasional syariah.
     */
    public function recordIncident(
        string $severity,
        string $category,
        string $description,
        string $entityReference
    ): SybOpsIncidentLog {
        $ref = 'INC-'.hash('sha256', $entityReference.microtime());
        $ref = substr($ref, 0, 32);

        return SybOpsIncidentLog::create([
            'incident_ref' => $ref,
            'severity' => strtoupper($severity),
            'category' => strtoupper($category),
            'description' => $description,
            'entity_reference' => $entityReference,
            'resolution_status' => 'OPEN',
        ]);
    }

    /**
     * Menyelesaikan insiden operasional dengan catatan perbaikan.
     */
    public function resolveIncident(string $incidentRef, string $notes): SybOpsIncidentLog
    {
        $inc = SybOpsIncidentLog::where('incident_ref', $incidentRef)->firstOrFail();

        $inc->update([
            'resolution_status' => 'RESOLVED',
            'resolution_notes' => $notes,
            'resolved_at' => now(),
        ]);

        return $inc->fresh();
    }

    /**
     * Mencatat hasil reviu berkala Dewan Pengawas Syariah (DPS).
     */
    public function recordDpsReview(
        string $periodLabel,
        string $reviewType,
        string $reviewerName,
        string $verdict,
        string $findings,
        string $recommendations
    ): SybDpsComplianceReview {
        $ref = 'DPS-REV-'.hash('sha256', $periodLabel.':'.$reviewerName.microtime());
        $ref = substr($ref, 0, 32);

        return SybDpsComplianceReview::create([
            'review_ref' => $ref,
            'period_label' => $periodLabel,
            'review_type' => $reviewType,
            'reviewer_name' => $reviewerName,
            'verdict' => strtoupper($verdict),
            'findings' => $findings,
            'recommendations' => $recommendations,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Memverifikasi audit operasional syariah untuk syb:audit.
     *
     * @return array<string, mixed>
     */
    public function auditOperations(): array
    {
        // 1. Cek apakah ada transaksi dompet yang sukses pada merchant haram atau kadaluarsa
        $illegalWalletTxs = SybUniversalWalletTransaction::where('status', 'SUCCESS')
            ->where('is_halal_verified', false)
            ->count();

        // 2. Cek apakah ada jadwal restrukturisasi yang menambah margin melebihi margin lama
        $illegalRestructuring = SybRestructuringRequest::whereColumn('revised_margin_minor', '>', 'original_margin_minor')
            ->count();

        // 3. Cek apakah ada insiden kritis yang belum tertangani
        $criticalIncidents = SybOpsIncidentLog::where('severity', 'CRITICAL')
            ->where('resolution_status', 'OPEN')
            ->count();

        $findings = [];
        if ($illegalWalletTxs > 0) {
            $findings[] = "Ditemukan {$illegalWalletTxs} transaksi dompet sukses pada merchant tidak halal (syb.illegal_wallet_payment)";
        }

        if ($illegalRestructuring > 0) {
            $findings[] = "Ditemukan {$illegalRestructuring} permohonan restrukturisasi dengan margin bertambah / riba (syb.riba_restructuring_margin)";
        }

        if ($criticalIncidents > 0) {
            $findings[] = "Ditemukan {$criticalIncidents} insiden kritis operasional syariah belum terselesaikan (syb.unresolved_critical_incident)";
        }

        return [
            'is_clean' => empty($findings),
            'findings' => $findings,
            'illegal_wallet_tx_count' => $illegalWalletTxs,
            'illegal_restructuring_count' => $illegalRestructuring,
            'critical_incident_count' => $criticalIncidents,
        ];
    }
}
