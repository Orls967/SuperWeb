<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Tarif Pajak Pertambahan Nilai (PPN / VAT) Logistik
    |--------------------------------------------------------------------------
    | Tarif PPN sebesar 11% sesuai peraturan perpajakan Indonesia terkini.
    | Diberi label: SIMULASI, bukan nasihat perpajakan formal.
    */
    'vat_rate' => (float) env('LOGISTICS_VAT_RATE', 0.11),

    /*
    |--------------------------------------------------------------------------
    | Biaya Pembatalan Pengiriman (Cancellation Fee)
    |--------------------------------------------------------------------------
    | Biaya administrasi pembatalan pengiriman prabayar sebelum di-pickup.
    | Dipotong dari refund pelanggan dan diakui sebagai freight_revenue.
    */
    'cancellation_fee_idr' => (int) env('LOGISTICS_CANCELLATION_FEE_IDR', 25_000),

    /*
    |--------------------------------------------------------------------------
    | Asuransi Kargo (Cargo Insurance)
    |--------------------------------------------------------------------------
    | Tarif asuransi default: 0.2% (0.002) dari nilai barang yang dideklarasikan,
    | dengan nilai minimum premi Rp 10.000.
    */
    'insurance_rate' => (float) env('LOGISTICS_INSURANCE_RATE', 0.002),
    'insurance_min_idr' => (int) env('LOGISTICS_INSURANCE_MIN_IDR', 10_000),

    /*
    |--------------------------------------------------------------------------
    | Biaya Cash on Delivery (COD Fee)
    |--------------------------------------------------------------------------
    | Fee penanganan transaksi COD sebesar 3% dari nilai barang tunai.
    */
    'cod_fee_rate' => (float) env('LOGISTICS_COD_FEE_RATE', 0.03),
    'cod_fee_min_idr' => (int) env('LOGISTICS_COD_FEE_MIN_IDR', 5_000),

    // Pencairan dana COD ke shipper D+N hari setelah disetor di hub.
    'cod_settlement_days' => (int) env('LOGISTICS_COD_SETTLEMENT_DAYS', 2),

    /*
    |--------------------------------------------------------------------------
    | SLA Pengiriman (jam sejak booked_at)
    |--------------------------------------------------------------------------
    | Resi yang belum selesai melewati batas ini dianggap terlambat dan
    | ditandai exception tipe "late" oleh lgx:detect-late.
    */
    'sla_hours' => [
        'same_day' => 12,
        'express' => 24,
        'regular' => 72,
        'economy' => 168,
        'ltl' => 96,
        'ftl' => 72,
        'lcl' => 336,
        'fcl' => 336,
        'air_freight' => 48,
    ],

    /*
    |--------------------------------------------------------------------------
    | Klaim Kargo
    |--------------------------------------------------------------------------
    | Batas ganti rugi: barang diasuransikan = nilai deklarasi; tidak diasuransikan =
    | N x ongkos kirim (maks. nilai deklarasi bila ada). Klaim keterlambatan = ongkos kirim.
    */
    'claim_window_days' => (int) env('LOGISTICS_CLAIM_WINDOW_DAYS', 14),
    'claim_uninsured_multiplier' => (int) env('LOGISTICS_CLAIM_UNINSURED_MULTIPLIER', 10),

    // Nilai pabean total (IDR) yang otomatis masuk jalur merah (pemeriksaan fisik) pada simulasi bea cukai.
    'customs_red_lane_threshold_idr' => (int) env('LOGISTICS_CUSTOMS_RED_LANE_THRESHOLD_IDR', 500_000_000),

    // Jendela peringatan dini: resi yang jatuh tempo SLA dalam N jam ke depan dianggap berisiko.
    'sla_at_risk_hours' => (int) env('LOGISTICS_SLA_AT_RISK_HOURS', 6),
];
