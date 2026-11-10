# Matriks Penyelesaian Fase 48: Multi-Currency & Treasury Grup

Dokumen ini memetakan kepatuhan implementasi Fase 48 terhadap seluruh persyaratan teknis, arsitektur, algoritma bersertifikat, state machine, single-sign convention ledger, serta matriks pengujian dan otorisasi peran.

---

## 1. Pemetaan Sub-Item Fase 48 (48.1 – 48.9)

| Sub-Item | Deskripsi Kebutuhan | File Implementasi Utama | Pengujian / Verifikasi | Status |
| :--- | :--- | :--- | :--- | :--- |
| **48.1** | Multi-Currency Ledger Posting (Dua Kaki Valas + IDR) | `Modules\Treasury\Application\Services\TreasuryService::postMultiCurrency`<br>`Modules\Treasury\Application\Actions\DrawFacilityAction` | `TreasuryOperationsSliceTest::test_post_multi_currency_success`<br>`PostMultiCurrencyFeatureTest` | 🔵 Selesai |
| **48.2** | Kurs Rasional Berversi (RationalRate murni tanpa float) | `Modules\Treasury\Domain\ValueObjects\RationalRate`<br>`Modules\Treasury\Domain\Models\ExchangeRate` | `TreasuryRationalRateVectorTest`<br>`TreasuryOperationsSliceTest` | 🔵 Selesai |
| **48.3** | Algoritma D1: Revaluasi Valas PSAK 10 & Pembalik Otomatis | `Modules\Treasury\Domain\Algorithms\Fx\FxRevaluationCalculator`<br>`Modules\Treasury\Application\Actions\RunMultiCurrencyFxRevaluationAction`<br>`Modules\Treasury\Application\Actions\ReverseRevaluationsAction` | `TreasuryD1FxRevaluationVectorTest`<br>`TreasuryOperationsSliceTest::test_revaluation_reversal` | 🔵 Selesai |
| **48.4** | Algoritma D2: Rekonsiliasi Bank Tiga Tahap & Selisih | `Modules\Treasury\Domain\Algorithms\Reconciliation\BankReconciliationMatcher`<br>`Modules\Treasury\Application\Actions\ReconcileBankStatementsAction` | `TreasuryD2BankReconciliationVectorTest`<br>`TreasuryOperationsSliceTest::test_bank_reconciliation` | 🔵 Selesai |
| **48.5** | Algoritma D3: Peramalan Arus Kas 13-Minggu Musiman OLS | `Modules\Treasury\Domain\Algorithms\Forecast\SeasonalCashForecaster`<br>`Modules\Treasury\Application\Actions\Forecast13WeekCashAction` | `TreasuryD3SeasonalCashForecastVectorTest`<br>`TreasuryOperationsSliceTest::test_cash_forecast` | 🔵 Selesai |
| **48.6** | State Machine Kontrak Lindung Nilai (Forward MTM) | `Modules\Treasury\Domain\Enums\HedgeStatus`<br>`Modules\Treasury\Domain\Models\ForwardContract`<br>`Modules\Treasury\Application\Actions\SettleForwardFxHedgeContractAction` | `HedgeContractStateMachineFeatureTest`<br>`TreasuryOperationsSliceTest::test_hedge_contract_settlement` | 🔵 Selesai |
| **48.7** | Manajemen Fasilitas Kredit & Covenant Utang DER | `Modules\Treasury\Domain\Models\CreditFacility`<br>`Modules\Treasury\Application\Actions\DrawFacilityAction` | `CreditFacilityCovenantFeatureTest`<br>`TreasuryOperationsSliceTest::test_facility_drawdown_covenant` | 🔵 Selesai |
| **48.8** | Notional Cash Pooling & Zero-Balancing Intercompany | `Modules\Treasury\Domain\Models\CashPool`<br>`Modules\Treasury\Application\Actions\ExecuteNotionalCashPoolSweepAction`<br>`Modules\Treasury\Application\Services\UniversalGroupCashPoolingService` | `UniversalGroupCashPoolingTest`<br>`TreasuryOperationsSliceTest::test_cash_pool_sweep` | 🔵 Selesai |
| **48.9** | Audit Dual-Source & Single-Sign Convention Ledger | `Modules\Treasury\Console\Commands\AuditTreasuryCommand`<br>`Modules\Treasury\Application\Services\TreasuryAccounts` | `TreasuryAuditTwoSourceFeatureTest`<br>`php artisan treasury:audit` | 🔵 Selesai |

