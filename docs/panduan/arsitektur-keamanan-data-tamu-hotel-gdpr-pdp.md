# Arsitektur Keamanan Data Tamu & Kepatuhan UU Perlindungan Data Pribadi (PDP)

## 1. Landasan Hukum & Ruang Lingkup Kepatuhan
Modul Perhotelan menyimpan informasi profil tamu tingkat tinggi (*Personally Identifiable Information / PII*) seperti nomor paspor, NIK KTP, preferensi ranjang kamar, pantangan alergi medis, dan riwayat transaksi keuangan.

Sistem perhotelan mengadopsi standar kepatuhan ketat sesuai Undang-Undang Perlindungan Data Pribadi (UU PDP No. 27 Tahun 2022) dan General Data Protection Regulation (GDPR Uni Eropa).

```
+----------------------------------------------------------------------------------+
|                    PROTOKOL PERLINDUNGAN PRIVASI DATA TAMU                       |
+----------------------------------------------------------------------------------+
|  [Identitas Tamu (KTP/Paspor)] --> Enkripsi AES-256-GCM At-Rest                  |
|  [Preferensi Alergi / Medis]  --> Akses Terisolasi Peran F&B / Housekeeping      |
|  [Riwayat Kartu Kredit / CVV]  --> Tokenisasi PCI-DSS Tingkat 1 (Tidak Disimpan) |
|  [Permohonan Hak Lupa]         --> Anonymization Engine (Penyamaran Nama/NIK)    |
+----------------------------------------------------------------------------------+
```

---

## 2. Enkripsi Data Sensitif & Manajemen Kunci (KMS)

### 2.1 Enkripsi Saat Penyimpanan (Encryption At-Rest)
Seluruh kolom identitas tamu pada tabel `htl_guest_identities`:
- `id_number`: Dienkripsi secara deterministik menggunakan skema AES-256-GCM agar pencarian cepat tetap memungkinkan tanpa mengekspos angka asli dalam plaintext.
- `metadata` (preferensi pribadi dan catatan khusus): Dienkripsi menggunakan kunci turunan per properti resort.

### 2.2 Isolasi Jalur Akses Berbasis Peran (RBAC Enforcement)
- **Front Desk Agent**: Hanya dapat melihat 4 digit terakhir nomor identitas tamu untuk keperluan verifikasi saat proses check-in.
- **Executive Chef**: Hanya dapat melihat catatan pantangan makanan dan alergi (misal: "Alergi kacang berat") tanpa memiliki hak akses ke nomor telepon atau data finansial tamu.
- **Night Auditor**: Hanya memiliki akses membaca ringkasan angka transaksi keuangan pada sub-folio tanpa dapat mengunduh dokumen paspor tamu.

---

## 3. Protokol Hak Penghapusan Data (Right to be Forgotten)
Bila tamu mengajukan permohonan penutupan akun loyalitas dan penghapusan data riwayat menginap:
1. Sistem memverifikasi bahwa seluruh kewajiban pembayaran tamu pada saldo sub-folio telah mencapai **IDR 0** dan tidak ada sengketa berjalan.
2. Sistem menjalankan *Guest Anonymization Command*:
   - Nama tamu diubah menjadi: `GUEST_ANONYMOUS_{HASH}`.
   - NIK, paspor, nomor telepon, dan email diganti dengan nilai null terenkripsi.
   - Catatan transaksi keuangan pada buku besar tetap dipertahankan untuk keperluan audit perpajakan (sesuai kewajiban retensi dokumen keuangan 10 tahun UU Perpajakan).
