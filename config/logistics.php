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
];