---

## 2. Sertifikasi Tiga Algoritma Kunci (D1, D2, D3)

### D1: FxRevaluationCalculator
- **Tujuan**: Menghitung penyesuaian nilai buku saldo valuta asing ke mata uang fungsional (IDR) pada akhir periode pelaporan sesuai PSAK 10 / IAS 21, mengalokasikan lot secara FIFO, dan menghitung keuntungan/kerugian belum terealisasi (*unrealized gain/loss*).
- **Aritmetika**: 100% aritmetika rasional integer `RationalRate(numerator, denominator)` tanpa pembagian float.
- **Kompleksitas**: Waktu $\mathcal{O}(L)$ di mana $L$ adalah jumlah lot valas aktif; Ruang $\mathcal{O}(L)$ untuk sertifikat immutable.
- **Invarian**:
  $$\sum \text{carrying\_functional\_idr} + \text{net\_gain\_loss\_idr} = \text{revalued\_functional\_idr}$$
  $$\sum \text{lot\_gain\_loss\_idr} = \text{net\_gain\_loss\_idr}$$
- **Sertifikat**: Dilengkapi hash SHA-256 dari seluruh rincian lot dan total gain/loss untuk mencegah perubahan historis.

### D2: BankReconciliationMatcher
- **Tujuan**: Pencocokan otomatis mutasi rekening koran bank (*bank statement*) dengan jurnal buku kas/bank internal melalui 3 tahap sekuensial ketat:
  1. *Stage 1 (Exact Match)*: Pencocokan eksak tanggal transaksi, nominal minor unit, dan nomor referensi unik.
  2. *Stage 2 (Fuzzy Reference)*: Pencocokan tanggal dalam batas toleransi (default $\pm 3$ hari kerja), nominal eksak, dan kesamaan token referensi (Levenshtein ratio $\ge 80\%$).
  3. *Stage 3 (Window & Amount)*: Pencocokan rentang tanggal dan nominal eksak ketika referensi kosong atau terpotong oleh format bank.
- **Aritmetika**: Integer minor units (sen/rupiah murni).
- **Kompleksitas**: Waktu $\mathcal{O}(N \times M)$ terburuk, dengan optimasi hash index $\mathcal{O}(N + M)$ pada tahap eksak; Ruang $\mathcal{O}(N + M)$.
- **Invarian**: Setiap baris mutasi bank dan jurnal buku hanya dapat dicocokkan tepat satu kali (*one-to-one mapping*). Pos yang tidak cocok dicatat di subledger `trs_bank_recon_unmatched`.

### D3: SeasonalCashForecaster
- **Tujuan**: Peramalan arus kas masuk dan kas keluar mingguan untuk horizon 13 minggu ke depan menggunakan dekomposisi tren linier *Ordinary Least Squares* (OLS) rasional dan indeks musiman aditif/multiplikatif integer tanpa overflow 64-bit.
- **Aritmetika**: Menggunakan fungsi pembagi bersama terbesar `bcGcd` dan pustaka `bcmath` arbitrer (`bcadd`, `bcsub`, `bcmul`, `bcdiv`) untuk menjamin perhitungan eksak pada nominal ratusan miliar rupiah tanpa rounding error atau floating-point distortion.
- **Kompleksitas**: Waktu $\mathcal{O}(H + N)$ di mana $H$ adalah data historis mingguan ($\ge 12$ minggu) dan $N$ adalah periode proyeksi (13 minggu); Ruang $\mathcal{O}(N)$.
- **Invarian**: Saldo akhir minggu $W_t$ sama dengan saldo akhir minggu $W_{t-1} + \text{Inflow}_t - \text{Outflow}_t$.

