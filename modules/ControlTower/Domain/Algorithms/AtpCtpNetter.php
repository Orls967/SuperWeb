<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Algorithms;

/**
 * Algoritma I1: Netting ATP/CTP multi-periode dengan bilangan bulat satuan dasar (tanpa float).
 */
final class AtpCtpNetter
{
    /**
     * @param  list<int>  $mpsReceipts  Penerimaan MPS terjadwal per periode (indeks 0 = periode 1).
     * @param  list<int>  $commitments  Komitmen pesanan terbuka per periode (indeks 0 = periode 1).
     * @param  list<int>  $capacityCumulative  Kapasitas bebas kumulatif per periode (indeks 0 = periode 1).
     * @param  list<int>  $materialCumulative  Material tersedia kumulatif per periode (indeks 0 = periode 1).
     * @return array{
     *     item_code: string,
     *     status: string,
     *     promised_period: ?int,
     *     late_periods: int,
     *     atp_cumulative: int,
     *     ctp_cumulative: int,
     *     atp_confirmed_qty: int,
     *     ctp_manufacturing_qty: int,
     *     reason_code: ?string,
     *     certificate_hash: string,
     *     atp_series: list<int>,
     *     ctp_series: list<int>
     * }
     */
    public function net(
        string $itemCode,
        int $onHand,
        int $quarantine,
        int $hold,
        array $mpsReceipts,
        array $commitments,
        array $capacityCumulative,
        array $materialCumulative,
        int $requestedQty,
        int $requestedPeriod,
    ): array {
        if ($itemCode === '' || $requestedQty <= 0) {
            return $this->buildResult(
                itemCode: $itemCode,
                onHand: $onHand,
                quarantine: $quarantine,
                hold: $hold,
                mpsReceipts: $mpsReceipts,
                commitments: $commitments,
                capacityCumulative: $capacityCumulative,
                materialCumulative: $materialCumulative,
                requestedQty: $requestedQty,
                requestedPeriod: $requestedPeriod,
                status: 'cannot_promise',
                promisedPeriod: null,
                latePeriods: 0,
                atpCumulative: 0,
                ctpCumulative: 0,
                atpConfirmedQty: 0,
                ctpManufacturingQty: 0,
                reasonCode: 'INVALID_QUANTITY',
                atpSeries: [],
                ctpSeries: [],
            );
        }

        $periods = max(
            count($mpsReceipts),
            count($commitments),
            count($capacityCumulative),
            count($materialCumulative),
        );

        $startingAvailable = max(0, $onHand - max(0, $quarantine) - max(0, $hold));

        if ($periods === 0 || $requestedPeriod < 1 || $requestedPeriod > $periods) {
            [$atpSeries, $ctpSeries] = $this->computeSeries(
                $periods,
                $startingAvailable,
                $mpsReceipts,
                $commitments,
                $capacityCumulative,
                $materialCumulative,
            );

            $endAtp = $periods > 0 ? $atpSeries[$periods - 1] : $startingAvailable;
            $endCtp = $periods > 0 ? $ctpSeries[$periods - 1] : $startingAvailable;

            return $this->buildResult(
                itemCode: $itemCode,
                onHand: $onHand,
                quarantine: $quarantine,
                hold: $hold,
                mpsReceipts: $mpsReceipts,
                commitments: $commitments,
                capacityCumulative: $capacityCumulative,
                materialCumulative: $materialCumulative,
                requestedQty: $requestedQty,
                requestedPeriod: $requestedPeriod,
                status: 'cannot_promise',
                promisedPeriod: null,
                latePeriods: 0,
                atpCumulative: $endAtp,
                ctpCumulative: $endCtp,
                atpConfirmedQty: 0,
                ctpManufacturingQty: 0,
                reasonCode: 'HORIZON_EXCEEDED',
                atpSeries: $atpSeries,
                ctpSeries: $ctpSeries,
            );
        }

        [$atpSeries, $ctpSeries, $normCap, $normMat] = $this->computeSeries(
            $periods,
            $startingAvailable,
            $mpsReceipts,
            $commitments,
            $capacityCumulative,
            $materialCumulative,
        );

        $reqIdx = $requestedPeriod - 1;
        if ($atpSeries[$reqIdx] >= $requestedQty || $ctpSeries[$reqIdx] >= $requestedQty) {
            $atpCum = $atpSeries[$reqIdx];
            $ctpCum = $ctpSeries[$reqIdx];
            $atpConfirmed = min($requestedQty, $atpCum);
            $ctpMfg = max(0, $requestedQty - $atpConfirmed);

            return $this->buildResult(
                itemCode: $itemCode,
                onHand: $onHand,
                quarantine: $quarantine,
                hold: $hold,
                mpsReceipts: $mpsReceipts,
                commitments: $commitments,
                capacityCumulative: $capacityCumulative,
                materialCumulative: $materialCumulative,
                requestedQty: $requestedQty,
                requestedPeriod: $requestedPeriod,
                status: 'on_time',
                promisedPeriod: $requestedPeriod,
                latePeriods: 0,
                atpCumulative: $atpCum,
                ctpCumulative: $ctpCum,
                atpConfirmedQty: $atpConfirmed,
                ctpManufacturingQty: $ctpMfg,
                reasonCode: null,
                atpSeries: $atpSeries,
                ctpSeries: $ctpSeries,
            );
        }

        for ($idx = $reqIdx + 1; $idx < $periods; $idx++) {
            if ($ctpSeries[$idx] >= $requestedQty) {
                $promisedPeriod = $idx + 1;
                $atpCum = $atpSeries[$idx];
                $ctpCum = $ctpSeries[$idx];
                $atpConfirmed = min($requestedQty, $atpCum);
                $ctpMfg = max(0, $requestedQty - $atpConfirmed);

                return $this->buildResult(
                    itemCode: $itemCode,
                    onHand: $onHand,
                    quarantine: $quarantine,
                    hold: $hold,
                    mpsReceipts: $mpsReceipts,
                    commitments: $commitments,
                    capacityCumulative: $capacityCumulative,
                    materialCumulative: $materialCumulative,
                    requestedQty: $requestedQty,
                    requestedPeriod: $requestedPeriod,
                    status: 'late',
                    promisedPeriod: $promisedPeriod,
                    latePeriods: $promisedPeriod - $requestedPeriod,
                    atpCumulative: $atpCum,
                    ctpCumulative: $ctpCum,
                    atpConfirmedQty: $atpConfirmed,
                    ctpManufacturingQty: $ctpMfg,
                    reasonCode: null,
                    atpSeries: $atpSeries,
                    ctpSeries: $ctpSeries,
                );
            }
        }

        $capEnd = $normCap[$periods - 1];
        $matEnd = $normMat[$periods - 1];
        $reasonCode = $capEnd <= $matEnd ? 'INSUFFICIENT_CAPACITY' : 'INSUFFICIENT_MATERIAL';

        return $this->buildResult(
            itemCode: $itemCode,
            onHand: $onHand,
            quarantine: $quarantine,
            hold: $hold,
            mpsReceipts: $mpsReceipts,
            commitments: $commitments,
            capacityCumulative: $capacityCumulative,
            materialCumulative: $materialCumulative,
            requestedQty: $requestedQty,
            requestedPeriod: $requestedPeriod,
            status: 'cannot_promise',
            promisedPeriod: null,
            latePeriods: 0,
            atpCumulative: $atpSeries[$periods - 1],
            ctpCumulative: $ctpSeries[$periods - 1],
            atpConfirmedQty: 0,
            ctpManufacturingQty: 0,
            reasonCode: $reasonCode,
            atpSeries: $atpSeries,
            ctpSeries: $ctpSeries,
        );
    }

