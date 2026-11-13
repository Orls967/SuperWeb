<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

use InvalidArgumentException;
use Modules\Core\Domain\Models\CoreEventSchema;

final class EventCatalog
{
    /**
     * Official cross-module event definitions compliant with KONSEP.md §A4.
     *
     * @var array<string, array{
     *     name: string,
     *     version: int,
     *     producer: string,
     *     consumers: array<int, string>,
     *     purpose: string,
     *     payload_schema: array<string, string>
     * }>
     */
    private static array $catalog = [
        'store.order.paid.v1' => [
            'name' => 'store.order.paid.v1',
            'version' => 1,
            'producer' => 'Store',
            'consumers' => ['Logistics', 'Manufacturing', 'Pricing'],
            'purpose' => 'Order fulfillment dispatch, COGS/HPP calculation, price lock resolution',
            'payload_schema' => [
                'order_id' => 'string',
                'customer_id' => 'string',
                'amount_idr' => 'integer',
                'items' => 'array',
            ],
        ],
        'lgx.shipment.delivered.v1' => [
            'name' => 'lgx.shipment.delivered.v1',
            'version' => 1,
            'producer' => 'Logistics',
            'consumers' => ['Logistics', 'Core', 'Insurance'],
            'purpose' => 'Freight revenue recognition, digital passport update, cargo transit policy closure',
            'payload_schema' => [
                'shipment_id' => 'string',
                'tracking_number' => 'string',
                'vehicle_id' => 'string',
                'delivered_at' => 'string',
            ],
        ],
        'lgx.temperature.breached.v1' => [
            'name' => 'lgx.temperature.breached.v1',
            'version' => 1,
            'producer' => 'Logistics',
            'consumers' => ['Payment', 'Insurance'],
            'purpose' => 'Carrier payout hold on breach, automated cold-chain cargo insurance claim trigger',
            'payload_schema' => [
                'shipment_id' => 'string',
                'sensor_id' => 'string',
                'recorded_temp_celsius' => 'numeric',
                'threshold_temp_celsius' => 'numeric',
            ],
        ],
        'auto.telematics.anomaly_detected.v1' => [
            'name' => 'auto.telematics.anomaly_detected.v1',
            'version' => 1,
            'producer' => 'Telematics',
            'consumers' => ['AutoServe', 'Logistics'],
            'purpose' => 'Predictive maintenance booking draft generation, fleet unit ground alert',
            'payload_schema' => [
                'vin' => 'string',
                'telematics_id' => 'string',
                'anomaly_type' => 'string',
                'severity' => 'string',
            ],
        ],
        'auto.ev.session_completed.v1' => [
            'name' => 'auto.ev.session_completed.v1',
            'version' => 1,
            'producer' => 'Ev',
            'consumers' => ['Core', 'Esg'],
            'purpose' => 'Battery State-of-Health update in vehicle passport, Scope 2 kWh carbon calculation',
            'payload_schema' => [
                'session_id' => 'string',
                'vin' => 'string',
                'kwh_consumed' => 'numeric',
                'battery_soh_percent' => 'numeric',
            ],
        ],
        'mall.footfall.recorded.v1' => [
            'name' => 'mall.footfall.recorded.v1',
            'version' => 1,
            'producer' => 'Mall',
            'consumers' => ['Resto', 'CloudKitchen'],
            'purpose' => 'Mall zone footfall batch recording for tenant sales and cloud kitchen kitchen load forecast',
            'payload_schema' => [
                'zone_id' => 'string',
                'visitor_count' => 'integer',
                'recorded_hour' => 'string',
            ],
        ],
        'prop.meter.read.v1' => [
            'name' => 'prop.meter.read.v1',
            'version' => 1,
            'producer' => 'Proptech',
            'consumers' => ['Mall', 'Esg'],
            'purpose' => 'Smart building sub-meter reading for tenant utility billing and carbon accounting',
            'payload_schema' => [
                'meter_id' => 'string',
                'tenant_id' => 'string',
                'kwh_value' => 'numeric',
            ],
        ],
        'egy.meter.read.v1' => [
            'name' => 'egy.meter.read.v1',
            'version' => 1,
            'producer' => 'Egy',
            'consumers' => ['Mall', 'Esg'],
            'purpose' => 'Industrial energy grid telemetry, power consumption billing, emission index',
            'payload_schema' => [
                'grid_id' => 'string',
                'facility_id' => 'string',
                'active_power_kw' => 'numeric',
            ],
        ],
        'epc.project.handed_over.v1' => [
            'name' => 'epc.project.handed_over.v1',
            'version' => 1,
            'producer' => 'Epc',
            'consumers' => ['Asset'],
            'purpose' => 'Handover certificate completion and capitalization of CIP to fixed asset register',
            'payload_schema' => [
                'project_id' => 'string',
                'handover_ref' => 'string',
                'total_capitalized_idr' => 'integer',
            ],
        ],
        'hcm.payroll.posted.v1' => [
            'name' => 'hcm.payroll.posted.v1',
            'version' => 1,
            'producer' => 'Hcm',
            'consumers' => ['CloudKitchen', 'Wealth'],
            'purpose' => 'Monthly payroll journal posting, corporate meal payroll deduction, employee wealth allocation',
            'payload_schema' => [
                'payroll_run_id' => 'string',
                'period' => 'string',
                'total_net_idr' => 'integer',
            ],
        ],
        'hsp.episode.discharged.v1' => [
            'name' => 'hsp.episode.discharged.v1',
            'version' => 1,
            'producer' => 'Hospital',
            'consumers' => ['Payment', 'Insurance'],
            'purpose' => 'Inpatient/outpatient discharge settlement, insurance/BPJS tariff reimbursement claim',
            'payload_schema' => [
                'episode_id' => 'string',
                'patient_id' => 'string',
                'total_tariff_idr' => 'integer',
                'coverage_type' => 'string',
            ],
        ],
        'ven.event.closed.v1' => [
            'name' => 'ven.event.closed.v1',
            'version' => 1,
            'producer' => 'Venue',
            'consumers' => ['Venue', 'Media'],
            'purpose' => 'Event gate settlement, ticket revenue recognition, media performance royalty fee posting',
            'payload_schema' => [
                'event_id' => 'string',
                'venue_code' => 'string',
                'ticket_revenue_idr' => 'integer',
            ],
        ],
        'htl.night.audited.v1' => [
            'name' => 'htl.night.audited.v1',
            'version' => 1,
            'producer' => 'Hotel',
            'consumers' => ['Hotel', 'Rwa'],
            'purpose' => 'Night audit close, hotel daily room revenue posting, RWA fractional property yield accrual',
            'payload_schema' => [
                'property_code' => 'string',
                'audit_date' => 'string',
                'room_revenue_idr' => 'integer',
                'occupancy_rate' => 'numeric',
            ],
        ],
        'min.weighbridge.recorded.v1' => [
            'name' => 'min.weighbridge.recorded.v1',
            'version' => 1,
            'producer' => 'Mining',
            'consumers' => ['Logistics', 'Mining'],
            'purpose' => 'Weighbridge gross tare netting, mineral transit custody handover, state royalty calculation',
            'payload_schema' => [
                'ticket_id' => 'string',
                'concession_code' => 'string',
                'net_weight_ton' => 'numeric',
                'mineral_type' => 'string',
            ],
        ],
        'esg.emission.recorded.v1' => [
            'name' => 'esg.emission.recorded.v1',
            'version' => 1,
            'producer' => 'Esg',
            'consumers' => ['TradeFinance', 'Egy'],
            'purpose' => 'Carbon emission declaration, CBAM cross-border tariff validation, carbon credit debit',
            'payload_schema' => [
                'emission_log_id' => 'string',
                'facility_id' => 'string',
                'co2_equivalent_kg' => 'numeric',
                'scope' => 'string',
            ],
        ],
        'gov.proposal.passed.v1' => [
            'name' => 'gov.proposal.passed.v1',
            'version' => 1,
            'producer' => 'Governance',
            'consumers' => ['Contract', 'Epc'],
            'purpose' => 'Governance proposal approval trigger for municipal project draft and vendor tender',
            'payload_schema' => [
                'proposal_id' => 'string',
                'budget_cap_idr' => 'integer',
                'passed_at' => 'string',
            ],
        ],
        'enterprise.budget.threshold_exceeded.v1' => [
            'name' => 'enterprise.budget.threshold_exceeded.v1',
            'version' => 1,
            'producer' => 'EnterpriseFinance',
            'consumers' => ['Procurement', 'Core'],
            'purpose' => 'Alert cost center and procurement when department budget available is low',
            'payload_schema' => [
                'budget_id' => 'integer',
                'budget_code' => 'string',
                'entity_code' => 'string',
                'allocated_amount_minor' => 'integer',
                'remaining_available_minor' => 'integer',
                'threshold_percent' => 'integer',
            ],
        ],
        'enterprise.sod.violation_detected.v1' => [
            'name' => 'enterprise.sod.violation_detected.v1',
            'version' => 1,
            'producer' => 'EnterpriseFinance',
            'consumers' => ['Core', 'Audit'],
            'purpose' => 'Notify governance officers when segregation of duties conflict is detected',
            'payload_schema' => [
                'user_id' => 'integer',
                'sod_rule_id' => 'string',
                'first_role' => 'string',
                'second_role' => 'string',
                'severity' => 'string',
            ],
        ],
        'enterprise.period.closed.v1' => [
            'name' => 'enterprise.period.closed.v1',
            'version' => 1,
            'producer' => 'EnterpriseFinance',
            'consumers' => ['Intercompany', 'Treasury'],
            'purpose' => 'Signal monthly fiscal period closure and lock across holding ecosystem',
            'payload_schema' => [
                'entity_code' => 'string',
                'period' => 'string',
                'net_income_loss_minor' => 'integer',
                'closing_voucher_id' => 'string',
            ],
        ],
        'intercompany.mismatch.detected.v1' => [
            'name' => 'intercompany.mismatch.detected.v1',
            'version' => 1,
            'producer' => 'Intercompany',
            'consumers' => ['EnterpriseFinance', 'Core'],
            'purpose' => 'Alert controllers and auditors on bilateral reciprocal balance mismatch',
            'payload_schema' => [
                'pair_key' => 'string',
                'seller_entity_code' => 'string',
                'buyer_entity_code' => 'string',
                'seller_balance_minor' => 'integer',
                'buyer_balance_minor' => 'integer',
                'discrepancy_minor' => 'integer',
            ],
        ],
        'intercompany.mirror.posted.v1' => [
            'name' => 'intercompany.mirror.posted.v1',
            'version' => 1,
            'producer' => 'Intercompany',
            'consumers' => ['EnterpriseFinance', 'Banking'],
            'purpose' => 'Broadcast mirrored intercompany sales invoice and purchase bill generation',
            'payload_schema' => [
                'transaction_code' => 'string',
                'selling_entity_code' => 'string',
                'buying_entity_code' => 'string',
                'amount_minor' => 'integer',
            ],
        ],
        'intercompany.consolidation.locked.v1' => [
            'name' => 'intercompany.consolidation.locked.v1',
            'version' => 1,
            'producer' => 'Intercompany',
            'consumers' => ['EnterpriseFinance', 'Core'],
            'purpose' => 'Seal intercompany consolidation elimination cycle and prevent post-closing tampering',
            'payload_schema' => [
                'fiscal_period' => 'string',
                'locked_by_user_id' => 'integer',
                'total_eliminated_minor' => 'integer',
            ],
        ],
        'treasury.revaluation.posted.v1' => [
            'name' => 'treasury.revaluation.posted.v1',
            'version' => 1,
            'producer' => 'Treasury',
            'consumers' => ['EnterpriseFinance', 'Banking', 'Core'],
            'purpose' => 'Broadcast period-end foreign exchange revaluation journal posting (PSAK 10) across group financial reporting',
            'payload_schema' => [
                'revaluation_id' => 'string',
                'period' => 'string',
                'currency' => 'string',
                'foreign_balance_minor' => 'integer',
                'book_functional_idr' => 'integer',
                'revalued_functional_idr' => 'integer',
                'gain_loss_idr' => 'integer',
                'certificate_hash' => 'string',
            ],
        ],
        'treasury.covenant.breached.v1' => [
            'name' => 'treasury.covenant.breached.v1',
            'version' => 1,
            'producer' => 'Treasury',
            'consumers' => ['EnterpriseFinance', 'Core', 'Audit'],
            'purpose' => 'Alert corporate finance and governance officers when bank credit facility debt-to-equity covenant is breached or near breach',
            'payload_schema' => [
                'facility_id' => 'string',
                'facility_code' => 'string',
                'bank_name' => 'string',
                'covenant_der_permil' => 'integer',
                'max_der_permil' => 'integer',
                'verified_debt_idr' => 'integer',
                'verified_equity_idr' => 'integer',
                'severity' => 'string',
            ],
        ],
        'treasury.hedge.matured.v1' => [
            'name' => 'treasury.hedge.matured.v1',
            'version' => 1,
            'producer' => 'Treasury',
            'consumers' => ['Banking', 'Core', 'EnterpriseFinance'],
            'purpose' => 'Notify treasury dealers and settlement desk when a forward FX hedge contract reaches maturity for physical or net settlement',
            'payload_schema' => [
                'contract_id' => 'string',
                'contract_number' => 'string',
                'currency' => 'string',
                'notional_foreign_amount' => 'integer',
                'maturity_date' => 'string',
                'mtm_value_idr' => 'integer',
            ],
        ],
        'robotics.mission.completed.v1' => [
            'name' => 'robotics.mission.completed.v1',
            'version' => 1,
            'producer' => 'Wms',
            'consumers' => ['Wms', 'ControlTower'],
            'purpose' => 'Confirm linked warehouse task and update robot fleet telemetry when a robot mission completes',
            'payload_schema' => [
                'warehouse_id' => 'string',
                'mission_id' => 'integer',
                'mission_code' => 'string',
                'from_status' => 'string',
                'to_status' => 'string',
            ],
        ],
        'robotics.mission.failed.v1' => [
            'name' => 'robotics.mission.failed.v1',
            'version' => 1,
            'producer' => 'Wms',
            'consumers' => ['Wms', 'ControlTower'],
            'purpose' => 'Trigger zone reservation release compensation, manual fallback task, and wave replanning on robot failure',
            'payload_schema' => [
                'warehouse_id' => 'string',
                'mission_id' => 'integer',
                'mission_code' => 'string',
                'from_status' => 'string',
                'to_status' => 'string',
            ],
        ],
        'robotics.wave.released.v1' => [
            'name' => 'robotics.wave.released.v1',
            'version' => 1,
            'producer' => 'Wms',
            'consumers' => ['Wms', 'Logistics'],
            'purpose' => 'Publish FFD carrier-cutoff robotics wave plan with deterministic certificate hash',
            'payload_schema' => [
                'warehouse_id' => 'string',
                'batch_code' => 'string',
                'wave_count' => 'integer',
                'certificate_hash' => 'string',
            ],
        ],
        'tower.alert.raised.v1' => [
            'name' => 'tower.alert.raised.v1',
            'version' => 1,
            'producer' => 'ControlTower',
            'consumers' => ['Logistics', 'Wms', 'Core'],
            'purpose' => 'Raise a supply chain disruption alert from a versioned rule so logistics and warehouse can react and Tugas Saya items are created',
            'payload_schema' => [
                'alert_code' => 'string',
                'rule_code' => 'string',
                'object_ref' => 'string',
                'severity' => 'string',
                'dedup_key' => 'string',
            ],
        ],
        'tower.sop.published.v1' => [
            'name' => 'tower.sop.published.v1',
            'version' => 1,
            'producer' => 'ControlTower',
            'consumers' => ['Manufacturing', 'Procurement', 'Distribution'],
            'purpose' => 'Publish an approved S&OP cycle plan (demand, supply, gaps) to manufacturing, procurement and distribution planners',
            'payload_schema' => [
                'cycle_code' => 'string',
                'period' => 'string',
                'demand_plan_qty' => 'integer',
                'supply_plan_qty' => 'integer',
                'approved_by_user_id' => 'integer',
            ],
        ],
        'tower.promise.made.v1' => [
            'name' => 'tower.promise.made.v1',
            'version' => 1,
            'producer' => 'ControlTower',
            'consumers' => ['Distribution', 'Logistics', 'Core'],
            'purpose' => 'Notify order owners of an ATP/CTP order promise (on time, late or cannot promise) with its certificate hash',
            'payload_schema' => [
                'promise_code' => 'string',
                'order_reference' => 'string',
                'item_code' => 'string',
                'status' => 'string',
                'promised_period' => 'integer',
                'certificate_hash' => 'string',
            ],
        ],
    ];

