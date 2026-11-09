# Arsitektur Order-to-Cash (O2C) Billing Rumah Sakit & Koordinasi Manfaat (CoB) Waterfall

## 1. Pendahuluan & Ringkasan Eksekutif

Sistem Informasi Rumah Sakit modern menuntut integrasi yang kohesif antara pelayanan klinis (*clinical care*), manajemen inventaris logistik medis, dan siklus pendapatan (*Revenue Cycle Management* / RCM). Pada implementasi Fase 88, arsitektur *Order-to-Cash* (O2C) distandarisasi untuk mengakomodasi ekosistem pembiayaan kesehatan di Indonesia yang bercirikan sistem pembayaran majemuk:
- Jaminan Kesehatan Nasional (JKN) yang dikelola oleh BPJS Kesehatan dengan mekanisme pembayaran prospektif berbasis *Indonesian Case Base Groups* (INA-CBG).
- Asuransi Kesehatan Komersial Swasta (*Private Health Insurance*) dan *Third-Party Administrator* (TPA) yang menggunakan sistem penjaminan klaim non-tunai (*cashless guarantee*).
- Penjaminan Korporasi / Perusahaan yang memiliki Perjanjian Kerja Sama (PKS) khusus dengan rumah sakit.
- Pembayaran Langsung Mandiri oleh Pasien (*Out-of-Pocket* / OOP) atau skema dana bantuan sosial / subsidi amal (*Charity Subsidization Fund*).

Diagram interaksi alur kerja O2C Rumah Sakit:

```
+---------------------------------------------------------------------------------------------------+
|                                     ALUR KERJA O2C RUMAH SAKIT                                    |
+---------------------------------------------------------------------------------------------------+

   +------------------------+
   |   Pendaftaran Pasien   |
   | (Admisi Rawat Jalan /  |-----> Buat BillingEpisode (Status: OPEN)
   |       Rawat Inap)      |       Daftarkan Jaminan: BPJS / Asuransi Swasta / Umum
   +------------------------+
                |
                v
   +------------------------+
   |  Pelayanan Medis Aktif |
   | - Poliklinik / IGD     |-----> Input Folio Items: Tindakan Medis, Visitasi Dokter,
   | - Kamar Bedah (OK)     |       Pemeriksaan Laboratorium, Radiologi, Obat Farmasi
   | - Bangsal Perawatan    |
   +------------------------+
                |
                v
   +------------------------+
   |   Discharge Pasien     |-----> BillingEpisode dikunci (Status: CLOSED)
   |   (Selesai Rawat)      |       Pemeriksaan Invarian Kelengkapan Rekam Medis
   +------------------------+
                |
                v
   +------------------------+
   |  Koding Medis & E-Klaim|
   | - ICD-10 Diagnosis     |-----> INA-CBG Grouper Engine (Hitung MDC, Severity, Base Tariff)
   | - ICD-9-CM Prosedur    |       Status Episode: VERIFIED_CODER
   +------------------------+
                |
                v
   +------------------------+
   | CoB Waterfall Engine   |
   | - Layer 1: BPJS        |-----> Alokasi Pembiayaan Bertingkat:
   | - Layer 2: Asuransi    |       BPJS -> Asuransi Swasta (K31) -> Pasien OOP / Charity
   | - Layer 3: OOP/Charity |       Status Episode: CLAIM_SUBMITTED
   +------------------------+
                |
                v
   +------------------------+
   |   Penyelesaian Buku    |
   |   Besar & Kasir        |-----> Posting Jurnal Otomatis ke 27 Akun HospitalAccounts
   | (Settlement & Posting) |       Status Akhir Episode: SETTLED
   +------------------------+
```

---

## 2. Model Keadaan Episode Tagihan (Billing Episode State Machine)

Setiap episode pelayanan pasien diwakili oleh model `BillingEpisode`. Perubahan status tagihan diatur oleh state machine deterministik `BillingEpisodeStateMachine` guna memastikan tidak terjadi entri ganda atau kebocoran tagihan (*revenue leakage*):

