# Matriks Otorisasi RBAC & Perlindungan Data Medis (Medical Privacy & RBAC)

Dokumen ini menjelaskan kebijakan kontrol akses berbasis peran (Role-Based Access Control / RBAC) serta mekanisme perlindungan privasi data rekam medis elektronik pada modul Hospital Fase 104 sesuai dengan UU Perlindungan Data Pribadi (UU PDP No. 27 Tahun 2022) dan ISO/IEC 27001.

---

## 1. Prinsip Kerahasiaan Medis & *Need-to-Know*

Data kesehatan pribadi pasien tergolong data pribadi yang bersifat spesifik. Akses terhadap informasi rekam medis, riwayat pengobatan, hasil laboratorium, dan catatan telekonsultasi diatur secara ketat berdasarkan prinsip *Least Privilege* dan *Need-to-Know*.

---

## 2. Definisi Peran Sistem (Role Definitions)

Platform rumah sakit mendefinisikan peran-peran terpisah dengan batas kewenangan yang tegas:

| Peran (Role) | Kewenangan Klinis & Operasional | Batasan Akses |
|---|---|---|
| `doctor` | Membuka telekonsultasi, melihat rekam medis lengkap, meresepkan obat keras & narkotika (dengan SIP aktif), merujuk pasien ke rawat inap / SISRUTE. | Dilarang meracik obat, dilarang mengakses modul keuangan langsung. |
| `pharmacist` (Apoteker) | Meninjau resep digital, memvalidasi tanda tangan dokter, mengevaluasi FEFO, melakukan dispensing, memverifikasi double-check obat high-alert. | Dilarang mengubah dosis resep tanpa persetujuan dokter penulis. |
| `nurse` | Membantu triase telemedis, mencatat tanda-tanda vital awal, memantau ketersediaan bed bangsal. | Dilarang menandatangani resep atau menyetujui klaim BPJS. |
| `courier` (Kurir) | Mengakses alamat antar pasien, nomor telepon penerima, menginput telemetri suhu boks pendingin, memvalidasi e-POD (NIK & TTD). | Dilarang membaca diagnosa klinis, riwayat penyakit, atau catatan medis pasien. |
| `hospital_admin` | Mengelola data master poliklinik, cabang apotek jaringan, jadwal dokter, tarif rujukan. | Akses ke data klinis disamarkan (*masked / pseudonymized*). |
| `auditor` | Membaca riwayat transaksi untuk verifikasi kepatuhan SIPNAP, jejak log kriptografis, dan balans buku besar keuangan. | Akses *Read-Only* penuh terhadap log tanpa izin melakukan pembaruan (mutasi). |

---

## 3. Matriks Otorisasi Rute (Route-Roles Matrix)

Semua endpoint HTTP telemedis dan e-pharmacy diproteksi oleh middleware `RoleMiddleware` dan didaftarkan pada `config/route-roles.php`:

```php
// Cuplikan Pemetaan Otorisasi Endpoint Fase 104
'/hospital/telemedicine/dashboard' => ['doctor', 'nurse', 'hospital_admin', 'superadmin'],
'/hospital/telemedicine/consultation/{id}' => ['doctor', 'superadmin'],
'/hospital/telemedicine/prescribe' => ['doctor', 'superadmin'],
'/hospital/epharmacy/orders' => ['pharmacist', 'hospital_admin', 'superadmin'],
'/hospital/epharmacy/dispense' => ['pharmacist', 'superadmin'],
'/hospital/epharmacy/courier/dispatch' => ['pharmacist', 'hospital_admin', 'superadmin'],
'/hospital/epharmacy/courier/telemetry' => ['courier', 'hospital_admin', 'superadmin'],
'/hospital/epharmacy/courier/complete-pod' => ['courier', 'superadmin'],
'/hospital/referrals/sisrute/bridge' => ['doctor', 'hospital_admin', 'superadmin'],
'/hospital/chronic/subscriptions' => ['pharmacist', 'hospital_admin', 'superadmin'],
```

---

## 4. Pseudonimisasi & Enkripsi Data Medis

1. **Atribut Alergi & Riwayat Kronis**:
   Disimpan dalam kolom terenkripsi pada model `Patient`: `encrypted_allergies` dan `encrypted_chronic_diagnoses` menggunakan algoritma AES-256-GCM dengan kunci yang dikelola di tingkat aplikasi.
2. **Paspor Kesehatan (Health Passport Hash Chain)**:
   Perubahan diagnosa klinis membentuk rantai blok hash SHA-256 yang ditandatangani secara kriptografis sehingga mencegah penghapusan jejak medis (*anti-tampering audit trail*).
3. **Privasi Kurir Logistik**:
   Label kemasan obat kurir hanya memuat nama pasien, alamat, nomor telepon, dan instruksi penyimpanan (misal: "Simpan pada 2°C – 8°C"), tanpa mencantumkan indikasi medis sensitif (misal: "Obat ARV HIV" atau "Obat Onkologi").