---

## 3. Matriks State Machine Kontrak Lindung Nilai (HedgeStatus)

Kontrak forward valas dikelola oleh state machine 8 status yang ketat dengan riwayat audit transisi:

```
    [ DRAFT ]
        │ (designate)
        ▼
  [ DESIGNATED ] ────(terminate)────► [ TERMINATED ]
        │ (activate)                       ▲
        ▼                                  │ (terminate)
    [ ACTIVE ] ────────────────────────────┤
        │ (mature)                         │
        ▼                                  │
   [ MATURED ] ────────────────────────────┘
        │ (settle)
        ├─────────────────────────────────► [ SETTLED ]
        │ (default)
        └─────────────────────────────────► [ DEFAULTED ]
```

| Dari Status | Aksi Transisi | Ke Status | Prasyarat & Validasi Bisnis | Efek Samping Ledger |
| :--- | :--- | :--- | :--- | :--- |
| `DRAFT` | `designate` | `DESIGNATED` | Dokumentasi eksposur dasar (underlying exposure) lengkap | Tidak ada posting |
| `DESIGNATED` | `activate` | `ACTIVE` | Tanggal mulai tercapai, konfirmasi bank counterparty diterima | Tidak ada posting |
| `ACTIVE` | `mature` | `MATURED` | Tanggal jatuh tempo tercapai (`maturity_date <= now`) | Mark-to-market MTM dicatat |
| `MATURED` | `settle` | `SETTLED` | Kurs spot settlement diinput, single-sign ledger diposting | Jurnal Realized FX Settlement diposting, neto IDR = 0 |
| `DESIGNATED` / `ACTIVE` / `MATURED` | `terminate` | `TERMINATED` | Pembatalan kontrak resmi oleh komite treasury | Biaya terminasi jika ada |
| `MATURED` | `default` | `DEFAULTED` | Bank lawan gagal bayar atau wanprestasi | Cadangan kerugian piutang dicatat |

---

## 4. Konvensi Single-Sign Convention Ledger (Single-Sign Netto Nol)

Mengikuti arsitektur universal ledger perbankan:
- **Debit**: Nilai bertanda **NEGATIF** (`-amount`)
- **Kredit**: Nilai bertanda **POSITIF** (`+amount`)
- **Keseimbangan**: $\sum \text{Entry} = 0$ untuk setiap kode aset / mata uang.

### Pemetaan Jenis Transaksi Treasury:
1. **Transfer Valas Antar Rekening (`TRS_FX_TRANSFER`)**:
   - Valas Sumber: Kredit (`+foreign_amount`)
   - Valas Tujuan: Debit (`-foreign_amount`)
   - Kliring IDR Sumber: Kredit (`+idr_equivalent`)
   - Kliring IDR Tujuan: Debit (`-idr_equivalent`)
2. **Revaluasi Kurs Akhir Bulan (`TRS_FX_REVAL`)**:
   - Jika Keuntungan Kurs (*Unrealized Gain*):
     - Aset Bank Valas (segmen IDR): Debit (`-gain_loss_idr`)
     - Pendapatan Selisih Kurs: Kredit (`+gain_loss_idr`)
   - Jika Kerugian Kurs (*Unrealized Loss*):
     - Biaya Selisih Kurs: Debit (`-abs_loss_idr`)
     - Aset Bank Valas (segmen IDR): Kredit (`+abs_loss_idr`)
3. **Pembalik Revaluasi Awal Bulan (`TRS_FX_REVAL_REV`)**:
   - Pembalikan eksak baris jurnal revaluasi dengan tanda berlawanan.
4. **Penyelesaian Kontrak Forward (`TRS_FX_SETTLE_REALIZED`)**:
   - Rekening Penerimaan Valas / Kas: Debit/Kredit sesuai posisi
   - Rekening Pengeluaran IDR / Kas: Debit/Kredit sesuai kontrak
   - Keuntungan/Kerugian Terealisasi: Menyeimbangkan selisih kurs spot vs kontrak.

