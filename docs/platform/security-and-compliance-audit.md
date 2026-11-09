# Kerangka Kepatuhan Keamanan & Audit Regulasi Platform (SOC 2 / ISO 27001)

Dokumen ini menjelaskan kontrol keamanan teknis, pemisahan tugas (Separation of Duties), dan integritas jejak audit kriptografis pada modul Platform untuk memenuhi standar sertifikasi industri.

---

## 1. Landasan Kepatuhan Regulasi

Sistem manajemen perubahan dan pengiriman kode pada platform dirancang untuk memenuhi kontrol kepatuhan berikut:
- **SOC 2 Type II (Trust Services Criteria)**: Kontrol CC6 (Logical and Physical Access), CC7 (System Operations), dan CC8 (Change Management).
- **ISO/IEC 27001:2022**: Kontrol A.8.25 (Secure Development Life Cycle), A.8.29 (Security Testing in Acceptance), dan A.8.32 (Change Management).
- **PCI-DSS v4.0**: Persyaratan 6.4 (Pengelolaan Perubahan Sistem dan Perangkat Lunak Secara Aman).

---

## 2. Pemisahan Tugas (Separation of Duties / SoD)

Prinsip dasar kepatuhan mensyaratkan bahwa pengembang yang menulis kode dilarang menyetujui atau menerapkan perubahannya sendiri ke lingkungan produksi tanpa pengawasan pihak independen:

### Kontrol SoD yang Ditegakkan Secara Otomatis:
1. **Pemeriksaan Pengarang (Author Segregation)**:
   Model `ChangeRequest` memvalidasi bahwa `author_id` tidak boleh sama dengan `approver_id`. Sistem menolak persetujuan sepihak dengan pengecualian `DomainException`.
2. **Persetujuan Ganda untuk Perubahan Kritis (Dual Control)**:
   Perubahan berkategori `critical` mewajibkan kuorum persetujuan minimal dari dua entitas yang berbeda:
   - Satu orang Lead Engineer atau Solution Architect.
   - Satu orang representatif tim Keamanan Informasi (InfoSec) atau Tim Keandalan Platform.
3. **Pemberian Hak Akses Berdasarkan Peran (Role-Based Access Control / RBAC)**:
   Rute manajemen perubahan `/platform/change-management` dan `/platform/release-trains` dilindungi oleh middleware `role:admin,superadmin`. Upaya akses oleh pengguna tanpa wewenang akan menghasilkan status HTTP 403 Forbidden.

---

## 3. Buku Besar Audit Anti-Penyangkalan (Tamper-Evident Audit Ledger)

Seluruh peristiwa transisi status perubahan, tindakan persetujuan, dan intervensi operator dicatat secara permanen pada tabel `plt_change_transitions`:

### Struktur Kriptografis Jejak Audit:
Setiap catatan transisi memuat `audit_hash` yang dihitung menggunakan algoritma SHA-256 dengan rumus:

$$\text{hash}_n = \text{SHA256}(\text{hash}_{n-1} \parallel \text{id} \parallel \text{from\_status} \parallel \text{to\_status} \parallel \text{operator\_id} \parallel \text{timestamp})$$

Karakteristik Keamanan Buku Besar:
- **Keterikatan Berantai (Chained Integrity)**: Setiap baris mengikat hash dari rekaman sebelumnya. Modifikasi atau penghapusan data secara ilegal pada masa lalu akan merusak rantai verifikasi.
- **Deteksi Pemalsuan Seketika**: Perintah konsol `php artisan platform:change-check` memindai seluruh rantai hash dan memvalidasi keabsahan data terhadap kunci rahasia aplikasi.
- **Penyimpanan Tak Terhapus (Append-Only)**: Tidak ada operasi `UPDATE` atau `DELETE` pada tabel audit; seluruh peristiwa dicatat secara append-only.

---

## 4. Perlindungan Data Sensitif & Sanitasi Log

Dalam seluruh proses observasi telemetri canary, pemrosesan backfill, dan pencatatan riwayat perubahan:
- Kredensial, kunci API, dan token sesi dilarang keras dicatat dalam kolom `metadata` JSON.
- `SqlAstTokenScanner` secara proaktif memindai masukan kueri untuk mendeteksi penulisan string teks biasa (plaintext) pada kolom sensitif seperti password atau nomor identitas pribadi.
- Setiap entri log yang dikirim ke saluran eksternal difilter melalui pembersih sanitasi regex.

---

## 5. Prosedur Audit Tahunan Eksternal (Auditor Walkthrough)

Saat auditor independen melakukan penilaian kepatuhan tahunan:
1. Jalankan ekstraksi sampel perubahan acak:
   ```bash
   php artisan platform:change-check --export=csv --window-days=365
   ```
2. Tunjukkan sertifikat keselamatan migrasi (`MigrationSafetyCertificate`) untuk setiap migrasi skema yang diterapkan ke produksi.
3. Buktikan bahwa tidak ada perubahan tanpa tiket persetujuan resmi yang lolos ke produksi dalam kurun waktu audit.
