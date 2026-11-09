<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Platform\Domain\Enums\SandboxStatus;
use Modules\Platform\Domain\Models\MaskRule;
use Modules\Platform\Domain\Models\PlatformAuditRun;
use Modules\Platform\Domain\Models\PostmortemRecord;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\Models\SandboxTransition;
use Modules\Platform\Domain\Models\ServiceSlo;
use Modules\Platform\Domain\Models\SyntheticDataset;

final class PlatformUniverseSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::parse('2026-11-09 12:00:00');

        // Pastikan User simulasi ada
        $users = [
            ['id' => 1, 'name' => 'Radit Pratama', 'email' => 'radit.platform@dutamall.internal'],
            ['id' => 2, 'name' => 'Siti Aisyah', 'email' => 'siti.sre@dutamall.internal'],
            ['id' => 3, 'name' => 'Budi Santoso', 'email' => 'budi.developer@dutamall.internal'],
            ['id' => 4, 'name' => 'Dewi Lestari', 'email' => 'dewi.qa@dutamall.internal'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => bcrypt('password123'),
                ]
            );
        }

        $user1 = User::where('email', 'radit.platform@dutamall.internal')->first();
        $user2 = User::where('email', 'siti.sre@dutamall.internal')->first();
        $user3 = User::where('email', 'budi.developer@dutamall.internal')->first();
        $user4 = User::where('email', 'dewi.qa@dutamall.internal')->first();

        // 1. Seed 12 Sandbox di semua 7 status siklus hidup
        $sandboxesData = [
            ['slug' => 'sbx-feat-auth-01', 'name' => 'Feature OAuth2 Token Exchange', 'owner_id' => $user1->id, 'status' => SandboxStatus::REQUESTED->value, 'ttl' => 24, 'tier' => 'standard'],
            ['slug' => 'sbx-feat-auth-02', 'name' => 'Feature PKCE Mobile Flow', 'owner_id' => $user2->id, 'status' => SandboxStatus::REQUESTED->value, 'ttl' => 48, 'tier' => 'isolated'],
            ['slug' => 'sbx-feat-billing-01', 'name' => 'Feature QRIS Payment Webhook', 'owner_id' => $user1->id, 'status' => SandboxStatus::PROVISIONING->value, 'ttl' => 24, 'tier' => 'ephemeral'],
            ['slug' => 'sbx-feat-billing-02', 'name' => 'Feature Dispute Settlement', 'owner_id' => $user3->id, 'status' => SandboxStatus::PROVISIONING->value, 'ttl' => 36, 'tier' => 'standard'],
            ['slug' => 'sbx-feat-inv-01', 'name' => 'Feature Warehouse Stock Mutation', 'owner_id' => $user2->id, 'status' => SandboxStatus::SEEDED->value, 'ttl' => 24, 'tier' => 'standard'],
            ['slug' => 'sbx-feat-inv-02', 'name' => 'Feature Barcode Scanning Pipeline', 'owner_id' => $user4->id, 'status' => SandboxStatus::SEEDED->value, 'ttl' => 48, 'tier' => 'isolated'],
            ['slug' => 'sbx-feat-core-01', 'name' => 'Feature RBAC Permission Hierarchy', 'owner_id' => $user1->id, 'status' => SandboxStatus::READY->value, 'ttl' => 72, 'tier' => 'standard'],
            ['slug' => 'sbx-feat-core-02', 'name' => 'Feature Multi-Branch Org Switcher', 'owner_id' => $user3->id, 'status' => SandboxStatus::READY->value, 'ttl' => 24, 'tier' => 'isolated'],
            ['slug' => 'sbx-feat-crm-01', 'name' => 'Feature Customer Loyalty Tiers', 'owner_id' => $user2->id, 'status' => SandboxStatus::EXPIRING->value, 'ttl' => 1, 'tier' => 'ephemeral'],
            ['slug' => 'sbx-feat-crm-02', 'name' => 'Feature Coupon Redemption Batch', 'owner_id' => $user4->id, 'status' => SandboxStatus::EXPIRING->value, 'ttl' => 2, 'tier' => 'standard'],
            ['slug' => 'sbx-feat-report-01', 'name' => 'Feature End-of-Day Ledger Export', 'owner_id' => $user1->id, 'status' => SandboxStatus::EXPIRED->value, 'ttl' => -1, 'tier' => 'standard'],
            ['slug' => 'sbx-feat-report-02', 'name' => 'Feature Tax Summary PDF Generator', 'owner_id' => $user3->id, 'status' => SandboxStatus::DESTROYED->value, 'ttl' => -5, 'tier' => 'ephemeral'],
        ];

        foreach ($sandboxesData as $data) {
            $sandbox = SandboxEnvironment::firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'owner_id' => $data['owner_id'],
                    'status' => $data['status'],
                    'tier' => $data['tier'],
                    'expires_at' => $now->addHours($data['ttl']),
                    'metadata' => [
                        'database_schema' => 'plt_tenant_' . str_replace('-', '_', $data['slug']),
                        'allocated_cpu' => 2.0,
                        'allocated_memory_mb' => 2048,
                    ],
                    'idempotency_key' => sprintf('plt:seed:sandbox:%s:%d', $data['slug'], $data['owner_id']),
                    'created_at' => $now->subHours(10),
                    'updated_at' => $now,
                ]
            );

            SandboxTransition::firstOrCreate(
                [
                    'sandbox_id' => $sandbox->id,
                    'to_status' => $data['status'],
                ],
                [
                    'from_status' => SandboxStatus::REQUESTED->value,
                    'reason' => 'Seeding initial universe baseline',
                    'context' => ['event' => 'seeder_init', 'slug' => $data['slug']],
                    'created_at' => $now->subHours(5),
                ]
            );
        }

        // 2. Seed 4 SLO Target
        $slos = [
            [
                'service_name' => 'api-gateway',
                'tier' => 'tier_1_critical',
                'target_sli_bps' => 9995,
                'error_budget_mins' => 22,
                'burn_rate_1h' => 0.85,
                'burn_rate_6h' => 0.70,
                'status' => 'healthy',
            ],
            [
                'service_name' => 'auth-token-service',
                'tier' => 'tier_1_critical',
                'target_sli_bps' => 9999,
                'error_budget_mins' => 4,
                'burn_rate_1h' => 0.45,
                'burn_rate_6h' => 0.30,
                'status' => 'healthy',
            ],
            [
                'service_name' => 'payment-webhook-receiver',
                'tier' => 'tier_1_critical',
                'target_sli_bps' => 9990,
                'error_budget_mins' => 43,
                'burn_rate_1h' => 3.25,
                'burn_rate_6h' => 2.80,
                'status' => 'warning',
            ],
            [
                'service_name' => 'report-export-worker',
                'tier' => 'tier_3_standard',
                'target_sli_bps' => 9950,
                'error_budget_mins' => 216,
                'burn_rate_1h' => 14.80,
                'burn_rate_6h' => 12.50,
                'status' => 'critical',
            ],
        ];

        foreach ($slos as $slo) {
            ServiceSlo::updateOrCreate(
                ['service_name' => $slo['service_name']],
                $slo
            );
        }

        // 3. Seed Postmortem Insiden untuk SLO yang breach
        PostmortemRecord::updateOrCreate(
            ['incident_ref' => 'INC-2026-11-001'],
            [
                'title' => 'Report Worker Redis OOM Incident',
                'severity' => 'sev2_major',
                'root_cause' => 'Worker memory leak during large Excel generation without chunking streaming.',
                'action_items' => [
                    'Migrate report generator to Spout streaming reader/writer',
                    'Add Redis queue max memory threshold eviction policy',
                    'Set up auto-scaling memory limits on k8s worker pods',
                ],
                'started_at' => $now->subDays(2),
                'resolved_at' => $now->subDays(2)->addMinutes(45),
                'author_id' => $user2->id,
                'created_at' => $now->subDays(2),
            ]
        );

        // 4. Seed Aturan Masking PII
        $maskRules = [
            ['domain' => 'users', 'column_pattern' => 'email', 'mask_strategy' => 'email', 'is_active' => true],
            ['domain' => 'users', 'column_pattern' => 'phone_number', 'mask_strategy' => 'phone', 'is_active' => true],
            ['domain' => 'employees', 'column_pattern' => 'nik', 'mask_strategy' => 'nik', 'is_active' => true],
            ['domain' => 'customers', 'column_pattern' => 'credit_card', 'mask_strategy' => 'redact', 'is_active' => true],
        ];

        foreach ($maskRules as $mr) {
            MaskRule::firstOrCreate(
                ['domain' => $mr['domain'], 'column_pattern' => $mr['column_pattern']],
                $mr
            );
        }

        // 5. Seed Dataset Sintetik
        SyntheticDataset::firstOrCreate(
            ['checksum' => hash('sha256', 'dutamall-staging-seed-v1-schema-definition')],
            [
                'name' => 'dutamall-staging-seed-v1',
                'domain' => 'ecommerce',
                'record_count' => 1500,
                'schema_definition' => [
                    'tables' => ['users', 'orders', 'transactions'],
                    'privacy_compliant' => true,
                ],
                'created_at' => $now->subDays(1),
            ]
        );

        // 6. Seed Rekam Jejak Audit Awal
        PlatformAuditRun::firstOrCreate(
            ['run_uuid' => 'audit-run-seed-baseline-001'],
            [
                'source_a_status' => 'healthy',
                'source_b_status' => 'healthy',
                'discrepancy_count' => 0,
                'findings' => [
                    'summary' => '12 Sandboxes seeded across 7 states, 4 SLOs initialized, 1 postmortem recorded, 4 masking rules configured.',
                ],
                'completed_at' => $now,
            ]
        );
    }
}
