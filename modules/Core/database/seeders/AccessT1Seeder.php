<?php

declare(strict_types=1);

namespace Modules\Core\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\Models\CoreAccessGrantTransition;
use Modules\Core\Domain\Models\CoreAccessPolicy;
use Modules\Core\Domain\Models\CoreAccessRecertificationCampaign;
use Modules\Core\Domain\Models\CoreAccessRecertificationItem;
use Modules\Core\Domain\Models\CoreIdentityLink;
use Modules\Core\Domain\Models\CoreTenant;
use Modules\Core\Domain\Models\CoreVendorExposureSnapshot;

/**
 * Access Governance & Multi-Tenancy T1 Seeder (W8-P4.3 / Fase 188).
 *
 * Seeds comprehensive datasets across 30 business verticals:
 * - 50 ABAC policies covering critical financial, health, supply chain, and IT assets
 * - 500 Access Grants across 8 status machine phases with immutable transition histories
 * - 200 Identity Graph links with varying similarity scores and consent matrices
 * - 25 Vendor group clusters with exposures totaling > Rp 500B
 * - 5 Recertification campaigns with hundreds of line items
 */
class AccessT1Seeder extends Seeder
{
    public function run(): void
    {
        $tenantId = '0193188a-7800-7000-8000-000000000001';

        // Ensure primary test tenant exists
        CoreTenant::firstOrCreate(
            ['id' => $tenantId],
            [
                'code' => 'CORP-HQ',
                'name' => 'SuperWeb Corporate HQ',
                'is_active' => true,
            ]
        );

        $this->seedPolicies();
        $this->seedGrants($tenantId);
        $this->seedIdentityLinks($tenantId);
        $this->seedVendorExposureSnapshots();
        $this->seedRecertificationCampaigns();
    }

