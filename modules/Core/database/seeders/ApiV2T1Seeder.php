<?php

declare(strict_types=1);

namespace Modules\Core\database\seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreApiClient;
use Modules\Core\Domain\Models\CoreApiClientKey;
use Modules\Core\Domain\Models\CoreBulkExportJob;
use Modules\Core\Domain\Models\CoreBulkImportJob;
use Modules\Core\Domain\Models\CoreEdiInterchange;
use Modules\Core\Domain\Models\CoreEdiTransition;
use Modules\Core\Domain\Models\CoreRetentionPolicy;
use Modules\Core\Domain\Models\CoreTenant;
use Modules\Core\Domain\Models\CoreWebhook;
use Modules\Core\Domain\StateMachines\EdiInterchangeStateMachine;

/**
 * Seeder Skala T1 API v2, Webhook HMAC, EDI, Multi-tenancy & Cold Archive (W8-P4.2 / Fase 55).
 *
 * Mengisi basis data skala T1 secara deterministik:
 * - 3 tenant inti (TENANT-A, TENANT-B, DEFAULT)
 * - 12 B2B API clients lintas tier (SILVER, GOLD, PLATINUM) dengan kunci aktif & riwayat rotasi
 * - 5.000 event pemakaian API di core_api_usage_events (200 OK, 422, 429 throttled)
 * - 300 langganan webhook terenkripsi di core_webhooks lintas tenant & event topics
 * - 40 berkas pertukaran EDI (core_edi_interchanges) mencakup seluruh 8 status siklus hidup
 * - 20.000 baris job impor streaming dan 50.000 baris job ekspor ZIP terkompresi
 * - Kebijakan cold archive retensi data aktif
 */