    /**
     * @return array<string, array{
     *     name: string,
     *     version: int,
     *     producer: string,
     *     consumers: array<int, string>,
     *     purpose: string,
     *     payload_schema: array<string, string>
     * }>
     */
    public static function all(): array
    {
        return self::$catalog;
    }

    public static function has(string $eventName): bool
    {
        return isset(self::$catalog[$eventName]);
    }

    /**
     * @return array{
     *     name: string,
     *     version: int,
     *     producer: string,
     *     consumers: array<int, string>,
     *     purpose: string,
     *     payload_schema: array<string, string>
     * }|null
     */
    public static function get(string $eventName): ?array
    {
        return self::$catalog[$eventName] ?? null;
    }

    /**
     * Validates that an event definition adheres to KONSEP.md §A4 rules.
     *
     * @param  array<string, mixed>  $definition
     *
     * @throws InvalidArgumentException
     */
    public static function validateEventIntegrity(array $definition): void
    {
        $name = (string) ($definition['name'] ?? '');
        if (! preg_match('/^[a-z0-9]+\.[a-z0-9_]+\.[a-z0-9_]+\.v[0-9]+$/', $name)) {
            throw new InvalidArgumentException("Format nama event '{$name}' tidak valid. Harus format {domain}.{entitas}.{kejadian}.v{n} (KONSEP §A4.1).");
        }

        $producer = (string) ($definition['producer'] ?? '');
        if ($producer === '') {
            throw new InvalidArgumentException("Event '{$name}' wajib memiliki produsen nyata (KONSEP §A4.2).");
        }

        $consumers = $definition['consumers'] ?? [];
        if (! is_array($consumers) || empty($consumers)) {
            throw new InvalidArgumentException("Event lintas modul '{$name}' dilarang tanpa konsumen nyata (KONSEP §A4.2).");
        }

        foreach ($consumers as $consumer) {
            if (! is_string($consumer) || trim($consumer) === '') {
                throw new InvalidArgumentException("Nama konsumen event '{$name}' harus berupa string modul valid.");
            }
        }
    }

