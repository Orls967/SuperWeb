# Definition of Ready (DoR) - P5.2 Lini 23 Kehutanan (Forest `for_`, Fase 172)

## 1. Modul & Prefiks
- **Modul**: `Forest` (Namespace `Modules\Forest`)
- **Prefiks Tabel**: `for_`
- **Acuan**: KONSEP.md baris 1412, 1425, §A1, §A2, §A7, §A16; PROGRESS.md Fase 172.

## 2. Tabel & Skema Relasional
1. `for_concession_plots`
   - `id`: bigint unsigned auto_increment primary key
   - `plot_code`: string unique
   - `concession_name`: string
   - `area_hectares`: unsignedInteger
   - `annual_quota_m3`: unsignedInteger (m3 kuota tebang tahunan)
   - `harvested_volume_m3`: unsignedInteger default 0
   - `status`: string ('active', 'suspended', 'closed')
   - `timestamps`
2. `for_tree_inventories`
   - `id`: bigint unsigned auto_increment primary key
   - `tree_tag`: string unique
   - `plot_id`: foreign key `for_concession_plots.id`
   - `species_name`: string
   - `dbh_cm`: unsignedInteger (Diameter at Breast Height dalam cm)
   - `height_m`: unsignedInteger (Tinggi komersial dalam meter)
   - `form_factor_pct`: unsignedInteger default 70 (faktor bentuk f * 100, misal 0.70 -> 70)
   - `calculated_volume_m3`: unsignedInteger (Volume dalam minor unit / liter atau m3 dengan skala desimal tetap)
   - `status`: string ('standing', 'marked_harvest', 'felled', 'conserved')
   - `timestamps`
3. `for_harvest_permits`
   - `id`: bigint unsigned auto_increment primary key
   - `permit_code`: string unique
   - `plot_id`: foreign key `for_concession_plots.id`
   - `authorized_volume_m3`: unsignedInteger
   - `harvested_volume_m3`: unsignedInteger default 0
   - `valid_from`: date
   - `valid_until`: date
   - `status`: string ('draft', 'submitted', 'approved', 'rejected', 'closed')
   - `requester_user_ref`: unsignedBigInteger
   - `approver_user_ref`: unsignedBigInteger nullable
   - `approved_at`: timestamp nullable
   - `timestamps`
4. `for_timber_logs`
   - `id`: bigint unsigned auto_increment primary key
   - `log_code`: string unique
   - `plot_id`: foreign key `for_concession_plots.id`
   - `permit_id`: foreign key `for_harvest_permits.id`
   - `tree_tag`: string
   - `volume_m3`: unsignedInteger
   - `serial_number`: unsignedInteger (gapless per permit)
   - `prev_hash`: string
   - `canonical_hash`: string
   - `stage`: string ('stump', 'log_landing', 'sawmill', 'delivered')
   - `timestamps`
5. `for_timber_sales`
   - `id`: bigint unsigned auto_increment primary key
   - `sale_code`: string unique
   - `buyer_party_ref`: unsignedBigInteger
   - `total_volume_m3`: unsignedInteger
   - `total_amount_idr`: unsignedBigInteger
   - `forestry_levy_idr`: unsignedBigInteger (PSDH/DR pungutan kehutanan)
   - `idempotency_key`: string unique
   - `status`: string ('invoiced', 'paid')
   - `timestamps`
6. `for_restoration_plots`
   - `id`: bigint unsigned auto_increment primary key
   - `restoration_code`: string unique
   - `plot_id`: foreign key `for_concession_plots.id`
   - `planted_hectares`: unsignedInteger
   - `trees_planted`: unsignedInteger
   - `survival_rate_pct`: unsignedInteger
   - `satellite_ndvi_score`: unsignedInteger (skala 0..100)
   - `carbon_credits_issued`: unsignedInteger default 0
   - `timestamps`

## 3. Aturan Bisnis & Invarian
1. **Perhitungan Volume Pohon Kanonikal (TreeVolumeCalculator)**:
   - Rumus silvikultur: $V = \frac{\pi}{4} \times \left(\frac{DBH}{100}\right)^2 \times T \times f$.
   - Dihitung dalam integer integer/milli-m3 atau cubic decimeters ($dm^3$) untuk menghindari float.
2. **Four-Eyes Approval Izin Tebang**:
   - Izin tebang tidak boleh disetujui oleh requester sendiri (`requester_user_ref !== approver_user_ref`).
   - Total `authorized_volume_m3` per petak tidak boleh melampaui sisa kuota tahunan petak (`harvested_volume_m3 + authorized <= annual_quota_m3`).
3. **Chain of Custody & Hash Chain**:
   - Setiap batang kayu (`for_timber_logs`) memiliki nomor urut gapless per permit.
   - Hash SHA-256 kanonikal mengikat `log_code:permit_code:serial:volume_m3:prev_hash`.
4. **Kredit Karbon Restorasi melalui Esg**:
   - Modul Forest memanggil kontrak interface `Modules\Esg\Contracts\CarbonCreditIssuer`.
   - Kredit hanya dapat diterbitkan jika `survival_rate_pct >= 75%` dan `satellite_ndvi_score >= 60`.
5. **Double-Entry Ledger Posting**:
   - Penjualan kayu:
     - Debit `AR_TIMBER` (Piutang Usaha Kayu)
     - Kredit `REVENUE_TIMBER` (Pendapatan Penjualan Kayu)
     - Kredit `FORESTRY_LEVY_PAYABLE` (Utang Pungutan Kehutanan PSDH/DR)
6. **Perintah Audit**:
   - `php artisan for:audit` memeriksa integritas kuota tebang vs realisasi tebang kayu, hash-chain kayu, dan rekonsiliasi pungutan kehutanan.