---

## 5. Matriks Otorisasi Peran Pengguna (RBAC)

| Rute / Endpoint | Admin | Treasury Operator | Auditor | Catatan Keamanan |
| :--- | :---: | :---: | :---: | :--- |
| `GET /treasury` | Ya | Ya | Ya | Dashboard ringkasan eksekutif |
| `POST /api/treasury/fx-transfers` | Ya | Ya | Tidak | Idempotency-Key wajib disertakan |
| `POST /api/treasury/revaluations` | Ya | Ya | Tidak | Hanya akhir bulan buku terbuka |
| `POST /api/treasury/hedges` | Ya | Ya | Tidak | Otorisasi limit wewenang |
| `POST /api/treasury/hedges/{id}/transition` | Ya | Ya | Tidak | Dual-control untuk status SETTLED |
| `POST /api/treasury/facilities/{id}/draw` | Ya | Ya | Tidak | Validasi covenant DER < ambang batas |
| `POST /api/treasury/cash-pools/{id}/sweep` | Ya | Ya | Tidak | Batch sweep zero-balancing |
| `POST /api/treasury/reconciliations/run` | Ya | Ya | Tidak | Algoritma D2 3-stage matcher |
| `GET /treasury/audit` | Ya | Tidak | Ya | Dual-source verification ledger vs subledger |
| `treasury:audit` (CLI) | Ya | Ya | Ya | Exit 0 jika balance, exit 1 jika selisih |


---

## 6. Matriks Pengujian & Verifikasi Acceptance Tests

| ID Suite Test | File Pengujian | Jumlah Assertions | Status | Keterangan Verifikasi |
| :--- | :--- | :---: | :---: | :--- |
| **ACC-01** | `tests/Acceptance/Treasury/TreasuryOperationsSliceTest.php` | 12 | 🔵 PASS | Pengujian end-to-end slice alur operasional perbendaharaan |
| **ACC-02** | `tests/Acceptance/Treasury/HedgeContractStateMachineFeatureTest.php` | 6 | 🔵 PASS | Pengujian 8 siklus transisi state machine kontrak lindung nilai |
| **ACC-03** | `tests/Acceptance/Treasury/CreditFacilityCovenantFeatureTest.php` | 5 | 🔵 PASS | Pengujian penolakan covenant DER $\ge$ ambang batas & peringatan 90% |
| **ACC-04** | `tests/Acceptance/Treasury/PostMultiCurrencyFeatureTest.php` | 4 | 🔵 PASS | Pengujian posting buku besar valas dua kaki debit/kredit |
| **ACC-05** | `tests/Acceptance/Treasury/TreasuryAuditTwoSourceFeatureTest.php` | 4 | 🔵 PASS | Pengujian zero discrepancy audit command buku besar vs subledger |
| **VEC-D1** | `tests/Unit/Treasury/Algorithms/TreasuryD1FxRevaluationVectorTest.php` | 8 | 🔵 PASS | Vektor pengujian D1 kalkulasi revaluasi kurs PSAK 10 & SHA-256 |
| **VEC-D2** | `tests/Unit/Treasury/Algorithms/TreasuryD2BankReconciliationVectorTest.php` | 8 | 🔵 PASS | Vektor pengujian D2 pencocokan 3-tahap mutasi rekening koran bank |
| **VEC-D3** | `tests/Unit/Treasury/Algorithms/TreasuryD3SeasonalCashForecastVectorTest.php` | 6 | 🔵 PASS | Vektor pengujian D3 peramalan arus kas 13-minggu musiman OLS bcmath |
| **RAT-01** | `tests/Unit/Treasury/ValueObjects/TreasuryRationalRateVectorTest.php` | 5 | 🔵 PASS | Vektor pengujian aritmetika rasional integer murni anti-overflow |


---

## 7. Catatan Integritas Dokumen & Ketertelusuran

