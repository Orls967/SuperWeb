# Dokumen Arsitektur: Telemedicine, E-Pharmacy & Jaringan Apotek (Fase 104)

## 1. Eksekutif Ringkasan & Definisi Arsitektur

Fase 104 merealisasikan subsistem **Telemedicine Terintegrasi, E-Pharmacy Preskripsi Digital, dan Jaringan Apotek Terdistribusi** dalam ekosistem Hospital SuperWeb (`Modules\Hospital`). Sistem ini menjembatani konsultasi klinis jarak jauh, evaluasi keselamatan farmakologi klinis (*Clinical Decision Support*), penerbitan e-resep berintegritas kriptografis, perutean pemenuhan resep terdesentralisasi (*geospatial multi-branch inventory routing*) berbasis FEFO (*First-Expired, First-Out*), pelacakan pengiriman kurir berantai dingin (*cold-chain 2–8°C*), penanganan langganan obat kronis (*chronic disease recurring refill*), dan bridging rujukan berjenjang nasional (BPJS SISRUTE & V-Claim) melalui Kontrak Antarmuka Standar **G7-K30** (`PatientReferralBooker`).

```
+---------------------------------------------------------------------------------------------------+
|                                  ARSITEKTUR SUBSISTEM FASE 104                                    |
+---------------------------------------------------------------------------------------------------+
|  [PASIEN / APLIKASI KLIEN]                                                                        |
|         │                                                                                         |
|         ▼                                                                                         |
|  [Telemedicine Intake & Triase Klinis] ──(Red Flag?)──► [Emergency Escalator / Dispatcher]       |
|         │                                                                                         |
|         ▼ (Green/Amber)                                                                           |
|  [Teleconsultation Session] (Doctor Video/Chat Recording & Diagnosis Encrypted)                   |
|         │                                                                                         |
|         ▼                                                                                         |
|  [Clinical Decision Support Engine (CDSE)]                                                        |
|    ├── Drug-Drug Interaction Matrix (High/Moderate/Minor)                                         |
|    ├── Cross-Allergy Class Checking vs Human Passport (Fase 87)                                   |
|    ├── Renal Clearance GFR Dosing Adjustment (Cockcroft-Gault)                                    |
|    └── Controlled Substance Gate (Narkotika/Psikotropika Dual Doctor Approval)                    |
|         │                                                                                         |
|         ▼ (Pass CDS Gate)                                                                         |
|  [Digital E-Prescription Issuer] ──► [Cryptographic Hash Chain & Doctor SIP Signature]            |
|         │                                                                                         |
|         ▼                                                                                         |
|  [Geospatial Pharmacy Multi-Branch Router] (Haversine Distance + Real-time Inventory Check)       |
|    ├── Primary Hub (Apotek RS Pusat)                                                              |
|    ├── Satellite Branch (Apotek Jaringan Internal)                                                |
|    └── Partner Network (Mitra Kimia Farma / K-24 / Apotek Rekanan Party)                         |
|         │                                                                                         |
|         ▼                                                                                         |
|  [FEFO Dispensing & Lot Picking Engine] ──(Batch Allocation & Inventory Ledger Lock)              |
|         │                                                                                         |
|         ▼                                                                                         |
|  [Cold-Chain Last-Mile Logistics Dispatcher]                                                      |
|    ├── Temperature Breach Monitoring (Sensors 2-8°C with Alert Thresholds)                       |
|    └── Electronic Proof of Delivery (e-POD) with Recipient Signature & Photo Verification         |
|         │                                                                                         |
|         ▼                                                                                         |
|  [Kontrak G7-K30: PatientReferralBooker]                                                          |
|    ├── Bridging BPJS SISRUTE (Rujukan FKTP -> FKRTL Tipe C/B/A)                                   |
|    └── Bridging Rawat Inap & Bed Reservation (Integrasi Fase 87/88)                               |
+---------------------------------------------------------------------------------------------------+
```

---

## 2. Batasan Kepatuhan & Aturan Anti-Bypass

1. **Integritas Medis & Kerahasiaan (UU PDP & Permenkes No. 24/2022)**:
   - Seluruh data rekam medis konsultasi jarak jauh, diagnosa ICD-10, catatan klinis dokter, dan riwayat alergi pasien dienkripsi pada *rest* menggunakan cipher `AES-256-GCM` dengan envelope encryption.
   - Akses data telemedis dibatasi oleh *Role-Based Access Control* (RBAC) ketat: hanya dokter pemeriksa, apoteker terdaftar, dan auditor berizin yang memiliki hak inspeksi.

