# Panduan Pengguna — Auditor Grup & Komite Audit (Group Auditor)

**Modul**: `Modules\Intercompany` (Fase 52)  
**Sasaran Pengguna**: Auditor Internal Grup, Tim Kepatuhan Keuangan, Auditor Eksternal Independen  
**Tanggal Efektif**: 2026-11-12

---

## 1. Metodologi Audit Dua Sumber Terpadu (Dual-Source Audit)

Sistem Intercompany Fase 52 menerapkan metodologi audit dua sumber secara ketat untuk mendeteksi setiap bentuk manipulasi, asimetri pencatatan, atau diskrepansi saldo:
1. **Sumber Primer (Subledger)**: Data operasional pada modul intercompany (`ic_transactions`, `ic_loans`, `ic_ownership_links`, `ic_inter_entity_transfers`).
2. **Sumber Sekunder (General Ledger)**: Saldo buku besar pada modul Banking/Ledger (`bank_ledger_transactions`, `bank_accounts`).

Auditor menjalankan audit menyeluruh menggunakan perintah:
```bash
php artisan group:audit
```

Perintah ini secara otomatis menguji 5 invarian inti:
- **Invarian 1 (Identitas Entitas)**: Transaksi penjualan dan pembelian dilarang melibatkan entitas legal yang sama ($A \neq B$).
- **Invarian 2 (Keseimbangan Eliminasi)**: Setiap jurnal eliminasi konsolidasi wajib memiliki nominal positif ($> 0$) dan pos akun debit tidak boleh sama dengan akun kredit.
- **Invarian 3 (Integritas Amortisasi Pinjaman)**: Akumulasi pokok pada jadwal angsuran wajib tepat sama dengan nilai pokok pinjaman resmi ($\sum \text{Principal Due} = \text{Loan Principal}$).
- **Invarian 4 (Batas Rasio Cost Sharing)**: Total alokasi pembagian biaya bersama tidak boleh melebihi $100\%$ ($100.000\text{ permille}$).
- **Invarian 5 (Asimetri Bilateral & Pos Gantung)**: Menjalankan pemindaian otomatis terhadap transaksi `posted_seller` tanpa dokumen tagihan lawan atau selisih nominal mata uang fungsional.

Bila terdeteksi pelanggaran invarian, perintah keluar dengan status non-nol (`exit 1`) dan mencatat rincian diskrepansi ke sistem log.

---

## 2. Penyisiran Selisih Asimetris Bilateral (`group:mismatch-sweep`)

Pos gantung (*floating unbilled balances*) adalah sumber utama ketidakseimbangan laporan konsolidasi.
Jalankan penyisiran selisih secara mandiri:
```bash
php artisan group:mismatch-sweep --threshold=0
```

### Analisis Hasil Penyisiran:
- **Status KELUAR 0 (Hijau)**: Seluruh saldo timbal balik antar-entitas berada dalam kondisi seimbang sempurna, setiap tagihan penjual telah dicocokkan dengan tagihan pembeli, dan tidak ada deviasi kurs yang melebihi batas toleransi.
- **Status KELUAR 1 (Merah)**: Ditemukan diskrepansi bilateral. Sistem menampilkan tabel rincian:
  - `UNBILLED_POSTED_TRANSACTION`: Transaksi telah diakui oleh pihak penjual namun belum diterima/diposting oleh pihak pembeli.
  - `FUNCTIONAL_AMOUNT_MISMATCH`: Terdapat selisih antara nominal mata uang transaksi dan nilai ekuivalen mata uang fungsional IDR.
  - `LOAN_SCHEDULE_MISMATCH`: Terdapat ketidaksesuaian antara pokok perjanjian pinjaman dengan jadwal jatuh tempo angsuran.

Setiap anomali otomatis memicu pemancaran domain event `intercompany.mismatch.detected.v1` yang tercatat dalam audit trail.

---

## 3. Verifikasi Integritas Penguncian Periode Konsolidasi

