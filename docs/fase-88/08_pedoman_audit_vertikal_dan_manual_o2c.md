# Pedoman Audit Vertikal Siklus Pendapatan & Manual Konsol O2C

## 1. Pedoman Pelaksanaan Audit Vertikal Dua-Sumber (Two-Source Vertical Audit)

Audit Vertikal Siklus Pendapatan (*Revenue Cycle Vertical Audit*) adalah mekanisme kendali mutu internal yang memverifikasi integritas finansial dan kepatuhan regulasi penagihan rumah sakit. Prinsip "Dua-Sumber" (*Two-Source Integrity*) membandingkan secara independen antara:
1. **Sumber Operasional Pelayanan Klinis**: Rincian transaksi pada tabel `FolioItem`, pesanan resep farmasi, catatan tindakan dokter, dan rekam medis elektronik.
2. **Sumber Buku Besar Akuntansi**: Catatan penagihan pada `BillingEpisode`, alokasi `ClaimWaterfall`, jurnal buku besar `HospitalAccounts`, dan rekening koran mutasi perbankan.

```
+---------------------------------------------------------------------------------------------------+
|                               VERIFIKASI INVARIAN AUDIT DUA-SUMBER                                |
+---------------------------------------------------------------------------------------------------+

   +------------------------+                    +------------------------+
   |   Sumber 1: Operasional|                    |    Sumber 2: Akuntansi |
   | - Folio Items          |                    | - BillingEpisode       |
   | - Koding Tindakan Medis|                    | - ClaimWaterfall       |
   | - Telemetri Cold Chain |                    | - General Ledger COA   |
   +------------------------+                    +------------------------+
                \                                            /
                 \                                          /
                  v                                        v
          +--------------------------------------------------------+
          |         MESIN AUDIT VERTIKAL (hsp:audit / RCM)         |
          |                                                        |
          |  1. INV-88-01: Sum(Folio.subtotal) == Episode.charges  |
          |  2. INV-88-02: Sum(Waterfall.covered) == Episode.total|
          |  3. INV-88-03: Cold Chain Excursion -> Quarantined     |
          |  4. INV-88-04: Festronik SHA-256 Hash Chain Valid      |
          +--------------------------------------------------------+
                                     |
                                     v
                   [Lolos Tanpa Selisih (Exit 0) / HEALTHY]
```

---

## 2. Definisi Empat Invarian Inti Fase 88 (INV-88-01 s/d INV-88-04)

### Invarian INV-88-01: Integritas Matematis Penjumlahan Folio (*Folio Sum Conservation*)
- **Pernyataan Teorema**: Untuk setiap episode penagihan yang telah ditutup (`CLOSED`, `VERIFIED_CODER`, atau `SETTLED`), jumlah matematis dari seluruh baris rincian item tagihan (`FolioItem`) wajib sama persis dengan total tagihan tercatat (`total_charges_idr`):
  $$\sum_{j=1}^{m} \text{FolioItem}_{j}.\text{subtotal\_idr} \equiv \text{BillingEpisode}.\text{total\_charges\_idr}$$
- **Deteksi Anomali**: Jika ditemukan selisih nilai walau hanya 1 rupiah, mesin audit menandai insiden `ANOMALY_FOLIO_MISMATCH` dan memblokir penerbitan kuitansi pelunasan.

### Invarian INV-88-02: Keseimbangan Alokasi Waterfall (*CoB Conservation Law*)
- **Pernyataan Teorema**: Untuk setiap episode penagihan yang telah mencapai status `SETTLED`, total nilai penjaminan yang dialokasikan ke penjamin BPJS, asuransi komersial (K31), pembayaran tunai pasien, dan subsidi diskon wajib sama dengan nilai total tagihan bruto:
  $$\text{BPJS Covered} + \text{Private Insurer Covered} + \text{Patient Cash} + \text{Charity Subsidy} \equiv \text{Total Charges}$$
- **Deteksi Anomali**: Jika jumlah penjaminan kurang dari total tagihan dan tidak terdapat dana deposit *escrow* penutup selisih, sistem memicu peringatan `ANOMALY_UNDERFUNDED_SETTLEMENT`.

### Invarian INV-88-03: Kepatuhan Karantina Rantai Dingin (*Cold-Chain Breach Protocol*)
- **Pernyataan Teorema**: Jika lemari pendingin farmasi mengalami ekskursi suhu kritis ($T > 12^\circ\text{C}$ atau $T < 1^\circ\text{C}$), seluruh nomor lot obat di unit tersebut wajib berstatus `is_quarantined = true`. Tidak boleh ada obat dari lot terinfeksi yang dapat dikeluarkan (*dispensed*) tanpa protokol pembebasan resmi.

