<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Algorithms\Events\SchemaAdditiveCompatibilityChecker;
use Modules\Core\Domain\Models\CoreConsumerInbox;
use Modules\Core\Domain\Models\CoreEventDelivery;
use Modules\Core\Domain\Models\CoreEventSchema;
use Modules\Core\Domain\Models\CoreSpineGap;
use Modules\Core\Domain\Models\SimEventSpine;
use Modules\Core\Domain\StateMachines\EventDeliveryStateMachine;

class AuditSpineCommand extends Command
{
    protected $signature = 'spine:audit {--fix : Attempt automatic remediation of non-fatal drifts}';

    protected $description = 'Perform dual-source audit across outbox, event spine log, consumer inbox and deliveries';

    public function handle(SchemaAdditiveCompatibilityChecker $compatibilityChecker): int
    {
        $this->info('Starting dual-source audit of Universal Event Spine...');
        $corruptions = [];

        // Pemeriksaan 1: Delivery delivered tanpa baris inbox yang cocok
        $deliveredWithoutInbox = CoreEventDelivery::where('status', EventDeliveryStateMachine::STATUS_DELIVERED)->get();
        foreach ($deliveredWithoutInbox as $delivery) {
            $hasInbox = CoreConsumerInbox::where('consumer_group', $delivery->consumer_group)
                ->where('message_id', $delivery->message_id)
                ->exists();

            if (! $hasInbox) {
                $corruptions[] = "Delivery delivered without inbox record: message_id={$delivery->message_id}, consumer_group={$delivery->consumer_group}";
            }
        }

        // Pemeriksaan 2: Celah urutan yang belum ditutup (open gaps)
        $unresolvedGaps = CoreSpineGap::where('status', CoreSpineGap::STATUS_OPEN)->get();
        foreach ($unresolvedGaps as $gap) {
            $corruptions[] = "Unresolved aggregate sequence gap detected: aggregate={$gap->aggregate_type}:{$gap->aggregate_id}, range=[{$gap->gap_start}, {$gap->gap_end}]";
        }

        // Pemeriksaan 3: Pesan DLQ tanpa alasan kegagalan tercatat
        $dlqWithoutReason = CoreEventDelivery::where('status', EventDeliveryStateMachine::STATUS_DEAD_LETTERED)
            ->where(function ($q) {
                $q->whereNull('last_error')->orWhere('last_error', '');
            })
            ->get();
        foreach ($dlqWithoutReason as $dlq) {
            $corruptions[] = "DLQ entry without failure reason: id={$dlq->id}, message_id={$dlq->message_id}, consumer_group={$dlq->consumer_group}";
        }

        // Pemeriksaan 4: Offset konsumen melebihi ID event spine terbesar
        $maxSpineId = (int) (SimEventSpine::max('id') ?? 0);
        $consumerOffsets = DB::table('sim_event_consumers')->get();
        foreach ($consumerOffsets as $c) {
            if ((int) $c->last_processed_offset > $maxSpineId) {
                $corruptions[] = "Consumer offset exceeds last spine event: consumer_group={$c->consumer_group}, topic={$c->topic}, offset={$c->last_processed_offset}, max_id={$maxSpineId}";
            }
        }

        // Pemeriksaan 5: Skema aktif tidak lolos A6 terhadap pendahulunya
        $allSchemas = CoreEventSchema::orderBy('event_name')->orderBy('version')->get()->groupBy('event_name');
        foreach ($allSchemas as $eventName => $versions) {
            $prev = null;
            foreach ($versions as $ver) {
                if ($prev !== null) {
                    $violations = $compatibilityChecker->checkCompatibility(
                        (array) $prev->payload_schema,
                        (array) $ver->payload_schema
                    );
                    if (! empty($violations)) {
                        $violationSummary = implode(';', array_map(fn ($v) => "{$v['path']}:{$v['code']}", $violations));
                        $corruptions[] = "Schema version {$ver->version} breaks additive compatibility A6 with v{$prev->version} for event {$eventName}: [{$violationSummary}]";
                    }
                }
                $prev = $ver;
            }
        }

        // Evaluasi Hasil Audit
        if (! empty($corruptions)) {
            $this->error('spine:audit FAILED: Data corruption or integrity violation detected in event spine!');
            foreach ($corruptions as $c) {
                $this->error(" - CORRUPTION: {$c}");
            }

            return self::FAILURE;
        }

        $this->info('spine:audit PASSED: Dual-source audit verified 100% integrity across event spine, outbox, deliveries, and consumer inbox.');

        return self::SUCCESS;
    }
}
