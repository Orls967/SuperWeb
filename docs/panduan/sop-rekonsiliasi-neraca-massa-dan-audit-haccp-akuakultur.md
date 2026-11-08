# Prosedur Operasional Standar (SOP): Rekonsiliasi Neraca Massa Panen ASC/MSC & Audit HACCP Cold Storage (Fase 171)

Dokumen Standar Operasional Prosedur (SOP) ini menetapkan pedoman wajib bagi manajemen tambak, kepala fasilitas pengolahan (*processing plant*), analis penjaminan mutu (*Quality Assurance*), dan auditor internal perikanan dalam melaksanakan pemantauan titik kendali kritis (*Critical Control Points* / CCP), perhitungan rendemen konversi biomassa, serta mitigasi risiko kontaminasi biologis dan kimia.

---

## 1. Landasan Regulasi & Acuan Standar Internasional

1. **ASC Farm Standard v1.3 & MSC Chain of Custody Standard v5.0**: Standar keterlacakan rantai pengawasan dari tambak tersertifikasi hingga produk akhir siap konsumsi.
2. **US FDA 21 CFR Part 123 (Fish and Fishery Products Hazards and Controls Guidance)**: Regulasi titik kendali kritis bahaya histamin, patogen, dan obat-obatan hewan budidaya.
3. **EU Commission Regulation (EC) No 853/2004 & No 2019/1871**: Standar kebersihan bahan pangan asal hewan dan batas residu terendah (*action limits*).
4. **Keputusan Menteri Kelautan dan Perikanan RI No. 52A/KEPMEN-KP/2013**: Persyaratan jaminan mutu dan keamanan hasil perikanan pada proses produksi, pengolahan, dan distribusi.

---

## 2. Prosedur Rekonsiliasi Neraca Massa (*Mass Balance Reconciliation*)

Prinsip dasar neraca massa mewajibkan bahwa total berat komoditas olahan yang diklaim sebagai produk tersertifikasi ramah lingkungan atau bersertifikat ekspor tidak boleh melebihi konversi fisik aktual dari bahan baku basah hasil panen.

### 2.1 Formula Rendemen Fisik Maksimum Berdasarkan Produk Akhir
Tabel rendemen standar konversi udang vaname (*Litopenaeus vannamei*):

| Jenis Potongan Produk | Kode Spesifikasi | Batas Toleransi Rendemen (% Berat Utuh) | Ambang Peringatan Surplus |
|---|---|---|---|
| *Head-On Shell-On* (HOSO) | `PROD_HOSO` | 98.0% – 100.0% | > 101.5% (Tolak Lot / Pelanggaran) |
| *Headless Shell-On* (HLSO) | `PROD_HLSO` | 63.0% – 66.5% | > 67.5% (Investigasi Pencampuran) |
| *Peeled Tail-On* (PTO) | `PROD_PTO` | 58.0% – 61.5% | > 62.5% (Investigasi Pencampuran) |
| *Peeled Deveined Tail-Off* (PDTO) | `PROD_PDTO` | 50.0% – 54.0% | > 55.0% (Investigasi Pencampuran) |
| *Cooked Peeled Tail-On* | `PROD_CPTO` | 46.0% – 49.5% | > 50.5% (Investigasi Pencampuran) |

```
Rendemen Aktual (%) = (Berat Bersih Produk Beku Olahan [kg] / Total Berat Panen Basah Diterima [kg]) × 100%
```

### 2.2 Langkah Pemeriksaan Audit Neraca Massa Harian
1. **Penerimaan Bahan Baku (Weigh-in)**:
   - Petugas penimbangan menimbang keranjang panen basah di pintu penerimaan (*receiving dock*).
   - Pengurangan tara air es (*ice tare deduction*) ditetapkan maksimal 8% dari berat kotor penimbangan.
   - Angka penimbangan bersih dicatat secara otomatis ke dalam buku besar `mar_aquaculture_ledger_entries` dengan transaksi bertipe `HARVEST_YIELD_INTAKE`.
2. **Evaluasi Otomatis via Algoritma A15**:
   - Sistem memanggil `Modules\Marine\Domain\Algorithms\AquacultureMassBalanceVerifier`.
   - Apabila rasio melebihi batas maksimum rendemen, sistem membekukan status penerbitan nomor lot ekspor dan mengeluarkan sinyal `ERR_AQUA_MASS_BALANCE_BREACH`.
   - Tindakan korektif: Kepala QC wajib melakukan penimbangan ulang fisik 100% dari kemasan palet sebelum investigasi diselesaikan dalam waktu 12 jam.

---

## 3. Titik Kendali Kritis (CCP) HACCP Pengolahan & Cold Storage