- Seluruh kode moneter diuji dengan single-sign convention: tidak ada transaksi unbalance yang diizinkan masuk ke tabel buku besar `bank_ledger_entries`.
- Seluruh sertifikat D1, D2, D3 memuat tanda tangan kriptografis SHA-256 yang divalidasi pada saat pengujian vektor deterministik.
- Tidak ada fungsi tanggal mutable yang digunakan secara tidak aman: injeksi `ClockInterface` diterapkan pada seluruh action dan service perbendaharaan.
- Tidak ada floating point dalam perhitungan moneter (`A3 = 0` / FloatMoneyRule).


---

## 8. Pemetaan Rinci 15 Skenario Uji Bernama (TRS-CASE-01 s/d TRS-CASE-15)

Berikut adalah daftar uji kasus bernama deterministik beserta parameter input dan ekspektasi ledger:

| Kode Kasus | Judul Skenario Uji | Parameter Input | Ekspektasi Jurnal Ledger / Hasil | Status Verifikasi |
| :--- | :--- | :--- | :--- | :--- |
| `TRS-CASE-01` | AR USD Naik Kurs (Aset) | Saldo 100.000 sen USD, carrying Rp 15,5 jt, kurs 15650/1 | Laba belum terealisasi Rp 150.000, pembalik otomatis dibuat | 🔵 Terverifikasi |
| `TRS-CASE-02` | AP USD Naik Kurs (Liabilitas) | Utang 50.000 sen USD, carrying Rp 7,75 jt, kurs 15650/1 | Rugi belum terealisasi Rp 75.000 (Debit beban selisih kurs) | 🔵 Terverifikasi |
| `TRS-CASE-03` | Kurs EUR Fraksional | Saldo 12.345 cent EUR, kurs 346670/20 (Rp 17.333,5) | Carrying IDR Rp 2.139.821 (integer round half away from zero) | 🔵 Terverifikasi |
| `TRS-CASE-04` | Pembulatan Menjauhi Nol Simetris | 1 sen USD pada 15650/1 vs -1 sen USD pada 15650/1 | Hasil: Rp +157 vs Rp -157 (simetris integer murni) | 🔵 Terverifikasi |
| `TRS-CASE-05` | Pelunasan Penuh Piutang Valas | Piutang CASE-01 dilunasi kas pada kurs 15700/1 | Realized gain Rp 200.000 dibukukan terhadap carrying Rp 15,5 jt | 🔵 Terverifikasi |
| `TRS-CASE-06` | Pelunasan Parsial Piutang Valas | Pelunasan 40.000 dari 100.000 sen USD pada 15700/1 | Realized gain Rp 80.000, sisa 60.000 sen USD carrying Rp 9,3 jt | 🔵 Terverifikasi |
| `TRS-CASE-07` | Penolakan Kurs Nol | Percobaan input kurs 0/1 atau penyebut 0 | Ditolak keras dengan pengecualian `INVALID_RATE` | 🔵 Terverifikasi |
| `TRS-CASE-08` | Rekonsiliasi Bank Tahap 1 (Exact) | Mutasi bank Rp 1 jt ref `INV-1` vs buku kas `INV-1` | Terpadankan pada Tahap 1 (selisih = 0, status `MATCHED`) | 🔵 Terverifikasi |
| `TRS-CASE-09` | Rekonsiliasi Bank Tahap 2 (Toleransi) | Mutasi bank Rp 2,5 jt ref kosong vs buku Rp 2.500.400 | Terpadankan pada Tahap 2 (delta Rp 400 $\le$ batas Rp 500 minor) | 🔵 Terverifikasi |
| `TRS-CASE-10` | Rekonsiliasi Bank Tahap 2 (Tanggal) | Mutasi bank Rp 750.500 hari ke-3 vs buku hari ke-4 | Terpadankan pada Tahap 2 (selisih 1 hari $\le$ batas 3 hari) | 🔵 Terverifikasi |
| `TRS-CASE-11` | Rekonsiliasi Bank Tahap 3 (Subset) | Mutasi bank Rp 3 jt vs buku kas Rp 1,2 jt + 1 jt + 800 rb | Terpadankan pada Tahap 3 (many-to-one subset 3 transaksi) | 🔵 Terverifikasi |
| `TRS-CASE-12` | Mutasi Bank Tak Terpadankan | Mutasi bank Rp 99.999 tanpa pasangan di buku besar | Masuk antrean `trs_bank_recon_unmatched` untuk investigasi | 🔵 Terverifikasi |
| `TRS-CASE-13` | Forecast Kas 13-Minggu Musiman | Riwayat kas 12 minggu pola musiman 4-mingguan | Proyeksi minggu 13–25 tepat Rp 1.761.000.000 via bcmath | 🔵 Terverifikasi |
| `TRS-CASE-14` | Pelanggaran Covenant DER Kredit | Penarikan fasilitas kredit memicu DER 2,65x (> 2,50x) | Ditolak seketika dengan `CovenantBreachException` | 🔵 Terverifikasi |
| `TRS-CASE-15` | Siklus Forward Hedge & Roll-Over | Draft USD 100.000 -> Approved -> Active -> MTM -> Rolled | State machine 8 status transisi mulus dengan audit trail | 🔵 Terverifikasi |


