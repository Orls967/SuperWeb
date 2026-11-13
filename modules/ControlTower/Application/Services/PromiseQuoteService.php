<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\ControlTower\Contracts\ItemAvailabilityReader;
use Modules\ControlTower\Contracts\PromiseQuoteInterface;
use Modules\ControlTower\Domain\Algorithms\AtpCtpNetter;
use Modules\ControlTower\Domain\Exceptions\PromiseIdempotencyConflictException;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\Core\Application\Services\EventSchemaRegistry;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Distribution\Contracts\OpenDemandProvider;
use Modules\Manufacturing\Contracts\CapacityAndMaterialProvider;

/**
 * Layanan kuotasi janji pesanan (ATP/CTP) lintas modul dengan idempotensi dan penerbitan event outbox.
 */
final class PromiseQuoteService implements PromiseQuoteInterface
{
    public const string EVENT_NAME = 'tower.promise.made.v1';

    private const int PERIOD_DAYS = 7;

    public function __construct(
        private readonly ItemAvailabilityReader $availabilityReader,
        private readonly CapacityAndMaterialProvider $capacityMaterialProvider,
        private readonly OpenDemandProvider $openDemandProvider,
        private readonly AtpCtpNetter $netter,
        private readonly ClockInterface $clock,
        private readonly ?OutboxBusInterface $outboxBus = null,
        private readonly ?EventSchemaRegistry $schemaRegistry = null,
        private readonly int $horizonPeriods = 12,
    ) {}

