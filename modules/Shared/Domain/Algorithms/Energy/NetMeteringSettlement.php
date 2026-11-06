<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Energy;

use InvalidArgumentException;

/**
 * E3 Energy\NetMeteringSettlement
 *
 * Per interval: ekspor = max(0, produksi − konsumsi), impor = max(0, konsumsi − produksi);
 * tagihan = impor × tarif − ekspor × feed-in.
 *
 * Kompleksitas: O(T) di mana T adalah jumlah interval.
 */
final class NetMeteringSettlement
{
    public function __construct(
        private readonly NetMeteringSettlementCertificateVerifier $verifier = new NetMeteringSettlementCertificateVerifier
    ) {}

    /**
     * @param  list<int>  $productions  Daftar produksi kWh per interval
     * @param  list<int>  $consumptions  Daftar konsumsi kWh per interval
     * @param  int  $tariffIdrPerKwh  Tarif beli listrik impor (Rp/kWh)
     * @param  int  $feedInTariffIdrPerKwh  Tarif jual listrik surplus ekspor (Rp/kWh)
     */
    public function settle(
        array $productions,
        array $consumptions,
        int $tariffIdrPerKwh,
        int $feedInTariffIdrPerKwh
    ): NetMeteringSettlementResult {
        if (count($productions) !== count($consumptions)) {
            throw new InvalidArgumentException('Jumlah interval produksi dan konsumsi harus sama');
        }
        if ($tariffIdrPerKwh < 0) {
            throw new InvalidArgumentException("Tarif impor tidak boleh negatif: {$tariffIdrPerKwh}");
        }
        if ($feedInTariffIdrPerKwh < 0) {
            throw new InvalidArgumentException("Tarif feed-in tidak boleh negatif: {$feedInTariffIdrPerKwh}");
        }

        $intervals = [];
        $totalExport = 0;
        $totalImport = 0;
        $totalProd = 0;
        $totalCons = 0;
        $count = count($productions);

        for ($i = 0; $i < $count; $i++) {
            $p = (int) $productions[$i];
            $c = (int) $consumptions[$i];

            if ($p < 0 || $c < 0) {
                throw new InvalidArgumentException("Nilai produksi atau konsumsi interval {$i} tidak boleh negatif");
            }

            $export = max(0, $p - $c);
            $import = max(0, $c - $p);

            $intervals[] = [
                'interval' => $i,
                'production_kwh' => $p,
                'consumption_kwh' => $c,
                'export_kwh' => $export,
                'import_kwh' => $import,
            ];

            $totalProd += $p;
            $totalCons += $c;
            $totalExport += $export;
            $totalImport += $import;
        }

        $grossImportCost = $totalImport * $tariffIdrPerKwh;
        $exportCredit = $totalExport * $feedInTariffIdrPerKwh;
        $netBill = $grossImportCost - $exportCredit;

        $digest = hash('sha256', json_encode([
            'intervals' => $intervals,
            'tariff' => $tariffIdrPerKwh,
            'feed_in' => $feedInTariffIdrPerKwh,
            'net_bill' => $netBill,
        ], JSON_THROW_ON_ERROR));

        $result = new NetMeteringSettlementResult(
            totalExportKwh: $totalExport,
            totalImportKwh: $totalImport,
            tariffIdrPerKwh: $tariffIdrPerKwh,
            feedInTariffIdrPerKwh: $feedInTariffIdrPerKwh,
            grossImportCostIdr: $grossImportCost,
            exportCreditIdr: $exportCredit,
            netBillIdr: $netBill,
            intervals: $intervals,
            certificate: [
                'total_production_kwh' => $totalProd,
                'total_consumption_kwh' => $totalCons,
                'total_export_kwh' => $totalExport,
                'total_import_kwh' => $totalImport,
                'tariff_idr_per_kwh' => $tariffIdrPerKwh,
                'feed_in_tariff_idr_per_kwh' => $feedInTariffIdrPerKwh,
                'gross_import_cost_idr' => $grossImportCost,
                'export_credit_idr' => $exportCredit,
                'net_bill_idr' => $netBill,
                'digest' => $digest,
            ]
        );

        $this->verifier->verify($result);

        return $result;
    }
}
