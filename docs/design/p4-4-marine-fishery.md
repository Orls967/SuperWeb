# Arsitektur & Desain Teknis — [G6-P4.4] Lini 22: Perikanan Marine (Fase 170)

## 1. Modul Baru & Struktur Direktori
Modul `Marine` berlokasi di `modules/Marine/` dengan namespace `Modules\Marine`:
- `Application/Services/MarineAquacultureService.php`: Logika orkestrasi siklus tebar, pakan, panen, grading, dan kalkulasi FCR rasional serta settlement payout.
- `Contracts/`: Kontrak modul (jika dibutuhkan modul eksternal).
- `Domain/Models/`:
  - `MarinePond.php`: Kolam/keramba (`mar_ponds`).
  - `MarineCycle.php`: Siklus budidaya (`mar_cycles`).
  - `MarineFeedLog.php`: Log pakan append-only (`mar_feed_logs`).
  - `MarineHarvest.php`: Penerimaan panen & grading (`mar_harvests`).
  - `MarinePayout.php`: Subledger settlement payout pembudidaya (`mar_payouts`).
- `Ledger/MarineAccounts.php`: Chart of accounts perikanan laut.
- `UI/Http/Controllers/MarineController.php`: Endpoint controller HTTP.
- `UI/Http/routes.php`: Route prefix `/marine` dilindungi auth & role `farmer`/`manager`.
- `UI/Console/Commands/AuditMarineCommand.php`: Command audit `marine:audit` (atau `mar:audit`).
- `database/migrations/`: Migrasi tabel-tabel `mar_*`.
- `Providers/MarineServiceProvider.php`: Service provider modul.

## 2. Tabel & Skema Basis Data
1. `mar_ponds`:
   - `id` (bigint pk), `code` (string unique), `name` (string), `farmer_id` (bigint), `capacity_units` (int), `location` (string), `created_at`, `updated_at`.
2. `mar_cycles`:
   - `id` (bigint pk), `cycle_number` (string unique), `pond_id` (bigint fk), `farmer_id` (bigint fk), `species` (string), `stocked_count` (int), `initial_weight_grams_per_fish` (int), `advance_amount` (bigint), `status` (string: active, harvested, settled), `started_at` (timestamp), `created_at`, `updated_at`.
3. `mar_feed_logs`:
   - `id` (bigint pk), `cycle_id` (bigint fk), `feed_date` (date), `quantity_kg` (int), `cost_per_kg` (bigint), `total_cost` (bigint), `loan_disbursed` (boolean), `created_at`, `updated_at`.
4. `mar_harvests`:
   - `id` (bigint pk), `harvest_number` (string unique), `cycle_id` (bigint fk), `harvest_date` (date), `harvested_count` (int), `avg_weight_grams` (int), `total_weight_kg` (int), `grade_a_kg` (int), `grade_a_price` (bigint), `grade_b_kg` (int), `grade_b_price` (bigint), `grade_c_kg` (int), `grade_c_price` (bigint), `gross_amount` (bigint), `fcr_scaled` (int: FCR x 1000 untuk 3 desimal), `created_at`, `updated_at`.
5. `mar_payouts`:
   - `id` (bigint pk), `cycle_id` (bigint fk), `farmer_id` (bigint fk), `gross_amount` (bigint), `advance_deduction` (bigint), `feed_loan_deduction` (bigint), `net_payout` (bigint), `settled_at` (timestamp), `ledger_tx_id` (bigint nullable), `idempotency_key` (string unique), `created_at`, `updated_at`.

## 3. Akuntansi & Double-Entry Ledger
Konvensi single-entry:
- `Debit` = aset bertambah / kewajiban berkurang
- `Kredit` = aset berkurang / kewajiban bertambah
Pencairan Uang Muka:
- Debit: `mar:farmer_advance:{farmer_id}:IDR`
- Kredit: `wallet:user:{farmer_id}:IDR`
Pinjaman Pakan:
- Debit: `mar:farmer_input_loan:{farmer_id}:IDR`
- Kredit: `mar:feed_issued_clearing:IDR`
Panen Masuk (Bruto):
- Debit: `mar:harvest_inventory:IDR`
- Kredit: `ap:mar:farmer:{farmer_id}:IDR`
Settlement Panen & Net Payout:
- Debit: `ap:mar:farmer:{farmer_id}:IDR` (Nilai Bruto)
- Kredit: `mar:farmer_advance:{farmer_id}:IDR` (Pelunasan DP)
- Kredit: `mar:farmer_input_loan:{farmer_id}:IDR` (Pelunasan Pakan)
- Kredit: `wallet:user:{farmer_id}:IDR` (Net Payout ke Dompet)