2. **Validasi Resep Elektronik & Tanda Tangan Digital Dokter (Permenkes No. 20/2019)**:
   - Resep elektronik tidak sah tanpa stempel waktu kriptografis (*RFC 3161 compliant timestamp*) dan hash tanda tangan digital dokter pemilik Nomor Surat Izin Praktik (SIP) yang valid.
   - Setiap e-resep dihubungkan dalam rantai blok internal anti-tampering (*tamper-evident hash chain*) yang memverifikasi bahwa konten resep (nama obat, dosis, frekuensi, aturan pakai) tidak mengalami modifikasi sejak pengesahan dokter.

3. **Penyaluran Obat Keras & Psikotropika**:
   - Obat golongan Narkotika (Golongan II/III) dan Psikotropika dilarang keras diproses otomatis tanpa verifikasi ganda (*dual doctor approval*) dan otentikasi identitas fisik penerima saat serah terima.
   - Verifikasi serah terima obat keras mewajibkan *Signed Proof of Delivery* (e-POD) dengan verifikasi NIK KTP penerima dan nomor batch obat yang tersegel.

4. **Kepatuhan Alokasi Persediaan FEFO**:
   - Sistem apotek wajib mengalokasikan stok berdasarkan tanggal kedaluwarsa paling dini (*First-Expired, First-Out*). Batch dengan masa simpan kurang dari 90 hari otomatis dialihkan ke status karantina khusus atau dilarang didistribusikan ke pasien rawat jalan.
   - Transaksi pemotongan persediaan mengunci baris stok menggunakan `lockForUpdate()` dalam transaksi database acid murni, mencegah *overselling* atau *negative stock balance*.

5. **Pemisahan Finansial & Akuntansi Ledger O2C**:
   - Setiap pembayaran konsultasi telemedis dan penebusan e-resep dicatat ke dalam buku besar akun rumah sakit:
     - Kas/Bank Operasional Telemedicine (Akun 1010/1015).
     - Piutang Asuransi / BPJS Bridging (Akun 1120/1130).
     - Pendapatan Jasa Medis Konsultasi Telemedicine (Akun 4110).
     - Pendapatan Farmasi & Obat E-Pharmacy (Akun 4120).
     - Hutang Honorarium Dokter Spesialis (Akun 2120).
     - Hutang Biaya Ekspedisi / Kurir Logistik (Akun 2130).
   - Seluruh posting akuntansi diverifikasi oleh perintah audit `hsp:audit` dengan selisih Rp 0 (nol discrepancy).

---

## 3. Matriks Keputusan Pembaruan Kode (L.0)

Berdasarkan audit atas modul `Hospital` yang ada, diputuskan langkah-langkah terukur:

| Kelas / Komponen Eksisting | Status | Alasan & Tindakan Rekayasa |
| :--- | :---: | :--- |
| `TelemedicineAndEPharmacyService` (149 baris) | **Perdalam (Deepen)** | Diperdalam dari orkestrasi sederhana menjadi enterprise clinical tele-triage & consultation pipeline lengkap dengan enkripsi, audit logging, dan integrasi G7-K30. |
| `PrescriptionSafetyEvaluator` (95 baris) | **Perdalam (Deepen)** | Diperluas menjadi Clinical Decision Support Engine (CDSE) multi-faktor: matriks interaksi antarobat (DDI), klirens ginjal GFR/Cockcroft-Gault, kehamilan/laktasi, dan dosis maksimal. |
| `ChronicDrugSubscriptionService` (115 baris) | **Perdalam (Deepen)** | Diperkaya dengan mesin penjadwalan prediksi kehabisan obat (*predictive exhaustion scheduler*), pemotongan wallet berulang terintegrasi, dan auto-refill batch dengan kontrol audit. |
| `PharmacyMultiBranchRoutingService` (46 baris) | **Perdalam (Deepen)** | Diperkaya dengan algoritma Haversine geospasial presisi tinggi, fallback routing berantai cabang satelit dan apotek rekanan, serta validasi integritas rantai dingin logistik. |
| `ChronicSubscription` & `AllergyMatrix` (Model) | **Perdalam (Deepen)** | Ditambahkan casting JSON, enkripsi field sensitif, query scopes untuk batch cron, serta relasi eksplisit ke episode billing dan akun ledger. |
| **Kontrak G7-K30: `PatientReferralBooker`** | **Baru (New)** | Dibuat interface kontrak baru G7-K30 di modul `Hospital` untuk standarisasi pertukaran rujukan eksternal dan interoperabilitas jaringan apotek. |
| `PatientReferralBookerService` | **Baru (New)** | Implementasi enterprise atas kontrak G7-K30 yang mengintegrasikan telemedicine, e-resep, apotek cabang, logistik cold-chain, dan bridging rujukan BPJS SISRUTE. |