    /**
     * @param  list<int>  $mpsReceipts
     * @param  list<int>  $commitments
     * @param  list<int>  $capacityCumulative
     * @param  list<int>  $materialCumulative
     * @return array{0: list<int>, 1: list<int>, 2: list<int>, 3: list<int>}
     */
    private function computeSeries(
        int $periods,
        int $startingAvailable,
        array $mpsReceipts,
        array $commitments,
        array $capacityCumulative,
        array $materialCumulative,
    ): array {
        if ($periods <= 0) {
            return [[], [], [], []];
        }

        $running = [];
        $normCap = [];
        $normMat = [];
        $balance = $startingAvailable;
        $prevCap = 0;
        $prevMat = 0;

        for ($i = 0; $i < $periods; $i++) {
            $mps = max(0, (int) ($mpsReceipts[$i] ?? 0));
            $commit = max(0, (int) ($commitments[$i] ?? 0));
            $balance = $balance + $mps - $commit;
            $running[$i] = $balance;

            $prevCap = max($prevCap, max(0, (int) ($capacityCumulative[$i] ?? $prevCap)));
            $prevMat = max($prevMat, max(0, (int) ($materialCumulative[$i] ?? $prevMat)));
            $normCap[$i] = $prevCap;
            $normMat[$i] = $prevMat;
        }

        // Look-ahead minimum menjaga komitmen periode mendatang tidak terpotong janji periode awal.
        $atpSeries = array_fill(0, $periods, 0);
        $suffixMin = $running[$periods - 1];
        for ($i = $periods - 1; $i >= 0; $i--) {
            $suffixMin = min($suffixMin, $running[$i]);
            $atpSeries[$i] = max(0, $suffixMin);
        }

        $ctpSeries = [];
        for ($i = 0; $i < $periods; $i++) {
            $producible = min($normCap[$i], $normMat[$i]);
            $ctpSeries[$i] = $atpSeries[$i] + $producible;
        }

        return [$atpSeries, $ctpSeries, $normCap, $normMat];
    }