    /**
     * Validate standard payload structure per KONSEP.md §A4.1.
     * Required minimum fields:
     * - event_id (uuid)
     * - occurred_at (string/timestamp)
     * - aggregate_type (string)
     * - aggregate_id (string|int)
     * - idempotency_key (string)
     * - schema_version (int)
     * - data (array)
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidArgumentException
     */
    public static function validatePayloadStructure(string $eventName, array $payload): bool
    {
        $required = [
            'event_id',
            'occurred_at',
            'aggregate_type',
            'aggregate_id',
            'idempotency_key',
            'schema_version',
            'data',
        ];

        foreach ($required as $field) {
            if (! array_key_exists($field, $payload)) {
                throw new InvalidArgumentException("Payload event '{$eventName}' kekurangan field wajib '{$field}' (KONSEP §A4.1).");
            }
        }

        if (! is_int($payload['schema_version'])) {
            throw new InvalidArgumentException("Field 'schema_version' pada payload event '{$eventName}' harus integer.");
        }

        if (! is_array($payload['data'])) {
            throw new InvalidArgumentException("Field 'data' pada payload event '{$eventName}' harus array.");
        }

        return true;
    }

    /**
     * Sync catalog to core_event_schemas database table.
     */
    public static function syncToDatabase(): int
    {
        $synced = 0;
        foreach (self::$catalog as $eventName => $def) {
            self::validateEventIntegrity($def);

            CoreEventSchema::updateOrCreate(
                [
                    'event_name' => $eventName,
                    'version' => $def['version'],
                ],
                [
                    'producer_module' => $def['producer'],
                    'consumer_modules' => $def['consumers'],
                    'payload_schema' => $def['payload_schema'],
                    'description' => $def['purpose'],
                ]
            );
            $synced++;
        }

        return $synced;
    }
}
