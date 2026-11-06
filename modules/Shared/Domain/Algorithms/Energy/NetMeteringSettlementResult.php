<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Energy;

/**
 * Result data object for E3 Energy\NetMeteringSettlement.
 */
final class NetMeteringSettlementResult
{
    /**
     * @param  list<array{
     *     interval: int,
     *     production_kwh: int,
     *     consumption_kwh: int,
     *     export_kwh: int,
     *     import_kwh: int
     * }>  $intervals
     * @param  array{
     *     total_production_kwh: int,
     *     total_consumption_kwh: int,
     *     total_export_kwh: int,
     *     total_import_kwh: int,
     *     tariff_idr_per_kwh: int,
     *     feed_in_tariff_idr_per_kwh: int,
     *     gross_import_cost_idr: int,
     *     export_credit_idr: int,
     *     net_bill_idr: int,
     *     digest: string
     * }  $certificate
     */
    public function __construct(
        public readonly int $totalExportKwh,
        public readonly int $totalImportKwh,
        public readonly int $tariffIdrPerKwh,
        public readonly int $feedInTariffIdrPerKwh,
        public readonly int $grossImportCostIdr,
        public readonly int $exportCreditIdr,
        public readonly int $netBillIdr,
        public readonly array $intervals,
        public readonly array $certificate
    ) {}
}
