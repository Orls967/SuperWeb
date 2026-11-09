# Panduan Pengujian & Verifikasi Kepatuhan Sistem (Verification & Testing Guide)

Dokumen ini menjelaskan strategi pengujian, skenario verifikasi otomatis, serta tata cara validasi kepatuhan arsitektur modul Hospital Fase 104 dalam kerangka continuous integration (CI).

---

## 1. Strategi Piramida Pengujian

Pengujian perangkat lunak Fase 104 dibagi ke dalam 3 tingkatan utama:
1. **Unit Tests**: Menguji logika murni algoritma matematika dan aturan farmakologi secara terisolasi (MKT Arrhenius, Cockcroft-Gault, Child-Pugh, Beers Criteria).
2. **Feature & Contract Tests**: Menguji alur bisnis terpadu end-to-end melalui interaksi database in-memory SQLite, event handling, dan pemenuhan kontrak antarmuka G7-K30 (`PatientReferralBooker`).
3. **Architecture & Invariant Tests**: Menjamin kepatuhan aturan arsitektur sistem SuperWeb, deteksi korupsi basis data dua sumber, dan integritas matriks RBAC.

---

## 2. Test Suite Utama Fase 104

File-file pengujian berikut merupakan tulang punggung verifikasi Fase 104:

### 2.1 `PatientReferralBookerContractTest.php`
Menguji 6 metode esensial kontrak G7-K30:
- `test_k30_1_book_teleconsultation_with_triage`: Verifikasi pembuatan sesi telemedis disertai kalkulasi triage skor otomatis.
- `test_k30_2_issue_electronic_prescription_with_digital_signature`: Verifikasi penerbitan e-resep dengan tanda tangan kriptografis dokter dan deteksi pemalsuan.
- `test_k30_3_route_prescription_to_optimal_geospatial_branch`: Verifikasi pemilihan cabang apotek jaringan terdekat berbasis koordinat geospasial Haversine.
- `test_k30_4_dispatch_medicine_delivery_with_fefo_depletion`: Verifikasi pembentukan pesanan kurir logistik dan pemotongan stok berdasar lot FEFO terawal.
- `test_k30_5_bridge_bpjs_sisrute_referral`: Verifikasi penjembatanan rujukan telemedis ke SISRUTE Kemenkes.
- `test_k30_6_book_inpatient_from_telemedicine_referral`: Verifikasi pemesanan langsung tempat tidur rawat inap (bangsal/VIP) dari hasil rujukan telemedis.

### 2.2 `ClinicalDecisionSupportComprehensiveTest.php`
Menguji ketahanan algoritma pendukung keputusan klinis:
- Deteksi interaksi obat kombinatorial `checkPair` dan `scanPrescriptionList` (Warfarin + Aspirin).
- Evaluasi klirens ginjal pasien geriatri dengan kontraindikasi Metformin pada $CrCl < 30\text{ mL/min}$.
- Penandaan obat berisiko tinggi (*High-Alert Medications*) dan konversi Tall-Man Lettering ISMP.
- Verifikasi Beers Criteria AGS untuk pasien lanjut usia ($\ge 65$ tahun).
- Pembuktian kegagalan verifikasi tanda tangan jika payload e-resep diubah (*tampered payload*).

### 2.3 `ColdChainLogisticsAndPodTest.php`
Menguji integritas rantai pasok farmasi peka suhu:
- Inisiasi ekspedisi rantai dingin dengan validasi ambang batas suhu awal ($2.0^\circ\text{C} - 8.0^\circ\text{C}$).
- Karantina seketika (*instant quarantine*) saat terjadi lonjakan suhu di jalan ($12.8^\circ\text{C}$).
- Pemblokiran serah terima paket yang telah berstatus `QUARANTINED_BREACH`.
- Penyelesaian e-POD dengan pencatatan NIK 16 digit dan pembuatan cryptographic hash bukti penyerahan.
- Perhitungan Mean Kinetic Temperature (MKT) Arrhenius.

### 2.4 `ChronicRefillAndAdherenceTest.php`
Menguji mesin langganan obat kronis:
- Identifikasi prediktif langganan yang jatuh tempo dalam jendela 3 hari ke depan.
- Pemrosesan batch refill otomatis yang memajukan jadwal berikutnya secara idempoten (+30 hari).
- Analisis rasio kepemilikan obat (*Medication Possession Ratio* / MPR) dan penentuan tingkat kepatuhan.
- Peramalan kebutuhan pengadaan obat bulanan disertai *safety stock buffer* 20%.

---

## 3. Perintah Eksekusi Pengujian Lokal

Jalankan rangkaian pengujian modul secara mandiri:
```bash
# 1. Jalankan seluruh Feature Test Hospital
./vendor/bin/pest modules/Hospital/tests/Feature/

# 2. Jalankan Perintah Audit Integritas
php artisan hosp:audit
php artisan hsp:telemedicine-scan
php artisan hsp:chronic-refill-cron

# 3. Jalankan Pengujian Arsitektur Sistem
./vendor/bin/pest tests/Architecture/AuditCommandContractTest.php
./vendor/bin/pest tests/Architecture/RouteAuthorizationMatrixTest.php
```