    /**
     * @param  list<int>  $mpsReceipts
     * @param  list<int>  $commitments
     * @param  list<int>  $capacityCumulative
     * @param  list<int>  $materialCumulative
     * @param  list<int>  $atpSeries
     * @param  list<int>  $ctpSeries
     * @return array{
     *     item_code: string,
     *     status: string,
     *     promised_period: ?int,
     *     late_periods: int,
     *     atp_cumulative: int,
     *     ctp_cumulative: int,
     *     atp_confirmed_qty: int,
     *     ctp_manufacturing_qty: int,
     *     reason_code: ?string,
     *     certificate_hash: string,
     *     atp_series: list<int>,
     *     ctp_series: list<int>
     * }
     */
    private function buildResult(
        string $itemCode,
        int $onHand,
        int $quarantine,
        int $hold,
        array $mpsReceipts,
        array $commitments,
        array $capacityCumulative,
        array $materialCumulative,
        int $requestedQty,
        int $requestedPeriod,
        string $status,
        ?int $promisedPeriod,
        int $latePeriods,
        int $atpCumulative,
        int $ctpCumulative,
        int $atpConfirmedQty,
        int $ctpManufacturingQty,
        ?string $reasonCode,
        array $atpSeries,
        array $ctpSeries,
    ): array {
        $certificateHash = hash('sha256', (string) json_encode([
            'item_code' => $itemCode,
            'on_hand' => $onHand,
            'quarantine' => $quarantine,
            'hold' => $hold,
            'mps_receipts' => array_values($mpsReceipts),
            'commitments' => array_values($commitments),
            'capacity_cumulative' => array_values($capacityCumulative),
            'material_cumulative' => array_values($materialCumulative),
            'requested_qty' => $requestedQty,
            'requested_period' => $requestedPeriod,
            'status' => $status,
            'promised_period' => $promisedPeriod,
            'late_periods' => $latePeriods,
            'atp_cumulative' => $atpCumulative,
            'ctp_cumulative' => $ctpCumulative,
            'reason_code' => $reasonCode,
        ], JSON_THROW_ON_ERROR));

        return [
            'item_code' => $itemCode,
            'status' => $status,
            'promised_period' => $promisedPeriod,
            'late_periods' => $latePeriods,
            'atp_cumulative' => $atpCumulative,
            'ctp_cumulative' => $ctpCumulative,
            'atp_confirmed_qty' => $atpConfirmedQty,
            'ctp_manufacturing_qty' => $ctpManufacturingQty,
            'reason_code' => $reasonCode,
            'certificate_hash' => $certificateHash,
            'atp_series' => $atpSeries,
            'ctp_series' => $ctpSeries,
        ];
    }
}