Setelah Holding CFO melakukan penguncian periode konsolidasi melalui perintah `group:consolidation-lock`:
1. **Pemeriksaan Event Domain**:
   Verifikasi bahwa event `intercompany.consolidation.locked.v1` telah dipancarkan dengan muatan data lengkap:
   - `fiscalPeriod`: Periode pelaporan (misal `2026-10`).
   - `lockedByUserId`: Identitas pejabat yang mengunci.
   - `totalEliminatedMinor`: Total nilai rupiah saldo resiprokal yang dieliminasi.
   - `lockedAt`: Waktu ISO-8601 penguncian.
2. **Validasi Tanda Tangan Kriptografis (Audit Seal)**:
   Setiap hasil konsolidasi memuat kode hash integritas SHA-256 yang dihitung dari kombinasi:
   `run_id | fiscal_period | holding_code | consolidated_assets | consolidated_liabilities | net_profit | journal_count`.
   Jika terjadi perubahan data retrospektif pada tabel jurnal eliminasi atau neraca lajur entitas, penghitungan ulang hash akan menghasilkan nilai berbeda (*hash mismatch*), menandakan adanya korupsi data.

---

## 4. Pengujian Sabotase & Uji Ketahanan Sistem

Auditor dapat memverifikasi ketahanan sistem melalui uji sabotase otomatis yang disediakan dalam test suite:
- **Sabotase 1: Transaksi Asimetris Buatan**: Membuat transaksi penjualan intra-grup dengan `purchase_bill_ref = null` dan status `posted_seller`. Sistem wajib menolak audit (`group:audit` exit 1) dan memancarkan peringatan mismatch.
- **Sabotase 2: Modifikasi Nilai Tanpa Hak Otorisasi**: Mencoba memasukkan nilai desimal (*float*) atau transaksi bersaldo negatif. Sistem wajib melempar eksepsi integritas moneter.
- **Sabotase 3: Kepemilikan Saham Sirkular**: Memasukkan relasi kepemilikan loop ($A \to B \to C \to A$). Sistem melalui `GroupEntityRegistry` wajib menolak registrasi dan melempar `InvalidArgumentException`.

---

## 5. Kertas Kerja Audit Eliminasi & Lembar Pemeriksaan (Audit Checklist)

Sebelum menandatangani opini audit laporan keuangan konsolidasian grup, auditor wajib melengkapi checklist kepatuhan berikut:

### 5.1 Lembar Pemeriksaan Eliminasi Konsolidasi (Consolidation Elimination Checklist)
1. [ ] **Verifikasi Saldo Piutang/Utang Usaha**: Seluruh saldo piutang dan utang intra-grup telah dieliminasi 100% tanpa menyisakan saldo gantung di neraca konsolidasi.
2. [ ] **Verifikasi Saldo Pinjaman & Bunga**: Saldo pokok pinjaman intra-grup dan akrual bunga telah dieliminasi simetris antara entitas kreditur dan debitur.
3. [ ] **Verifikasi Penjualan & Pembelian**: Nilai penjualan intra-grup telah dieliminasi terhadap beban pokok penjualan entitas pembeli.
4. [ ] **Verifikasi Laba Belum Terealisasi**: Keuntungan atas persediaan akhir yang belum terjual ke pihak ketiga telah dikeluarkan dari nilai persediaan dan saldo laba konsolidasian.
5. [ ] **Verifikasi Pajak Tangguhan**: Aset pajak tangguhan (DTA) sebesar tarif pajak efektif dikalikan laba belum terealisasi telah diakui secara tepat.
6. [ ] **Verifikasi Alokasi NCI**: Porsi laba bersih dan ekuitas yang diatribusikan kepada pemegang saham non-pengendali telah dihitung sesuai persentase kepemilikan efektif.
7. [ ] **Verifikasi Translasi Valas**: Selisih translasi laporan keuangan entitas anak asing telah dialokasikan dengan benar ke pos ekuitas FCTR dan bukan ke laba rugi berjalan.
8. [ ] **Verifikasi Bukti Kunci**: Hash integritas konsolidasi tersimpan dan cocok dengan kalkulasi independen.