### Matriks Transisi Status Episode Tagihan
| Status Asal | Event / Pemicu | Status Tujuan | Guard Conditions / Syarat Validasi |
| :--- | :--- | :--- | :--- |
| `DRAFT` | `open()` | `OPEN` | Encounter pasien valid, nomor registrasi rekam medis terverifikasi. |
| `OPEN` | `close()` | `CLOSED` | Pasien telah mendapatkan izin pemulangan (*discharge order*) dari DPJP. |
| `CLOSED` | `verifyCoder()` | `VERIFIED_CODER` | Minimal 1 diagnosis primer ICD-10 terdaftar, tarif INA-CBG terhitung. |
| `VERIFIED_CODER` | `submitClaim()` | `CLAIM_SUBMITTED` | Berkas digital klaim lengkap, surat jaminan asuransi swasta terhubung. |
| `CLAIM_SUBMITTED`| `settle()` | `SETTLED` | Nilai waterfall balance 100% terhadap total folio tagihan bruto. |
| `CLAIM_SUBMITTED`| `dispute()` | `DISPUTED` | Penolakan atau revisi berkas dari verifikator BPJS/TPA. |
| `DISPUTED` | `resolveDispute()`| `CLAIM_SUBMITTED` | Berkas sanggahan TKMKB selesai disusun beserta bukti medis pendukung. |
| `OPEN` / `CLOSED`| `cancel()` | `CANCELLED` | Pembatalan admisi dengan otorisasi Manajer Pelayanan Medis. |

---

## 3. Formulasi Tarif INA-CBG & Aturan Kenaikan Kelas Rawat Inap

Koding tarif INA-CBG dikelompokkan berdasarkan *Major Diagnostic Category* (MDC) dan tingkat keparahan (*Severity Level* I, II, dan III). 

### Struktur Kode INA-CBG
Format kode INA-CBG terdiri dari kombinasi alfanumerik:
$$\text{Kode CBG} = [\text{Digit 1: Huruf MDC}] + [\text{Digit 2: Tipe Kasus}] + [\text{Digit 3-4: Nomor Spesifik}] + [\text{Digit 5: Romawi Severity}]$$
- Digit 1: Alfabet rujukan sistem organ (misal: `I` untuk Kardiovaskular, `J` untuk Respirasi, `K` untuk Pencernaan).
- Digit 2: `1` untuk Kasus Rawat Inap Prosedur Bedah Mayor, `4` untuk Kasus Rawat Inap Medikal Umum, `5` untuk Rawat Jalan.
- Digit 5: Indeks Keparahan:
  - `I`: Kasus Ringan (*Mild*, tanpa komorbiditas berarti).
  - `II`: Kasus Sedang (*Moderate Complication / Comorbidity* / CC).
  - `III`: Kasus Berat (*Major Complication / Comorbidity* / MCC).

### Formula Perhitungan Tarif Dasar INA-CBG
Berdasarkan Permenkes No. 3 Tahun 2023, tarif klaim dihitung dengan formula:
$$\text{Tarif Akhir INA-CBG} = \text{Tarif Dasar Standar}(\text{Kode CBG}) \times K_{\text{Regional}} \times K_{\text{Tipe RS}}$$

Tabel Koefisien Regional:
| Regionalisasi Wilayah | Koefisien Multiplier ($K_{\text{Regional}}$) | Cakupan Provinsi |
| :--- | :--- | :--- |
| **Regional 1** | $1.00$ | DKI Jakarta, Jawa Barat, Jawa Tengah, DI Yogyakarta, Jawa Timur, Bali |
| **Regional 2** | $1.05$ | Sumatera Barat, Riau, Sumatera Selatan, Lampung |
| **Regional 3** | $1.12$ | Kalimantan Barat, Kalimantan Timur, Sulawesi Selatan, Sulawesi Utara |
| **Regional 4** | $1.25$ | Nusa Tenggara Barat, Nusa Tenggara Timur, Maluku |
| **Regional 5** | $1.35$ | Papua, Papua Barat, Papua Pegunungan, Papua Selatan |

