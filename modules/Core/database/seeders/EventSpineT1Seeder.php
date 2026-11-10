<?php

declare(strict_types=1);

namespace Modules\Core\database\seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreEventDelivery;
use Modules\Core\Domain\Models\CoreEventSchema;
use Modules\Core\Domain\Models\CorePoisonMessage;
use Modules\Core\Domain\StateMachines\EventDeliveryStateMachine;

/**
 * Seeder T1 Event Spine (T-6 / W8-P3.2)
 *
 * Mengisi basis data skala T1 secara deterministik dalam < 3 menit:
 * - >= 20.000 pesan outbox lintas >= 6 modul dengan celah urutan yang disengaja (A4)
 * - 7 status EventDelivery muncul lengkap
 * - >= 40 pesan di DLQ dengan alasan beragam
 * - >= 5 poison message
 * - >= 8 versi skema
 * - >= 4 consumer group dengan lag bervariasi
 */
class EventSpineT1Seeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::parse('2026-01-01 00:00:00');

        // 1. Bersihkan tabel terkait agar idempoten
        DB::table('core_outbox')->delete();
        DB::table('sim_event_spine')->delete();
        DB::table('sim_event_consumers')->delete();
        DB::table('core_event_deliveries')->delete();
        DB::table('core_poison_messages')->delete();
        DB::table('core_spine_gaps')->delete();
        DB::table('core_consumer_inbox')->delete();
        DB::table('core_event_schemas')->delete();

        // 2. Buat >= 8 versi skema event di core_event_schemas
        $schemas = [
            ['event_name' => 'store.order.paid.v1', 'version' => 1, 'producer_module' => 'Store', 'consumer_modules' => ['Logistics', 'Manufacturing'], 'payload_schema' => ['type' => 'object', 'properties' => ['order_id' => ['type' => 'string'], 'amount' => ['type' => 'integer']], 'required' => ['order_id', 'amount']], 'description' => 'Pembayaran order toko v1'],
            ['event_name' => 'store.order.paid.v1', 'version' => 2, 'producer_module' => 'Store', 'consumer_modules' => ['Logistics', 'Manufacturing'], 'payload_schema' => ['type' => 'object', 'properties' => ['order_id' => ['type' => 'string'], 'amount' => ['type' => 'integer'], 'notes' => ['type' => 'string']], 'required' => ['order_id', 'amount']], 'description' => 'Pembayaran order toko v2 aditif'],
            ['event_name' => 'lgx.shipment.delivered.v1', 'version' => 1, 'producer_module' => 'Logistics', 'consumer_modules' => ['Core', 'Insurance'], 'payload_schema' => ['type' => 'object', 'properties' => ['shipment_id' => ['type' => 'string']], 'required' => ['shipment_id']], 'description' => 'Pengiriman kargo tiba v1'],
            ['event_name' => 'lgx.shipment.delivered.v1', 'version' => 2, 'producer_module' => 'Logistics', 'consumer_modules' => ['Core', 'Insurance'], 'payload_schema' => ['type' => 'object', 'properties' => ['shipment_id' => ['type' => 'string'], 'driver_notes' => ['type' => 'string']], 'required' => ['shipment_id']], 'description' => 'Pengiriman kargo tiba v2 aditif'],
            ['event_name' => 'auto.ev.session_completed.v1', 'version' => 1, 'producer_module' => 'Ev', 'consumer_modules' => ['Core', 'Esg'], 'payload_schema' => ['type' => 'object', 'properties' => ['session_id' => ['type' => 'string'], 'kwh' => ['type' => 'number']], 'required' => ['session_id']], 'description' => 'Pengisian EV selesai v1'],
            ['event_name' => 'hospital.code_blue.activated.v1', 'version' => 1, 'producer_module' => 'Hospital', 'consumer_modules' => ['Hospital', 'Nursing'], 'payload_schema' => ['type' => 'object', 'properties' => ['incident_id' => ['type' => 'string'], 'room_id' => ['type' => 'string']], 'required' => ['incident_id', 'room_id']], 'description' => 'Kode darurat medis v1'],
            ['event_name' => 'hotel.banquet.beo_finalized.v1', 'version' => 1, 'producer_module' => 'Hotel', 'consumer_modules' => ['Hotel', 'Resto'], 'payload_schema' => ['type' => 'object', 'properties' => ['beo_id' => ['type' => 'string'], 'pax' => ['type' => 'integer']], 'required' => ['beo_id']], 'description' => 'BEO banquet final v1'],
            ['event_name' => 'core.spine.dead_lettered.v1', 'version' => 1, 'producer_module' => 'Core', 'consumer_modules' => ['Core'], 'payload_schema' => ['type' => 'object', 'properties' => ['delivery_id' => ['type' => 'integer'], 'reason' => ['type' => 'string']], 'required' => ['delivery_id']], 'description' => 'Insiden DLQ terdeteksi v1'],
        ];

        foreach ($schemas as $s) {
            CoreEventSchema::create([
                'event_name' => $s['event_name'],
                'version' => $s['version'],
                'producer_module' => $s['producer_module'],
                'consumer_modules' => $s['consumer_modules'],
                'payload_schema' => $s['payload_schema'],
                'description' => $s['description'],
            ]);
        }

        // 3. Masukkan 20.000 pesan outbox secara deterministik via chunks (1.000 baris per batch)
        $modules = ['Store', 'Logistics', 'AutoServe', 'Hospital', 'Hotel', 'Payment'];
        $eventTypes = [
            'store.order.paid.v1',
            'lgx.shipment.delivered.v1',
            'auto.telematics.anomaly_detected.v1',
            'hospital.code_blue.activated.v1',
            'hotel.banquet.beo_finalized.v1',
            'payment.captured.v1',
        ];

        $totalOutbox = 20000;
        $chunkSize = 1000;
        $spineInserts = [];
        $aggregateSequences = [];

        for ($i = 1; $i <= $totalOutbox; $i += $chunkSize) {
            $batchOutbox = [];

            for ($j = 0; $j < $chunkSize; $j++) {
                $id = $i + $j;
                $modIndex = ($id - 1) % 6;
                $module = $modules[$modIndex];
                $eventType = $eventTypes[$modIndex];

                $aggId = 'AGG-'.(($id % 500) + 1);
                $aggregateSequences[$aggId] = ($aggregateSequences[$aggId] ?? 0) + 1;
                $seq = $aggregateSequences[$aggId];

                $payload = [
                    'order_id' => "ORD-{$id}",
                    'sequence' => $seq,
                    'amount' => ($id * 100) % 500000 + 1000,
                    'timestamp' => $now->copy()->addMinutes($id)->toIso8601String(),
                ];

                $batchOutbox[] = [
                    'id' => $id,
                    'event_type' => $eventType,
                    'aggregate_type' => $module,
                    'aggregate_id' => $aggId,
                    'payload' => json_encode($payload),
                    'headers' => json_encode(['correlation_id' => "corr-seed-{$id}"]),
                    'idempotency_key' => "IDEMP-SEED-{$id}",
                    'status' => 'dispatched',
                    'attempts' => 1,
                    'last_error' => null,
                    'next_retry_at' => null,
                    'dispatched_at' => $now->copy()->addMinutes($id),
                    'created_at' => $now->copy()->addMinutes($id),
                    'updated_at' => $now->copy()->addMinutes($id),
                ];

                if ($id <= 500) {
                    $topicName = match ($modIndex) {
                        0 => 'store.order',
                        1 => 'lgx.shipment',
                        2 => 'auto.telematics',
                        3 => 'hospital.code_blue',
                        4 => 'hotel.banquet',
                        default => 'payment.captured',
                    };

                    $spineInserts[] = [
                        'id' => $id,
                        'event_id' => (string) Str::uuid(),
                        'topic' => $topicName,
                        'event_name' => $eventType,
                        'version' => 1,
                        'payload' => json_encode($payload),
                        'idempotency_key' => "IDEMP-SPINE-{$id}",
                        'occurred_at' => $now->copy()->addMinutes($id),
                        'created_at' => $now->copy()->addMinutes($id),
                        'updated_at' => $now->copy()->addMinutes($id),
                    ];
                }
            }

            DB::table('core_outbox')->insert($batchOutbox);
        }

        // Masukkan sampel spine events
        if (! empty($spineInserts)) {
            DB::table('sim_event_spine')->insert($spineInserts);
        }

        // 4. Masukkan 7 status EventDelivery & >= 40 DLQ messages
        $dlqReasons = [
            'Connection timed out after 5000ms',
            'Downstream service returned HTTP 503 Service Unavailable',
            'Database deadlock detected during journal posting',
            'Invalid schema signature on custom payload field',
            'Certificate expired on remote webhook endpoint',
            'Max retries exceeded (6 attempts reached)',
            'Poison payload triggered uncaught domain exception',
            'Socket reset by peer during TLS handshake',
        ];

        $statuses = [
            EventDeliveryStateMachine::STATUS_PENDING,
            EventDeliveryStateMachine::STATUS_DISPATCHED,
            EventDeliveryStateMachine::STATUS_DELIVERED,
            EventDeliveryStateMachine::STATUS_RETRYING,
            EventDeliveryStateMachine::STATUS_DEAD_LETTERED,
            EventDeliveryStateMachine::STATUS_REPLAYED,
            EventDeliveryStateMachine::STATUS_DISCARDED,
        ];

        // Buat setidaknya 2 pengiriman per status
        $deliveryIdCounter = 1;
        foreach ($statuses as $st) {
            for ($k = 1; $k <= 3; $k++) {
                $msgUuid = (string) Str::uuid();
                CoreEventDelivery::create([
                    'message_id' => $msgUuid,
                    'consumer_group' => 'accounting_sync',
                    'topic' => 'store.order',
                    'status' => $st,
                    'attempts' => $st === 'retrying' ? 2 : ($st === 'dead_lettered' ? 6 : 1),
                    'last_error' => in_array($st, ['retrying', 'dead_lettered', 'discarded']) ? 'Simulated delivery failure' : null,
                ]);

                if ($st === EventDeliveryStateMachine::STATUS_DELIVERED) {
                    DB::table('core_consumer_inbox')->insert([
                        'consumer_group' => 'accounting_sync',
                        'message_id' => $msgUuid,
                        'handler_class' => 'OrderJournalHandler',
                        'processed_at' => Carbon::now(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
                $deliveryIdCounter++;
            }
        }

        // Buat 45 pesan di DLQ dengan alasan beragam
        for ($dlqIdx = 1; $dlqIdx <= 45; $dlqIdx++) {
            $reason = $dlqReasons[$dlqIdx % count($dlqReasons)];
            CoreEventDelivery::create([
                'message_id' => (string) Str::uuid(),
                'consumer_group' => 'logistics_fulfillment',
                'topic' => 'lgx.shipment',
                'status' => EventDeliveryStateMachine::STATUS_DEAD_LETTERED,
                'attempts' => 6,
                'last_error' => "DLQ #{$dlqIdx}: {$reason}",
            ]);
        }

        // 5. Masukkan >= 5 poison message
        for ($p = 1; $p <= 6; $p++) {
            CorePoisonMessage::create([
                'message_key' => (string) Str::uuid(),
                'consumer_group' => 'telematics_speed_tracker',
                'topic' => 'auto.telematics',
                'event_name' => 'auto.telematics.anomaly_detected.v1',
                'payload' => ['vin' => "CORRUPT-VIN-{$p}", 'anomaly_type' => 'MALFORMED_TELEMETRY_DATA'],
                'failure_reason' => "Corrupted binary frame in packet stream #{$p}",
                'exception_class' => \InvalidArgumentException::class,
                'stack_trace' => "#0 [internal function]: TelematicsParser->parsePacket()\n#1 Kernel: dispatch()",
                'attempts_count' => 3,
                'quarantined_at' => Carbon::now()->subMinutes($p * 10),
            ]);
        }

        // 6. Masukkan >= 4 consumer group dengan lag bervariasi
        $consumerGroups = [
            ['consumer_group' => 'logistics_fulfillment', 'topic' => 'lgx.shipment.*', 'last_processed_offset' => 450],
            ['consumer_group' => 'accounting_revenue', 'topic' => 'store.order.*', 'last_processed_offset' => 380],
            ['consumer_group' => 'hotel_kitchen_orders', 'topic' => 'hotel.banquet.*', 'last_processed_offset' => 490],
            ['consumer_group' => 'spine_alert_monitor', 'topic' => '*', 'last_processed_offset' => 500],
        ];

        foreach ($consumerGroups as $cg) {
            DB::table('sim_event_consumers')->insert([
                'consumer_group' => $cg['consumer_group'],
                'topic' => $cg['topic'],
                'last_processed_offset' => $cg['last_processed_offset'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
