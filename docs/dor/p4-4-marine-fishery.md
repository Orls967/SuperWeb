# Definition of Ready (DoR) — [G6-P4.4] Lini 22: Perikanan, Aquaculture & Marine Supply Chain (Fase 170)

## 1. Identifikasi Dokumen & Konteks
- **ID Task**: G6-P4.4
- **Fase PROGRESS**: Fase 170 (170.1 – 170.6) — Lini 22: Perikanan, Aquaculture & Marine Supply Chain
- **Modul**: `Marine` (modul baru, prefiks tabel `mar_`)
- **Penanggung Jawab**: Pelaksana [G6-P4]
- **Target Status**: 🔵 SIAP VERIFIKASI (dilarang memberi ✅)

## 2. Latar Belakang & Masalah Bisnis
- Berdasarkan audit arsitektur (`PROGRESS.md` Fase 170), kode lama berupa service `DB::table()` mentah `MarineAquacultureService` di modul `Integration` tanpa rute/UI/command/ledger yang sah.
- Sesuai cetak biru `KONSEP.md` §A1 dan Lini 22:
  - Modul baru `Marine` (`mar_`) berfokus pada budidaya perikanan/marikultur, siklus tebar benih, log pakan, panen, grading, dan kalkulasi FCR rasional.
  - Kontrak pembudidaya dan pencairan pembiayaan/uang muka, pinjaman pakan input, serta settlement panen wajib terintegrasi penuh ke akuntansi double-entry Ledger (`Modules\Marine\Ledger\MarineAccounts`).
  - Dilarang float untuk nilai uang dan kuantitas bobot/pakan (menggunakan integer gram dan integer rupiah/minor-unit).
  - Formula analitik budidaya:
    - Survival rate (%) = $(\text{ekor panen} / \text{ekor tebar}) \times 100\%$
    - Panen total (kg) = $(\text{ekor panen} \times \text{bobot rata-rata gram}) / 1000$
    - Biomasa awal (kg) = $(\text{ekor tebar} \times \text{bobot awal gram}) / 1000$
    - Feed Conversion Ratio (FCR) = $\text{Total Pakan (kg)} / (\text{Total Panen (kg)} - \text{Biomasa Awal (kg)})$
    - Nilai Bruto Panen = $\sum (\text{kg grade} \times \text{harga per kg})$
    - Net Payout Pembudidaya = Bruto Panen - Uang Muka Petani - Pinjaman Pakan Input

## 3. Spesifikasi Vektor Angka Uji Wajib (Prompt)
- **Tebar**: 10.000 ekor @ 2 g $\to$ biomasa awal = $10.000 \times 2 / 1000 = 20\text{ kg}$.
- **Survival**: 85% $\to$ 8.500 ekor hidup.
- **Bobot rata-rata saat panen**: 250 g.
- **Total panen (kg)**: $8.500 \times 250 / 1000 = 2.125\text{ kg}$.
- **Pakan terpakai**: 2.800 kg.
- **FCR**: $2.800 / (2.125 - 20) = 2.800 / 2.105 \approx 1,330166...\text{ kg pakan/kg daging} \to 1,330$.
- **Grading & Harga**:
  - Grade A: 425 kg $\times$ Rp75.000 = Rp31.875.000
  - Grade B: 1.275 kg $\times$ Rp60.000 = Rp76.500.000
  - Grade C: 425 kg $\times$ Rp40.000 = Rp17.000.000
  - Total Bruto = Rp125.375.000
- **Potongan**:
  - Uang Muka Pembudidaya: Rp20.000.000
  - Pinjaman Pakan: Rp28.000.000
- **Net Payout Pembudidaya**: Rp125.375.000 - Rp20.000.000 - Rp28.000.000 = Rp77.375.000.

## 4. Alur Double-Entry Ledger (`MarineAccounts`)
1. **Pencairan Uang Muka**:
   - Debit: `mar:farmer_advance:{farmer_id}:IDR` (Asset/Piutang)
   - Kredit: `wallet:user:{farmer_id}:IDR` (Liability/Dompet Petani)
2. **Penyaluran Pinjaman Pakan**:
   - Debit: `mar:farmer_input_loan:{farmer_id}:IDR` (Asset/Piutang Pakan)
   - Kredit: `mar:feed_issued_clearing:IDR` (Clearing Persediaan Pakan)
3. **Penerimaan Panen (Bruto)**:
   - Debit: `mar:harvest_inventory:IDR` (Asset/Persediaan Ikan)
   - Kredit: `ap:mar:farmer:{farmer_id}:IDR` (Liability/Hutang Pembelian Hasil)
4. **Settlement Panen & Net Payout**:
   - Debit: `ap:mar:farmer:{farmer_id}:IDR` (sebesar nilai bruto, Rp125.375.000)
   - Kredit: `mar:farmer_advance:{farmer_id}:IDR` (pelunasan uang muka, Rp20.000.000)
   - Kredit: `mar:farmer_input_loan:{farmer_id}:IDR` (pelunasan pakan, Rp28.000.000)
   - Kredit: `wallet:user:{farmer_id}:IDR` (sisa bersih ke dompet petani, Rp77.375.000)
   - Total Debit = Total Kredit = Rp125.375.000 ($\Sigma=0$ terpenuhi).

## 5. Kriteria Penerimaan (Acceptance Criteria)
1. **AC-1 Modul & Registrasi**: Modul `Marine` didaftarkan di `config/modules.php`, `bootstrap/providers.php`, `KONSEP.md` §A1, dan `config-allowlists.json`.
2. **AC-2 Skema Basis Data (`mar_*`)**: Tabel `mar_ponds`, `mar_cycles`, `mar_feed_logs`, `mar_harvests`, `mar_payouts`.
3. **AC-3 Presisi Rasional & Integer**: Tidak ada float untuk nominal rupiah maupun kuantitas gram/bobot panen.
4. **AC-4 Double-Entry & Ledger Audit**: Akun `MarineAccounts` didaftarkan di `LedgerAccountsSeeder`. Audit `mar:audit` mencocokkan subledger mar_payouts dan saldo ledger pembudidaya.
5. **AC-5 Seeder Skala T1**: `MarineFisheryT1Seeder` deterministik (100 kolam, 50 siklus).
6. **AC-6 Pengujian Penerimaan**: `MarineFisheryAcceptanceTest` (maksimal 6 test) menguji RBAC/IDOR, kalkulasi FCR dan grading, settlement ledger, seeder T1, dan audit dua sumber dengan fixture sabotase.