Tabel Koefisien Kelas Rumah Sakit:
| Kelas Rumah Sakit | Multiplier Rawat Inap | Multiplier Rawat Intensif (ICU) |
| :--- | :--- | :--- |
| **RS Kelas A** (Rujukan Nasional) | $1.20$ | $1.35$ |
| **RS Kelas B** (Rujukan Regional) | $1.15$ | $1.25$ |
| **RS Kelas C** (RSUD Kabupaten/Kota) | $1.00$ | $1.10$ |
| **RS Kelas D** (Pratama) | $0.90$ | $0.95$ |

### Aturan Matematis Naik Kelas Rawat Inap (Permenkes No. 3/2023)
Jika pasien peserta BPJS menghendaki kenaikan kelas ruangan:
1. **Kenaikan dari Kelas 2 ke Kelas 1**:
   $$\text{Selisih Biaya} = \text{Tarif INA-CBG Kelas 1} - \text{Tarif INA-CBG Kelas 2}$$
2. **Kenaikan dari Kelas 1 ke Kelas VIP / Eksekutif**:
   $$\text{Selisih Biaya} = \min\Big( \text{Total Tagihan Riil Folio} - \text{Tarif INA-CBG Kelas 1}, \; 0.75 \times \text{Tarif INA-CBG Kelas 1} \Big)$$
   Ketentuan ini secara ketat melindungi pasien dari lonjakan tagihan tak terkendali dengan menetapkan batas atas (*cap*) selisih maksimal $75\%$ dari tarif INA-CBG Kelas 1.

---

## 4. Mesin Koordinasi Manfaat (CoB Waterfall Engine)

Layanan `CoordinationOfBenefitsWaterfallService` menjamin konservasi matematis nilai tagihan pasien:
$$\text{Total Gross Folio} = \text{BPJS Covered} + \text{Private Insurance Covered} + \text{Patient OOP} + \text{Charity Subsidy}$$

### Tahapan Penyerapan Alokasi Tagihan (Waterfall Tiers)
1. **Tier 1: BPJS Kesehatan INA-CBG (Penjamin Utama Pemerintah)**:
   - Menanggung paket tarif INA-CBG sesuai diagnosa primer dan sekunder.
   - Apabila terdapat ekses kenaikan kelas atau obat non-fornas di luar paket, sisa diteruskan ke Tier 2.
2. **Tier 2: Asuransi Swasta / TPA Komersial (Kontrak G7-K31)**:
   - Menanggung ekses kenaikan kelas dan tagihan tambahan hingga plafon Surat Jaminan (*Guarantee Letter*).
   - Memperhitungkan aturan polis: *deductible* tahunan dan klausul bagi biaya (*coinsurance*).
3. **Tier 3: Skema Subsidi Amal RS / Charity Subsidization Engine**:
   - Untuk pasien dhuafa ber-SKTM atau kasus gawat darurat penyelamatan jiwa, rumah sakit mengalokasikan subsidi dari dana Zakat/CSR rumah sakit.
4. **Tier 4: Pembayaran Mandiri Pasien (Out-of-Pocket / OOP)**:
   - Sisa saldo akhir dilunasi oleh pasien melalui kasir atau dipotong langsung dari deposit jaminan (*escrow*) awal.

---

## 5. Proteksi Kasus Katastropik & Reasuransi Stop-Loss Rumah Sakit

Pada kasus klinis berbiaya raksasa (*Catastrophic Cases*) seperti bedah pintas jantung terbuka (CABG), penggantian katup jantung (*valve replacement*), atau sepsis syok ICU dengan ventilasi mekanik $> 96$ jam:
- Total biaya riil rumah sakit sering kali melampaui tarif klaim INA-CBG.
- Rumah sakit menerapkan ambang batas kerugian stop-loss internal sebesar $\text{Rp } 75.000.000$ per episode.
- Layanan `CatastrophicCaseClaimEngine` mengevaluasi defisit:
  $$\text{Defisit Riil} = \max(0, \; \text{Biaya Riil RS} - \text{Total Penggantian Penjamin})$$
- Jika $\text{Defisit Riil} > \text{Rp } 75.000.000$, maka:
  $$\text{Klaim Reasuransi Stop-Loss} = 0.85 \times (\text{Defisit Riil} - \text{Rp } 75.000.000)$$
  Skema ini melindungi stabilitas keuangan rumah sakit dari risiko kerugian katastropik yang tak tertanggulangi.
