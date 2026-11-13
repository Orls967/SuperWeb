<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ControlTower\Application\Actions\ExecuteAutonomousReallocationPlaybookAction;
use Modules\ControlTower\Domain\Models\AlertRule;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderRiskScore;
use Modules\Core\Application\Services\EventSchemaRegistry;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Logistics\Contracts\InTransitShipmentProvider;

final class AlertScannerService
{
    public const EVENT_ALERT_RAISED = 'tower.alert.raised.v1';

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly ?InTransitShipmentProvider $inTransitProvider = null,
        private readonly ?OutboxBusInterface $outbox = null,
        private readonly ?EventSchemaRegistry $schemaRegistry = null,
        private readonly ?ExecuteAutonomousReallocationPlaybookAction $reallocationPlaybook = null,
    ) {}

    /**
     * Memindai echelon stocks, pengiriman in-transit terlambat, dan skor risiko CRITICAL/HIGH
     * secara idempoten (deduplikasi berdasarkan dedup_key) serta menerbitkan event outbox.
     *
     * @return array{
     *     created_count: int,
     *     existing_count: int,
     *     alerts: list<DisruptionAlert>
     * }
     */
    public function scan(): array
    {
        $this->ensureEventSchemaRegistered();

        $disabledCategories = $this->disabledRuleCategories();
        $createdCount = 0;
        $existingCount = 0;
        $alerts = [];

        if (! in_array('SUPPLY_CHAIN_BOTTLENECK', $disabledCategories, true)) {
            foreach ($this->scanEchelonStocks() as $entry) {
                if ($entry['created']) {
                    $createdCount++;
                } else {
                    $existingCount++;
                }
                $alerts[] = $entry['alert'];
            }
        }

        if (! in_array('TRANSIT_DELAY', $disabledCategories, true)) {
            foreach ($this->scanOverdueTransit() as $entry) {
                if ($entry['created']) {
                    $createdCount++;
                } else {
                    $existingCount++;
                }
                $alerts[] = $entry['alert'];
            }
        }

        if (! in_array('ORDER_RISK', $disabledCategories, true)) {
            foreach ($this->scanHighRiskScores() as $entry) {
                if ($entry['created']) {
                    $createdCount++;
                } else {
                    $existingCount++;
                }
                $alerts[] = $entry['alert'];
            }
        }

        return [
            'created_count' => $createdCount,
            'existing_count' => $existingCount,
            'alerts' => $alerts,
        ];
    }

    /**
     * @return list<string>
     */
    private function disabledRuleCategories(): array
    {
        if (! Schema::hasTable('sct_alert_rules')) {
            return [];
        }

        return AlertRule::query()
            ->where('is_active', false)
            ->pluck('category')
            ->map(static fn ($c): string => (string) $c)
            ->all();
    }

    /**
     * @return list<array{alert: DisruptionAlert, created: bool}>
     */
    private function scanEchelonStocks(): array
    {
        $results = [];
        $stocks = EchelonStock::query()
            ->orderBy('item_code')
            ->orderBy('echelon_node')
            ->get();

        $stocksByItem = $stocks->groupBy('item_code');

        foreach ($stocks as $stock) {
            $effectiveStock = ($stock->on_hand_qty + $stock->in_transit_qty) - $stock->reserved_qty;
            if ($effectiveStock >= $stock->safety_stock_qty) {
                continue;
            }

            $severity = 'MEDIUM';
            if ($effectiveStock <= 0) {
                $severity = 'CRITICAL';
            } elseif ($stock->safety_stock_qty > 0 && $effectiveStock < intdiv($stock->safety_stock_qty, 2)) {
                $severity = 'HIGH';
            }

            $dedupKey = 'echelon:'.strtoupper($stock->item_code).':'.strtoupper($stock->echelon_node);
            $alertCode = 'ALT-'.strtoupper($stock->item_code).'-'.strtoupper($stock->echelon_node);
            $shortfall = max(1, $stock->safety_stock_qty - $effectiveStock);

            $proposal = $this->buildReallocationProposalForItem(
                itemCode: $stock->item_code,
                toNode: $stock->echelon_node,
                neededQty: $shortfall,
                candidateStocks: $stocksByItem->get($stock->item_code, collect())->all(),
            );

            $results[] = $this->upsertAlert(
                dedupKey: $dedupKey,
                alertCode: $alertCode,
                severity: $severity,
                category: 'SUPPLY_CHAIN_BOTTLENECK',
                title: "Stockout Risk on {$stock->item_code} at {$stock->echelon_node}",
                description: "Effective stock ({$effectiveStock}) is below safety threshold ({$stock->safety_stock_qty}).",
                affectedOrdersCount: max(1, (int) round($shortfall / 10)),
                proposal: $proposal,
            );
        }

        return $results;
    }

    /**
     * @return list<array{alert: DisruptionAlert, created: bool}>
     */
    private function scanOverdueTransit(): array
    {
        $results = [];
        $overdue = $this->inTransitProvider?->getOverdueInTransitShipments(72) ?? [];

        foreach ($overdue as $shipment) {
            $shipmentId = (int) ($shipment['shipment_id'] ?? 0);
            $tracking = (string) ($shipment['tracking_number'] ?? "SHP-{$shipmentId}");
            $hours = (int) ($shipment['hours_in_transit'] ?? 0);
            $origin = (string) ($shipment['origin_code'] ?? '');
            $dest = (string) ($shipment['destination_code'] ?? '');

            $severity = match (true) {
                $hours >= 144 => 'CRITICAL',
                $hours >= 96 => 'HIGH',
                default => 'MEDIUM',
            };

            $dedupKey = "transit:{$shipmentId}:{$tracking}";
            $alertCode = 'ALT-TRN-'.strtoupper(substr(hash('sha256', $dedupKey), 0, 10));

            $results[] = $this->upsertAlert(
                dedupKey: $dedupKey,
                alertCode: $alertCode,
                severity: $severity,
                category: 'TRANSIT_DELAY',
                title: "Overdue In-Transit Shipment {$tracking}",
                description: "Shipment {$tracking} ({$origin} -> {$dest}) has been in transit for {$hours} hours (SLA 72h).",
                affectedOrdersCount: 1,
                proposal: [
                    'action' => 'escalate_carrier',
                    'status' => 'pending_approval',
                    'shipment_id' => $shipmentId,
                    'tracking_number' => $tracking,
                    'hours_in_transit' => $hours,
                ],
            );
        }

        return $results;
    }

    /**
     * @return list<array{alert: DisruptionAlert, created: bool}>
     */
    private function scanHighRiskScores(): array
    {
        if (! Schema::hasTable('sct_order_risk_scores')) {
            return [];
        }

        $results = [];
        $scores = OrderRiskScore::query()
            ->whereIn('risk_tier', ['CRITICAL', 'HIGH'])
            ->orderByDesc('risk_score')
            ->get();

        foreach ($scores as $score) {
            $refPart = $score->order_reference ? strtoupper($score->order_reference) : 'ITEM';
            $dedupKey = 'risk:'.strtoupper($score->item_code).':'.$refPart;
            $alertCode = 'ALT-RSK-'.strtoupper(substr(hash('sha256', $dedupKey), 0, 10));

            $itemStocks = EchelonStock::query()->where('item_code', $score->item_code)->get();
            $deficitNode = $itemStocks->sortBy(fn (EchelonStock $s): int => ($s->on_hand_qty + $s->in_transit_qty) - $s->reserved_qty - $s->safety_stock_qty)->first();
            $proposal = null;
            if ($deficitNode !== null) {
                $needed = max(1, $deficitNode->safety_stock_qty - (($deficitNode->on_hand_qty + $deficitNode->in_transit_qty) - $deficitNode->reserved_qty));
                $proposal = $this->buildReallocationProposalForItem(
                    itemCode: $score->item_code,
                    toNode: $deficitNode->echelon_node,
                    neededQty: $needed,
                    candidateStocks: $itemStocks->all(),
                );
            }

            if ($proposal === null) {
                $proposal = [
                    'action' => 'risk_mitigation',
                    'status' => 'pending_approval',
                    'item_code' => $score->item_code,
                    'order_reference' => $score->order_reference,
                    'risk_score' => $score->risk_score,
                    'recommendation' => $score->mitigation_recommendation,
                ];
            }

            $results[] = $this->upsertAlert(
                dedupKey: $dedupKey,
                alertCode: $alertCode,
                severity: $score->risk_tier,
                category: 'ORDER_RISK',
                title: "High Supply Risk ({$score->risk_tier}) on {$score->item_code}".($score->order_reference ? " ({$score->order_reference})" : ''),
                description: (string) ($score->mitigation_recommendation ?? "Risk score {$score->risk_score}/1000."),
                affectedOrdersCount: 1,
                proposal: $proposal,
            );
        }

        return $results;
    }

    /**
     * @param  array<int, EchelonStock>  $candidateStocks
     * @return array<string, mixed>|null
     */
    private function buildReallocationProposalForItem(
        string $itemCode,
        string $toNode,
        int $neededQty,
        array $candidateStocks,
    ): ?array {
        foreach ($candidateStocks as $candidate) {
            if ($candidate->echelon_node === $toNode) {
                continue;
            }

            $surplus = $candidate->on_hand_qty - max(0, $candidate->safety_stock_qty);
            if ($surplus <= 0) {
                continue;
            }

            $transferQty = min($neededQty, $surplus);
            if ($transferQty <= 0) {
                continue;
            }

            if ($this->reallocationPlaybook !== null) {
                return $this->reallocationPlaybook->proposeReallocation(
                    itemCode: $itemCode,
                    fromNode: $candidate->echelon_node,
                    toNode: $toNode,
                    transferQty: $transferQty,
                );
            }

            return [
                'action' => 'reallocation',
                'status' => 'pending_approval',
                'item_code' => $itemCode,
                'from_node' => $candidate->echelon_node,
                'to_node' => $toNode,
                'transfer_qty' => $transferQty,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $proposal
     * @return array{alert: DisruptionAlert, created: bool}
     */
    private function upsertAlert(
        string $dedupKey,
        string $alertCode,
        string $severity,
        string $category,
        string $title,
        string $description,
        int $affectedOrdersCount,
        ?array $proposal,
    ): array {
        return DB::transaction(function () use (
            $dedupKey,
            $alertCode,
            $severity,
            $category,
            $title,
            $description,
            $affectedOrdersCount,
            $proposal
        ): array {
            $existing = DisruptionAlert::query()
                ->where('dedup_key', $dedupKey)
                ->orWhere('alert_code', $alertCode)
                ->first();

            if ($existing !== null) {
                $updates = [];
                if ($existing->dedup_key === null) {
                    $updates['dedup_key'] = $dedupKey;
                }
                if ($existing->status === 'active') {
                    $updates['severity'] = $severity;
                    $updates['title'] = $title;
                    $updates['description'] = $description;
                    $updates['affected_orders_count'] = $affectedOrdersCount;
                    $existingStatus = is_array($existing->mitigation_proposal_json)
                        ? ($existing->mitigation_proposal_json['status'] ?? null)
                        : null;
                    if ($proposal !== null && ($existing->mitigation_proposal_json === null || $existingStatus === 'pending_approval')) {
                        $updates['mitigation_proposal_json'] = $proposal;
                    }
                }

                if ($updates !== []) {
                    $existing->update($updates);
                }

                return [
                    'alert' => $existing->fresh() ?? $existing,
                    'created' => false,
                ];
            }

            $alert = DisruptionAlert::create([
                'alert_code' => $alertCode,
                'dedup_key' => $dedupKey,
                'severity' => $severity,
                'category' => $category,
                'title' => $title,
                'description' => $description,
                'affected_orders_count' => $affectedOrdersCount,
                'status' => 'active',
                'mitigation_proposal_json' => $proposal,
            ]);

            $this->emitAlertRaisedEvent($alert, $dedupKey);

            return [
                'alert' => $alert,
                'created' => true,
            ];
        });
    }

    private function ensureEventSchemaRegistered(): void
    {
        if ($this->schemaRegistry === null || ! Schema::hasTable('core_event_schemas')) {
            return;
        }

        if ($this->schemaRegistry->hasSchema(self::EVENT_ALERT_RAISED, 1)) {
            return;
        }

        $this->schemaRegistry->registerSchema(
            eventName: self::EVENT_ALERT_RAISED,
            version: 1,
            schemaDefinition: [
                'type' => 'object',
                'properties' => [
                    'alert_code' => ['type' => 'string'],
                    'dedup_key' => ['type' => 'string'],
                    'severity' => ['type' => 'string', 'enum' => ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL']],
                    'category' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'affected_orders_count' => ['type' => 'integer', 'minimum' => 0],
                    'raised_at' => ['type' => 'string'],
                ],
                'required' => ['alert_code', 'dedup_key', 'severity', 'category', 'title', 'affected_orders_count', 'raised_at'],
            ],
            description: 'Diterbitkan saat pemindai Control Tower mendeteksi gangguan atau risiko CRITICAL/HIGH',
            producerModule: 'ControlTower',
            consumerModules: ['*'],
        );
    }

    private function emitAlertRaisedEvent(DisruptionAlert $alert, string $dedupKey): void
    {
        if ($this->outbox === null || ! Schema::hasTable('core_outbox')) {
            return;
        }

        $payload = [
            'alert_code' => $alert->alert_code,
            'dedup_key' => $dedupKey,
            'severity' => $alert->severity,
            'category' => $alert->category,
            'title' => $alert->title,
            'affected_orders_count' => (int) $alert->affected_orders_count,
            'raised_at' => $this->clock->now()->toIso8601String(),
        ];

        $this->schemaRegistry?->validate(self::EVENT_ALERT_RAISED, 1, $payload);

        $idempotencyKey = "tower:alert:raised:{$dedupKey}";

        $this->outbox->record(
            eventType: self::EVENT_ALERT_RAISED,
            payload: $payload,
            idempotencyKey: $idempotencyKey,
            aggregate: $alert,
        );
    }
}