Dalam rantai proses pengolahan udang ekspor beku, ditetapkan 3 Titik Kendali Kritis (CCP) wajib:

```
[Penerimaan Udang Segar] 
          │
          ▼
   [CCP 1: Residu Kimia & Antibiotik] ──(Lolos)──► [Sortasi & Pemotongan]
          │                                                   │
      (Gagal)                                                 ▼
          │                                      [CCP 2: Pembekuan Cepat IQF]
          ▼                                                   │
   [Karantina / Pemusnahan]                                   ▼
                                                 [CCP 3: Deteksi Logam Kemasan]
                                                              │
                                                              ▼
                                                 [Cold Storage (-18°C Wajib)]
```

### 3.1 CCP 1: Pemeriksaan Residu Kimia & Antibiotik (Receiving)
- **Bahaya Utama**: Kontaminasi antibiotik spektrum luas (Kloramfenikol, Nitrofuran metabolit AOZ/AMOZ, Fluorokuinolon, Malachite Green).
- **Batas Kritis (Critical Limit)**:
  - Kloramfenikol: Tidak Terdeteksi (*Zero Tolerance*, Limit of Detection < 0.3 ppb / μg/kg).
  - Metabolit Nitrofuran: Limit of Quantification < 0.5 ppb.
  - Tetrasiklin: < 100 ppb (Pasar AS), < 100 ppb (Pasar Uni Eropa).
- **Prosedur Pemantauan**:
  - Setiap batch panen diambil sampel representatif sebanyak 500 gram (minimum 25 ekor udang acak).
  - Pengujian cepat menggunakan kit uji enzyme-linked immunosorbent assay (ELISA) di laboratorium in-house tambak.
  - Konfirmasi kuantitatif dilakukan melalui pengujian Liquid Chromatography-Tandem Mass Spectrometry (LC-MS/MS) pada laboratorium rujukan eksternal terakreditasi ISO/IEC 17025.

### 3.2 CCP 2: Pembekuan Kontak Cepat (Blast Freezing / IQF)
- **Bahaya Utama**: Perkembangbiakan bakteri pembusuk dan enteropatogen (*Vibrio cholerae*, *Vibrio parahaemolyticus*, *Salmonella enterica*).
- **Batas Kritis (Critical Limit)**:
  - Suhu pusat produk (*core product temperature*) harus mencapai $\le -18.0^\circ\text{C}$ dalam waktu kurang dari 90 menit di terowongan pembekuan (*freezing tunnel*).
- **Frekuensi Pemantauan**:
  - Pemeriksaan suhu inti menggunakan termometer tusuk digital berkalibrasi setiap 30 menit per batch pembekuan.
  - Toleransi kalibrasi termometer: $\pm 0.2^\circ\text{C}$.

### 3.3 CCP 3: Detektor Logam Kemasan Akhir (*Metal Detection*)
- **Bahaya Utama**: Kontaminasi fisik serpihan logam dari mesin sortasi atau peralatan pabrik.
- **Batas Kritis (Critical Limit)**:
  - Ferrous (Fe): sensitivitas deteksi $\le 1.5\text{ mm}$.
  - Non-Ferrous: sensitivitas deteksi $\le 2.0\text{ mm}$.
  - Stainless Steel (SUS 316): sensitivitas deteksi $\le 2.5\text{ mm}$.
- **Tindakan Korektif**:
  - Bila alarm detektor berbunyi, sabuk konveyor berhenti secara otomatis.
  - Tiga karton sebelum dan tiga karton sesudah produk yang memicu alarm diisolasi dan diinspeksi ulang secara manual.

---

## 4. Protokol Pemeliharaan Suhu Cold Storage & Kontainer Reefer

- **Suhu Penyimpanan Gudang Beku (*Cold Storage Vault*)**: Dipertahankan secara kontinu pada rentang suhu $-20.0^\circ\text{C} \pm 2.0^\circ\text{C}$.
- **Batas Toleransi Deviasi Suhu Rantai Dingin (*Cold Chain Excursion Limit*)**:
  - Peringatan Dini (*Warning*): Suhu sensor udara naik hingga $-15.0^\circ\text{C}$ selama lebih dari 30 menit.
  - Pelanggaran Kritis (*Critical Breach*): Suhu sensor udara naik di atas $-12.0^\circ\text{C}$ selama lebih dari 2 jam berturut-turut.
  - Sanksi Kritis: Sistem otomatis mencabut izin penerbitan surat jalan laut kontainer dan mengaktifkan klaim asuransi penurunan mutu kargo beku melalui integrasi modul `Insurance`.