class ApiV2T1Seeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::parse('2026-11-12 12:00:00');

        // Bersihkan data dinamis agar seeder bersifat idempoten penuh
        DB::table('core_edi_transitions')->delete();
        DB::table('core_edi_interchanges')->delete();
        DB::table('core_webhook_dispatches')->delete();
        DB::table('core_api_usage_events')->delete();
        DB::table('core_bulk_import_jobs')->delete();
        DB::table('core_bulk_export_jobs')->delete();

        // 1. Pastikan Tenant Inti Tersedia
        $tenants = [
            'tenant-default' => CoreTenant::firstOrCreate(
                ['id' => 'tenant-default'],
                ['code' => 'DEFAULT', 'name' => 'Default Platform Tenant', 'is_active' => true]
            ),
            'tenant-a' => CoreTenant::firstOrCreate(
                ['id' => 'tenant-a'],
                ['code' => 'TENANT-A', 'name' => 'PT Surya Mega Logistik', 'is_active' => true]
            ),
            'tenant-b' => CoreTenant::firstOrCreate(
                ['id' => 'tenant-b'],
                ['code' => 'TENANT-B', 'name' => 'PT Nusantara Niaga Mandiri', 'is_active' => true]
            ),
        ];

        // 2. 12 Klien B2B API dengan Kunci & Kuota Terdistribusi
        $clientSpecs = [
            ['code' => 'CLI-B2B-S01', 'name' => 'PT Logistik Prima Express', 'tier' => 'SILVER', 'tenant' => 'tenant-a'],
            ['code' => 'CLI-B2B-S02', 'name' => 'CV Mandiri Mitra Distribusi', 'tier' => 'SILVER', 'tenant' => 'tenant-a'],
            ['code' => 'CLI-B2B-S03', 'name' => 'PT Kencana Cargo Nusantara', 'tier' => 'SILVER', 'tenant' => 'tenant-b'],
            ['code' => 'CLI-B2B-S04', 'name' => 'CV Sejahtera Abadi Trade', 'tier' => 'SILVER', 'tenant' => 'tenant-b'],
            ['code' => 'CLI-B2B-S05', 'name' => 'PT Samudera Niaga Gemilang', 'tier' => 'SILVER', 'tenant' => 'tenant-default'],
            ['code' => 'CLI-B2B-G01', 'name' => 'PT Mega B2B Retail Utama', 'tier' => 'GOLD', 'tenant' => 'tenant-a'],
            ['code' => 'CLI-B2B-G02', 'name' => 'PT Anugerah Distribusi Sentosa', 'tier' => 'GOLD', 'tenant' => 'tenant-a'],
            ['code' => 'CLI-B2B-G03', 'name' => 'PT Global Wholesale Network', 'tier' => 'GOLD', 'tenant' => 'tenant-b'],
            ['code' => 'CLI-B2B-G04', 'name' => 'PT Bahtera E-Commerce Nusantara', 'tier' => 'GOLD', 'tenant' => 'tenant-b'],
            ['code' => 'CLI-B2B-P01', 'name' => 'PT Enterprise Supply Chain Indo', 'tier' => 'PLATINUM', 'tenant' => 'tenant-a'],
            ['code' => 'CLI-B2B-P02', 'name' => 'PT Multinasional Perdagangan Asia', 'tier' => 'PLATINUM', 'tenant' => 'tenant-b'],
            ['code' => 'CLI-B2B-P03', 'name' => 'PT Garuda Industrial Platform', 'tier' => 'PLATINUM', 'tenant' => 'tenant-default'],
        ];

        $clients = [];
        foreach ($clientSpecs as $spec) {
            $tierDefaults = CoreApiClient::TIER_DEFAULTS[$spec['tier']];
            $tenantId = $tenants[$spec['tenant']]->id;

            $client = CoreApiClient::firstOrCreate(
                ['code' => $spec['code']],
                [
                    'tenant_id' => $tenantId,
                    'name' => $spec['name'],
                    'legal_entity_name' => $spec['name'],
                    'tier' => $spec['tier'],
                    'capacity' => $tierDefaults['capacity'],
                    'refill_per_sec' => $tierDefaults['refill_per_sec'],
                    'daily_quota' => $tierDefaults['daily_quota'],
                    'bucket_milli_tokens' => $tierDefaults['capacity'] * 1000,
                    'bucket_last_ms' => $now->getTimestampMs(),
                    'is_active' => true,
                    'is_sandbox' => false,
                ]
            );

            // Buat active key jika belum ada
            if ($client->keys()->where('status', 'active')->count() === 0) {
                $rawKey = 'sk_live_'.Str::random(32);
                CoreApiClientKey::create([
                    'api_client_id' => $client->id,
                    'key_prefix' => substr($rawKey, 0, 12),
                    'key_digest' => hash('sha256', $rawKey),
                    'abilities' => ['resource:read', 'resource:action', 'edi:write'],
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $clients[] = $client;
        }

        // 3. 5.000 Event Pemakaian API (Usage Events)
        if (DB::table('core_api_usage_events')->count() < 5000) {
            $routes = [
                ['uri' => '/api/v2/purchase-orders', 'method' => 'GET'],
                ['uri' => '/api/v2/shipments', 'method' => 'GET'],
                ['uri' => '/api/v2/warehouses', 'method' => 'GET'],
                ['uri' => '/api/v2/edi/interchanges', 'method' => 'POST'],
                ['uri' => '/api/v2/purchase-orders/actions/acknowledge', 'method' => 'POST'],
            ];

            $usageRows = [];
            $clientCount = count($clients);
            for ($u = 1; $u <= 5000; $u++) {
                $targetClient = $clients[($u - 1) % $clientCount];
                $route = $routes[($u - 1) % count($routes)];
                $isThrottled = ($u % 50 === 0);
                $isInvalid = (! $isThrottled && $u % 100 === 0);

                $status = $isThrottled ? 429 : ($isInvalid ? 422 : 200);
                $allowed = ($status === 200 || $status === 422);
                $retryAfter = $isThrottled ? 500 : 0;

                $eventTime = $now->copy()->subMinutes(5000 - $u);

                $usageRows[] = [
                    'api_client_id' => $targetClient->id,
                    'tenant_id' => $targetClient->tenant_id,
                    'route_uri' => $route['uri'],
                    'http_method' => $route['method'],
                    'http_status' => $status,
                    'allowed' => $allowed,
                    'retry_after_ms' => $retryAfter,
                    'occurred_at' => $eventTime->toDateTimeString(),
                ];

                if (count($usageRows) >= 500) {
                    DB::table('core_api_usage_events')->insert($usageRows);
                    $usageRows = [];
                }
            }
            if (! empty($usageRows)) {
                DB::table('core_api_usage_events')->insert($usageRows);
            }
        }

        // 4. 300 Webhook Subscriptions Terenkripsi Lintas Tenant
        if (CoreWebhook::count() < 300) {
            $topicsPool = [
                ['order.created.v1', 'order.status_changed.v1'],
                ['shipment.dispatched.v1', 'shipment.delivered.v1'],
                ['invoice.issued.v1', 'payment.settled.v1'],
                ['inventory.threshold_breached.v1'],
            ];

            $webhookRows = [];
            $currentCount = CoreWebhook::count();
            for ($w = $currentCount + 1; $w <= 300; $w++) {
                $tKey = ($w % 3 === 0) ? 'tenant-default' : (($w % 2 === 0) ? 'tenant-a' : 'tenant-b');
                $tenant = $tenants[$tKey];
                $topics = $topicsPool[($w - 1) % count($topicsPool)];
                $secret = 'whsec_'.Str::random(32);

                $webhookRows[] = [
                    'tenant_id' => $tenant->id,
                    'code' => sprintf('WH-%s-%04d', $tenant->code, $w),
                    'name' => "Integration Webhook #{$w} ({$tenant->code})",
                    'target_url' => "https://api.partner-{$w}.example.com/webhooks/superweb",
                    'event_topics' => json_encode($topics),
                    'secret_encrypted' => Crypt::encryptString($secret),
                    'previous_secret_encrypted' => null,
                    'previous_secret_expires_at' => null,
                    'consecutive_failures' => ($w % 30 === 0) ? 5 : 0,
                    'circuit_state' => ($w % 30 === 0) ? 'open' : 'closed',
                    'circuit_opened_at' => ($w % 30 === 0) ? $now->toDateTimeString() : null,
                    'is_active' => true,
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString(),
                ];

                if (count($webhookRows) >= 100) {
                    DB::table('core_webhooks')->insert($webhookRows);
                    $webhookRows = [];
                }
            }
            if (! empty($webhookRows)) {
                DB::table('core_webhooks')->insert($webhookRows);
            }
        }

        // 5. 40 Berkas Pertukaran EDI (core_edi_interchanges) Mencakup 8 Status
        if (CoreEdiInterchange::count() < 40) {
            $statuses = [
                EdiInterchangeStateMachine::STATUS_RECEIVED,
                EdiInterchangeStateMachine::STATUS_PARSED,
                EdiInterchangeStateMachine::STATUS_VALIDATED,
                EdiInterchangeStateMachine::STATUS_MAPPED,
                EdiInterchangeStateMachine::STATUS_ACKNOWLEDGED,
                EdiInterchangeStateMachine::STATUS_POSTED,
                EdiInterchangeStateMachine::STATUS_REJECTED,
                EdiInterchangeStateMachine::STATUS_ARCHIVED,
            ];

            $msgTypes = ['ORDERS', 'INVOIC', 'DESADV', '850', '810', '856'];
            $handlers = ['Distribution', 'Procurement', 'Wms'];

            for ($e = 1; $e <= 40; $e++) {
                $status = $statuses[($e - 1) % count($statuses)];
                $standard = ($e % 2 === 0) ? 'EDIFACT' : 'X12';
                $msgType = $msgTypes[($e - 1) % count($msgTypes)];
                $handler = $handlers[($e - 1) % count($handlers)];
                $tKey = ($e % 2 === 0) ? 'tenant-a' : 'tenant-b';
                $tenant = $tenants[$tKey];

                $ctrlRef = sprintf('IC-T1-%04d', $e);
                $docNo = sprintf('DOC-T1-%04d', $e);

                $interchange = CoreEdiInterchange::create([
                    'tenant_id' => $tenant->id,
                    'control_ref' => $ctrlRef,
                    'standard' => $standard,
                    'message_type' => $msgType,
                    'sender_ref' => "PARTNER-{$e}",
                    'receiver_ref' => 'SUPERWEB-HQ',
                    'document_number' => $docNo,
                    'status' => $status,
                    'raw_payload' => "UNB+UNOC:3+PARTNER-{$e}:ZZ+SUPERWEB:ZZ+261112:1200+{$ctrlRef}'UNH+1+{$msgType}:D:96A:UN'UNT+3+1'UNZ+1+{$ctrlRef}'",
                    'contrl_payload' => in_array($status, ['acknowledged', 'posted', 'archived', 'rejected'], true)
                        ? "UCI+{$ctrlRef}+PARTNER-{$e}+SUPERWEB+7'UNT+3+1'UNZ+1+ACK{$ctrlRef}'"
                        : null,
                    'error_codes' => ($status === 'rejected') ? ['SYNTAX_ERROR', 'LINE_MISMATCH'] : null,
                    'handler_module' => in_array($status, ['mapped', 'acknowledged', 'posted', 'archived'], true) ? $handler : null,
                    'target_reference' => in_array($status, ['posted', 'archived'], true) ? (string) Str::uuid() : null,
                    'idempotency_key' => "idem-edi-t1-{$e}",
                    'created_at' => $now->copy()->subHours(40 - $e),
                    'updated_at' => $now->copy()->subHours(40 - $e),
                ]);

                // Rekam transisi fakta dari received hingga status saat ini
                $history = match ($status) {
                    'received' => [
                        ['received', 'Interchange payload received and persisted'],
                    ],
                    'parsed' => [
                        ['received', 'Interchange payload received and persisted'],
                        ['parsed', 'Payload parsed into canonical EDI segments'],
                    ],
                    'validated' => [
                        ['received', 'Interchange payload received and persisted'],
                        ['parsed', 'Payload parsed into canonical EDI segments'],
                        ['validated', 'Segment counts and integrity validated'],
                    ],
                    'mapped' => [
                        ['received', 'Interchange payload received and persisted'],
                        ['parsed', 'Payload parsed into canonical EDI segments'],
                        ['validated', 'Segment counts and integrity validated'],
                        ['mapped', "Mapped to owner module {$handler}"],
                    ],
                    'acknowledged' => [
                        ['received', 'Interchange payload received and persisted'],
                        ['parsed', 'Payload parsed into canonical EDI segments'],
                        ['validated', 'Segment counts and integrity validated'],
                        ['mapped', "Mapped to owner module {$handler}"],
                        ['acknowledged', 'CONTRL functional acknowledgment dispatched'],
                    ],
                    'posted' => [
                        ['received', 'Interchange payload received and persisted'],
                        ['parsed', 'Payload parsed into canonical EDI segments'],
                        ['validated', 'Segment counts and integrity validated'],
                        ['mapped', "Mapped to owner module {$handler}"],
                        ['acknowledged', 'CONTRL functional acknowledgment dispatched'],
                        ['posted', 'Downstream module application executed without ledger impact'],
                    ],
                    'rejected' => [
                        ['received', 'Interchange payload received and persisted'],
                        ['parsed', 'Payload parsed into canonical EDI segments'],
                        ['rejected', 'Document rejected due to structural validation errors'],
                    ],
                    'archived' => [
                        ['received', 'Interchange payload received and persisted'],
                        ['parsed', 'Payload parsed into canonical EDI segments'],
                        ['validated', 'Segment counts and integrity validated'],
                        ['mapped', "Mapped to owner module {$handler}"],
                        ['acknowledged', 'CONTRL functional acknowledgment dispatched'],
                        ['posted', 'Downstream module application executed without ledger impact'],
                        ['archived', 'Interchange moved to cold retention storage'],
                    ],
                };

                $prevStatus = 'initial';
                foreach ($history as $step) {
                    $toStatus = $step[0];
                    $reason = $step[1];
                    CoreEdiTransition::create([
                        'edi_interchange_id' => $interchange->id,
                        'from_status' => $prevStatus,
                        'to_status' => $toStatus,
                        'reason' => $reason,
                        'handler_module' => $handler,
                        'actor_user_id' => null,
                        'transitioned_at' => $now->copy()->subHours(40 - $e),
                    ]);
                    $prevStatus = $toStatus;
                }
            }
        }

        // 6. Job Impor Skala Besar (20.000 Baris) & Job Ekspor (50.000 Baris)
        if (CoreBulkImportJob::count() === 0) {
            CoreBulkImportJob::create([
                'tenant_id' => 'tenant-a',
                'target_key' => 'b2b_catalog',
                'source_filename' => 't1_b2b_catalog_20k.csv',
                'file_path' => 'bulk_imports/t1_b2b_catalog_20k.csv',
                'total_rows' => 20000,
                'valid_rows' => 19880,
                'error_rows' => 120,
                'processed_rows' => 20000,
                'status' => 'completed',
                'approval_id' => 1,
                'created_at' => $now->copy()->subHours(5),
                'updated_at' => $now->copy()->subHours(4),
            ]);
        }

        if (CoreBulkExportJob::count() === 0) {
            CoreBulkExportJob::create([
                'tenant_id' => 'tenant-a',
                'target_key' => 'b2b_catalog',
                'total_rows' => 50000,
                'zip_path' => 'bulk_exports/b2b_catalog_50k_export.zip',
                'file_sha256' => hash('sha256', 'mock_zip_content_50k_dataset'),
                'expires_at' => $now->copy()->addDays(7),
                'status' => 'completed',
                'created_at' => $now->copy()->subHours(2),
                'updated_at' => $now->copy()->subHours(1),
            ]);
        }

        // 7. Kebijakan Retensi Cold Archive Aktif
        if (CoreRetentionPolicy::count() === 0) {
            CoreRetentionPolicy::create([
                'module_name' => 'Core',
                'source_table' => 'audit_logs',
                'archive_table' => 'core_audit_logs_archive',
                'retention_days' => 90,
                'native_partition_hint' => 'p_audit_logs_cold',
                'is_active' => true,
            ]);
            CoreRetentionPolicy::create([
                'module_name' => 'Logistics',
                'source_table' => 'lgx_tracking_events',
                'archive_table' => 'lgx_tracking_events_archive',
                'retention_days' => 180,
                'native_partition_hint' => 'p_tracking_cold',
                'is_active' => true,
            ]);
            CoreRetentionPolicy::create([
                'module_name' => 'Procurement',
                'source_table' => 'prc_po_versions',
                'archive_table' => 'prc_po_versions_archive',
                'retention_days' => 365,
                'native_partition_hint' => 'p_po_versions_cold',
                'is_active' => true,
            ]);
        }
    }
}