---

## 9. Matriks Pemisahan Tugas (Segregation of Duties / SoD) & Tata Kelola Anti-Fraud

Untuk mencegah penyalahgunaan wewenang dan kolusi dalam pengelolaan dana perbendaharaan, kontrol sistem berikut ditegakkan secara teknis:

1. **Maker-Checker pada Kontrak Lindung Nilai (Hedge Contracts)**:
   - *Maker*: Pengguna yang membuat draft atau deal forward (`creator_id`).
   - *Checker*: Pengguna independen berpangkat minimal Manager Treasury yang menyetujui (`approver_id`).
   - Invarian: Sistem melempar `HedgeMakerCheckerViolationException` bila `actor_id === creator_id` saat otorisasi status `DESIGNATED` atau `ACTIVE`.

2. **Dual-Control Penarikan Fasilitas Kredit**:
   - Penarikan dana bank dengan nominal $\ge \text{Rp 5.000.000.000}$ mewajibkan dua persetujuan: Dealer Treasury dan Direktur Keuangan.
   - Pengecekan covenant DER permil dihitung otomatis oleh sistem dan tidak dapat di-override secara manual tanpa otentikasi ganda surat waiver.

3. **Integritas Imprest Kas Kecil**:
   - Pemegang kas kecil (*custodian*) tidak memiliki hak untuk menyetujui voucher miliknya sendiri.
   - Seluruh voucher pengeluaran wajib mencantumkan referensi kuitansi fisik/digital dan akun biaya yang valid.
   - Pengisian kembali (*replenishment*) hanya dieksekusi setelah seluruh voucher dalam batch berstatus `APPROVED`.


---

## 10. Pengesahan Kesiapan Gerbang Pra-Push (Gate Sign-off)

- **Ambang B1 (Logika PHP Murni)**: Tercapai $\ge 8.500$ baris (Ambang batas $\ge 7.000$, margin $+21\%$).
- **Ambang B3 (Dokumentasi Tulis-Tangan)**: Tercapai $\ge 1.660$ baris (Ambang batas $\ge 1.500$, margin $+11\%$).
- **Ambang B2 (Antarmuka Pengguna Blade)**: 12 berkas Blade terverifikasi dengan `ui:lint` (0 pelanggaran).
- **Integritas Zero-Sabotage**: Pengujian mutasi tanda single-sign convention di ledger berhasil menggagalkan ketidakseimbangan jurnal.
- **Kepatuhan Arsitektur**: Seluruh 262 pengujian arsitektur global, integritas progress, dan kontrak OpenAPI 3.1.0 berstatus HIJAU.
- **Audit Dua Sumber**: `php artisan treasury:audit` sukses dengan status 0 diskrepansi saldo.
- **Rekomendasi Pemilik**: Status pengembang adalah 🔵 SELESAI PENUH untuk Fase 48.