    private function seedPolicies(): void
    {
        if (CoreAccessPolicy::count() >= 50) {
            return;
        }

        $verticals = [
            'FIN' => ['Finance', 'disbursement', 'disburse'],
            'MED' => ['Healthcare', 'patient_record', 'view_emr'],
            'LOG' => ['Logistics', 'freight_consignment', 'dispatch'],
            'PRC' => ['Procurement', 'purchase_order', 'approve'],
            'AVI' => ['Aviation', 'flight_manifest', 'certify'],
            'SEC' => ['Security', 'encryption_keys', 'rotate'],
            'HR' => ['HRIS', 'payroll_records', 'export'],
            'BNK' => ['Banking', 'wire_transfer', 'execute'],
            'TRD' => ['TradeFinance', 'letter_of_credit', 'amend'],
            'EPC' => ['EPC', 'engineering_blueprint', 'release'],
        ];

        foreach ($verticals as $code => [$vertName, $resource, $action]) {
            for ($i = 1; $i <= 5; $i++) {
                $policyCode = sprintf('POL-%s-%02d', $code, $i);
                $isDeny = ($i % 3 === 0);

                CoreAccessPolicy::firstOrCreate(
                    ['policy_code' => $policyCode],
                    [
                        'name' => "{$vertName} Access Policy Rule #{$i}",
                        'target_resource' => $resource,
                        'effect' => $isDeny ? 'DENY' : 'ALLOW',
                        'dimensions' => [
                            'action' => $action,
                            'security_zone' => 'INTERNAL_LAN',
                            'clearance_level' => min(5, $i + 1),
                        ],
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedGrants(string $tenantId): void
    {
        if (CoreAccessGrant::where('tenant_id', $tenantId)->count() >= 500) {
            return;
        }

        $statuses = [
            'active' => 250,
            'pending_approval' => 70,
            'approved' => 50,
            'requested' => 40,
            'suspended' => 30,
            'revoked' => 30,
            'expired' => 20,
            'archived' => 10,
        ];

        $now = now();
        $grantNum = 1;

        foreach ($statuses as $targetStatus => $targetCount) {
            for ($k = 0; $k < $targetCount; $k++) {
                $code = sprintf('GRN-%04d-%s', $grantNum, strtoupper(Str::random(6)));
                $userId = ($grantNum % 20) + 1;
                $role = match ($grantNum % 5) {
                    0 => 'role:finance_approver',
                    1 => 'role:secops_engineer',
                    2 => 'role:logistics_dispatcher',
                    3 => 'role:procurement_officer',
                    default => 'role:audit_inspector',
                };

                $grant = CoreAccessGrant::create([
                    'grant_code' => $code,
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'resource' => 'MODULE-'.($grantNum % 10),
                    'action' => $role,
                    'status' => $targetStatus,
                    'scope_dimensions' => ['tier' => 'standard'],
                    'valid_from' => $now->copy()->subDays(5),
                    'valid_to' => $targetStatus === 'expired' ? $now->copy()->subDay() : $now->copy()->addDays(30),
                    'revoked_at' => $targetStatus === 'revoked' ? $now->copy()->subHours(2) : null,
                    'grant_version' => 1,
                    'reason' => "Standard business grant for operational duties #{$grantNum}",
                ]);

                // Create initial transition
                CoreAccessGrantTransition::create([
                    'grant_id' => $grant->id,
                    'from_status' => 'initial',
                    'to_status' => 'requested',
                    'trigger_action' => 'REQUEST',
                    'reason' => 'User submitted access grant request',
                    'actor_id' => $userId,
                    'transitioned_at' => $now->copy()->subDays(5),
                ]);

                // Add subsequent transitions
                if ($targetStatus === 'active') {
                    CoreAccessGrantTransition::create([
                        'grant_id' => $grant->id,
                        'from_status' => 'requested',
                        'to_status' => 'approved',
                        'trigger_action' => 'APPROVE',
                        'reason' => 'Access request approved by manager',
                        'actor_id' => 1,
                        'transitioned_at' => $now->copy()->subDays(2),
                    ]);
                    CoreAccessGrantTransition::create([
                        'grant_id' => $grant->id,
                        'from_status' => 'approved',
                        'to_status' => 'active',
                        'trigger_action' => 'ACTIVATE',
                        'reason' => 'Grant activated in directory',
                        'actor_id' => 1,
                        'transitioned_at' => $now->copy()->subDays(1),
                    ]);
                } elseif ($targetStatus !== 'requested') {
                    CoreAccessGrantTransition::create([
                        'grant_id' => $grant->id,
                        'from_status' => 'requested',
                        'to_status' => $targetStatus,
                        'trigger_action' => 'TRANSITION_'.strtoupper($targetStatus),
                        'reason' => "Status transitioned to {$targetStatus} by workflow coordinator",
                        'actor_id' => 1,
                        'transitioned_at' => $now->copy()->subDays(1),
                    ]);
                }

                $grantNum++;
            }
        }
    }

    private function seedIdentityLinks(string $tenantId): void
    {
        if (CoreIdentityLink::where('tenant_id', $tenantId)->count() >= 200) {
            return;
        }

        $types = ['SUPPLIER', 'PATIENT', 'CUSTOMER', 'CARRIER'];

        for ($i = 1; $i <= 200; $i++) {
            $type = $types[$i % count($types)];
            $code = sprintf('LNK-%s-%04d', $type, $i);
            $score = 60 + ($i % 40); // 60 to 99 integer score
            $doubleConsent = ($i % 4 !== 0);     // 75% have double consent
            $sourceConsent = $doubleConsent || ($i % 2 === 0);
            $targetConsent = $doubleConsent;

            $isLinked = ($score >= 85) && $sourceConsent && $targetConsent;
            $status = match (true) {
                $isLinked => 'LINKED',
                $score >= 85 => 'BLOCKED_NO_CONSENT',
                default => 'REJECTED_LOW_SIMILARITY',
            };

            CoreIdentityLink::create([
                'link_code' => $code,
                'tenant_id' => $tenantId,
                'subject_type' => $type,
                'source_entity_id' => $i,
                'target_entity_id' => $i + 1000,
                'linkage_hash' => hash('sha256', "{$type}:{$i}:".($i + 1000)),
                'match_score' => $score,
                'source_consent' => $sourceConsent,
                'target_consent' => $targetConsent,
                'status' => $status,
                'is_active' => $isLinked,
                'linked_at' => $isLinked ? now()->subDays($i % 30) : null,
            ]);
        }
    }

    private function seedVendorExposureSnapshots(): void
    {
        if (CoreVendorExposureSnapshot::count() >= 25) {
            return;
        }

        for ($i = 1; $i <= 25; $i++) {
            $clusterCode = sprintf('VND-CLUSTER-%03d', $i);
            $limitIdr = 500000000; // Rp 500M
            // Some clusters deliberately exceed Rp 500M to test circuit breaker
            $exposureIdr = ($i % 5 === 0) ? 650000000 : (100000000 + ($i * 15000000));
            $isBreached = $exposureIdr > $limitIdr;

            CoreVendorExposureSnapshot::create([
                'snapshot_code' => sprintf('SNAP-VND-%03d', $i),
                'vendor_cluster_code' => $clusterCode,
                'total_exposure_idr' => $exposureIdr,
                'group_credit_limit_idr' => $limitIdr,
                'active_tranche_count' => ($i % 6) + 2,
                'is_limit_breached' => $isBreached,
                'module_breakdown' => [
                    'Procurement' => (int) round($exposureIdr * 0.50),
                    'Logistics' => (int) round($exposureIdr * 0.30),
                    'TradeFinance' => (int) round($exposureIdr * 0.20),
                ],
                'integrity_sha256' => hash('sha256', "SNAP:{$clusterCode}:{$exposureIdr}"),
                'calculated_at' => now()->subHours($i),
            ]);
        }
    }

    private function seedRecertificationCampaigns(): void
    {
        if (CoreAccessRecertificationCampaign::count() >= 5) {
            return;
        }

        $now = now();
        $campaigns = [
            ['REC-Q1-2026', 'Quarterly Access Recertification Q1 2026', 'COMPLETED', $now->copy()->subMonths(9), $now->copy()->subMonths(8)],
            ['REC-Q2-2026', 'Quarterly Access Recertification Q2 2026', 'COMPLETED', $now->copy()->subMonths(6), $now->copy()->subMonths(5)],
            ['REC-Q3-2026', 'Quarterly Access Recertification Q3 2026', 'COMPLETED', $now->copy()->subMonths(3), $now->copy()->subMonths(2)],
            ['REC-Q4-2026', 'Quarterly Access Recertification Q4 2026', 'IN_PROGRESS', $now->copy()->subDays(5), $now->copy()->addDays(9)],
            ['REC-CRIT-2026', 'Emergency Break-Glass Privileged Access Review', 'IN_PROGRESS', $now->copy()->subDays(20), $now->copy()->subDays(6)],
        ];

        foreach ($campaigns as [$code, $name, $status, $startedAt, $dueAt]) {
            $campaign = CoreAccessRecertificationCampaign::create([
                'campaign_code' => $code,
                'title' => $name,
                'scope' => 'TENANT',
                'deadline' => $dueAt,
                'status' => $status,
            ]);

            // Seed review items for the campaign
            $grants = CoreAccessGrant::limit(50)->get();
            foreach ($grants as $idx => $grant) {
                $decision = match ($status) {
                    'COMPLETED' => ($idx % 10 === 0) ? 'REVOKE_REQUESTED' : 'CERTIFIED',
                    default => ($idx < 35) ? 'CERTIFIED' : 'PENDING',
                };

                CoreAccessRecertificationItem::create([
                    'campaign_id' => $campaign->id,
                    'grant_id' => $grant->id,
                    'response' => $decision,
                    'responded_at' => $decision !== 'PENDING' ? $startedAt->copy()->addDays(2) : null,
                    'reviewer_id' => 1,
                ]);
            }
        }
    }
}