    public function quote(
        string $itemCode,
        int $quantity,
        int $requestedPeriod,
        string $idempotencyKey,
        ?string $orderReference = null,
        ?string $asOfDate = null,
    ): array {
        $effectiveAsOfDate = ($asOfDate !== null && $asOfDate !== '')
            ? CarbonImmutable::parse($asOfDate)->toDateString()
            : $this->clock->now()->toDateString();

        $payloadHash = hash('sha256', (string) json_encode([
            'item_code' => $itemCode,
            'quantity' => $quantity,
            'requested_period' => $requestedPeriod,
            'order_reference' => $orderReference,
            'as_of_date' => $effectiveAsOfDate,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $itemCode,
            $quantity,
            $requestedPeriod,
            $idempotencyKey,
            $orderReference,
            $effectiveAsOfDate,
            $payloadHash,
        ): array {
            $existing = OrderPromise::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->payload_hash !== $payloadHash) {
                    throw PromiseIdempotencyConflictException::forKey($idempotencyKey);
                }

                return $this->toQuoteArray($existing);
            }

            $position = $this->availabilityReader->positionFor($itemCode, $effectiveAsOfDate);
            $capMat = $this->capacityMaterialProvider->cumulativeByPeriod(
                itemCode: $itemCode,
                periods: $this->horizonPeriods,
                asOfDate: $effectiveAsOfDate,
            );
            $mpsReceipts = $this->capacityMaterialProvider->mpsReceipts(
                itemCode: $itemCode,
                periods: $this->horizonPeriods,
                asOfDate: $effectiveAsOfDate,
            );
            $commitments = $this->openDemandProvider->commitmentsByPeriod(
                itemCode: $itemCode,
                periods: $this->horizonPeriods,
                asOfDate: $effectiveAsOfDate,
            );

            $net = $this->netter->net(
                itemCode: $itemCode,
                onHand: $position['on_hand'],
                quarantine: $position['quarantine'],
                hold: $position['hold'],
                mpsReceipts: $mpsReceipts,
                commitments: $commitments,
                capacityCumulative: $capMat['capacity_cumulative'],
                materialCumulative: $capMat['material_cumulative'],
                requestedQty: $quantity,
                requestedPeriod: $requestedPeriod,
            );

            $promiseCode = 'PRM-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 12));
            $safeOrderRef = mb_substr($orderReference ?? ('QUOTE-'.substr($payloadHash, 0, 10)), 0, 32);
            $deliveryPeriod = $net['promised_period'] ?? max(1, min($this->horizonPeriods, $requestedPeriod));
            $deliveryDate = CarbonImmutable::parse($effectiveAsOfDate)
                ->addDays($deliveryPeriod * self::PERIOD_DAYS)
                ->toDateString();

            $promise = OrderPromise::query()->create([
                'promise_code' => $promiseCode,
                'order_reference' => $safeOrderRef,
                'item_code' => mb_substr($itemCode, 0, 32),
                'requested_qty' => $quantity,
                'atp_confirmed_qty' => $net['atp_confirmed_qty'],
                'ctp_manufacturing_qty' => $net['ctp_manufacturing_qty'],
                'promised_delivery_date' => $deliveryDate,
                'promise_status' => $net['status'],
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'promised_period' => $net['promised_period'],
                'late_periods' => $net['late_periods'],
                'reason_code' => $net['reason_code'],
                'certificate_hash' => $net['certificate_hash'],
                'as_of_date' => $effectiveAsOfDate,
                'atp_cumulative' => $net['atp_cumulative'],
                'ctp_cumulative' => $net['ctp_cumulative'],
            ]);

            $this->emitPromiseMadeEvent($promise, $requestedPeriod, $effectiveAsOfDate);

            return $this->toQuoteArray($promise);
        });
    }

    /**
     * @return array{promise_code:string, status:string, promised_period:?int, late_periods:int, atp_cumulative:int, ctp_cumulative:int, reason_code:?string, certificate_hash:string}
     */
    private function toQuoteArray(OrderPromise $promise): array
    {
        return [
            'promise_code' => (string) $promise->promise_code,
            'status' => (string) $promise->promise_status,
            'promised_period' => $promise->promised_period !== null ? (int) $promise->promised_period : null,
            'late_periods' => (int) ($promise->late_periods ?? 0),
            'atp_cumulative' => (int) ($promise->atp_cumulative ?? 0),
            'ctp_cumulative' => (int) ($promise->ctp_cumulative ?? 0),
            'reason_code' => $promise->reason_code !== null ? (string) $promise->reason_code : null,
            'certificate_hash' => (string) ($promise->certificate_hash ?? ''),
        ];
    }

    private function emitPromiseMadeEvent(
        OrderPromise $promise,
        int $requestedPeriod,
        string $asOfDate,
    ): void {
        if ($this->schemaRegistry !== null && Schema::hasTable('core_event_schemas')) {
            $this->schemaRegistry->registerSchema(
                eventName: self::EVENT_NAME,
                version: 1,
                schemaDefinition: [
                    'type' => 'object',
                    'properties' => [
                        'promise_code' => ['type' => 'string'],
                        'order_reference' => ['type' => 'string'],
                        'item_code' => ['type' => 'string'],
                        'requested_qty' => ['type' => 'integer'],
                        'requested_period' => ['type' => 'integer'],
                        'status' => ['type' => 'string', 'enum' => ['on_time', 'late', 'cannot_promise']],
                        'promised_period' => ['type' => ['integer', 'null']],
                        'late_periods' => ['type' => 'integer'],
                        'atp_cumulative' => ['type' => 'integer'],
                        'ctp_cumulative' => ['type' => 'integer'],
                        'reason_code' => ['type' => ['string', 'null']],
                        'certificate_hash' => ['type' => 'string'],
                        'as_of_date' => ['type' => 'string'],
                    ],
                    'required' => ['promise_code', 'item_code', 'requested_qty', 'status', 'certificate_hash'],
                ],
                description: 'Janji pesanan ATP/CTP diterbitkan oleh ControlTower.',
                producerModule: 'ControlTower',
                consumerModules: ['Distribution', 'Store', 'Audit'],
            );
        }

        $eventPayload = [
            'promise_code' => (string) $promise->promise_code,
            'order_reference' => (string) $promise->order_reference,
            'item_code' => (string) $promise->item_code,
            'requested_qty' => (int) $promise->requested_qty,
            'requested_period' => $requestedPeriod,
            'status' => (string) $promise->promise_status,
            'promised_period' => $promise->promised_period !== null ? (int) $promise->promised_period : null,
            'late_periods' => (int) ($promise->late_periods ?? 0),
            'atp_cumulative' => (int) ($promise->atp_cumulative ?? 0),
            'ctp_cumulative' => (int) ($promise->ctp_cumulative ?? 0),
            'reason_code' => $promise->reason_code !== null ? (string) $promise->reason_code : null,
            'certificate_hash' => (string) ($promise->certificate_hash ?? ''),
            'as_of_date' => $asOfDate,
        ];

        if ($this->outboxBus !== null && Schema::hasTable('core_outbox')) {
            $this->outboxBus->record(
                eventType: self::EVENT_NAME,
                payload: $eventPayload,
                idempotencyKey: 'tower-promise-'.(string) $promise->idempotency_key,
                aggregate: $promise,
            );
        }

        Event::dispatch(self::EVENT_NAME, [$eventPayload]);
    }
}