### Invarian INV-88-04: Keutuhan Rantai Hash Kustodi Festronik (*Festronik Immutability*)
- **Pernyataan Teorema**: Rantai hash SHA-256 pada tabel `hsp_waste_custody_logs` dari ruangan asal, TPS internal, hingga pengangkut transporter berizin wajib terhubung secara kriptografis tanpa ada catatan yang disunting ulang secara sepihak.

---

## 3. Matriks Penanganan Penolakan Klaim (Claim Denial Codes D01 - D06)

Layanan `ClaimDenialManagementEngine` dan `InaCbgDisputeResolutionEngine` memetakan kode penolakan verifikator BPJS/TPA ke dalam rencana aksi mitigasi:

| Kode Denial | Kategori Alasan Penolakan | Definisi Penolakan Verifikator | Rencana Aksi Sanggahan / Mitigasi |
| :---: | :--- | :--- | :--- |
| **D01** | *Coding Diagnosis Inaccuracy* | Diagnosis utama dianggap tidak didukung bukti klinis objektif | Susun Berkas Banding TKMKB: Lampirkan hasil laboratorium, CT Scan/EKG, dan resume medis DPJP |
| **D02** | *Unbundled Procedure Discrepancy* | Verifikator menganggap prosedur bedah merupakan fragmentasi dari prosedur utama | Evaluasi laporan operasi rinci: Buktikan bahwa tindakan dilakukan pada sayatan/organ berbeda |
| **D03** | *Medical Indication Admission* | Indikasi rawat inap dianggap tidak darurat / dapat rawat jalan | Lampirkan lembar triase IGD, tanda vital hemodinamik tidak stabil, dan kriteria kelaikan admisi PPK |
| **D04** | *Non-Formulary Medication* | Penggunaan obat antibiotik lini lanjutan dianggap di luar restriksi Fornas | Lampirkan lembar uji resistensi kultur mikrobiologi dan persetujuan Komite Farmasi dan Terapi |
| **D05** | *Administrative Eligibility Defect* | Surat Rujukan FKTP kedaluwarsa atau masa berlaku kepesertaan pasif | Koordinasi dengan petugas BPJS Care Center untuk pembaruan eligibilitas online (SEP online) |
| **D06** | *Lapsed Insurance Policy* | Polis asuransi swasta tidak aktif saat tanggal perawatan | Tagihkan langsung ke pasien sebagai pembayaran mandiri Out-of-Pocket atau ajukan subsidi charity |

---

## 4. Manual Perintah Konsol Artisan (Hospital CLI Reference)

Modul `Hospital` menyediakan lima perintah CLI Artisan untuk otomasi audit dan pemantauan sistem di lini produksi:

### 1. `php artisan hsp:audit`
Melakukan pemeriksaan audit dua-sumber menyeluruh terhadap seluruh episode penagihan, validitas waterfall, kepatuhan rantai dingin, dan rantai hash Festronik.
- **Output Sukses**: Menampilkan rekapitulasi audit dan pesan `Hospital audit PASSED with 0 discrepancy`. Exit code 0.
- **Output Gagal**: Menampilkan daftar ID episode yang mengalami inkonsistensi saldo dan rincian anomali. Exit code 1.

### 2. `php artisan hsp:cold-chain-monitor`
Melakukan pemindaian terhadap seluruh unit pendingin farmasi dan probe sensor IoT yang aktif.
- Opsi: `--alert-only` hanya menampilkan unit yang mengalami deviasi suhu melebihi toleransi.
- Menampilkan nilai Mean Kinetic Temperature (MKT) dan status karantina stok obat.

### 3. `php artisan hsp:claim-aging`
Menghasilkan laporan umur piutang (*AR Aging Report*) per penjamin (BPJS, Korporat, Asuransi Swasta, Pasien Umum).
- Menghitung cadangan penurunan nilai piutang ragu (*Expected Credit Loss* / ECL) secara otomatis sesuai PSAK 71.

### 4. `php artisan hsp:festronik-audit`
Melakukan audit kriptografis terhadap seluruh manifes limbah medis B3 yang aktif di TPS rumah sakit.
- Memverifikasi apakah terdapat limbah yang melampaui batas waktu simpan 48 jam di suhu ruang atau 90 hari di suhu dingin.
- Memeriksa keutuhan rantai hash SHA-256 untuk mendeteksi potensi pemalsuan berat limbah.

### 5. `php artisan hsp:o2c-kpi`
Menghitung indikator kinerja utama Revenue Cycle Management (DSO, CCR, IDR, NCR).
- Contoh penggunaan:
  ```bash
  php artisan hsp:o2c-kpi --gross=10000000000 --collected=9200000000 --receivables=1500000000 --total-claims=500 --clean-claims=460 --denied-claims=25
  ```
- Menampilkan tabel komprehensif metrik RCM beserta status benchmark industri rumah sakit.

