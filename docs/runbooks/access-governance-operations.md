# Runbook Operasional: Tata Kelola Akses, Identitas & Isolasi Multi-Tenant

**Dokumen Referensi:** SOP-SEC-188  
**Klasifikasi:** Internal Kritis  
**Sasaran:** SRE, Platform Architect, Security Officer, Compliance Team  
**Revisi Terakhir:** 2026-11-12 (Fase 188)

---

## 1. Operasi Harian & Pemeliharaan Terjadwal

### A. Eksekusi Harian Isolasi Multi-Tenant
Perintah `access:tenant-probe` dijalankan secara otomatis setiap hari pukul 02:00 UTC untuk memverifikasi tidak ada kebocoran antar-tenant:
```bash
php artisan access:tenant-probe --samples=100
```
- **Keluaran Normal:** Exit code `0`, `[TENANT_PROBE_OK] 100/100 model terisolasi sempurna`.
- **Keluaran Galat:** Exit code `1`, `[TENANT_LEAK_DETECTED] Model {class} membocorkan data tenant {A} ke {B}`.
- **Tindakan Insiden:** Lihat Prosedur 3.1 di bawah.

### B. Penyapuan Izin Kedaluwarsa Otomatis
Penyapuan izin akses sementara yang melewati batas waktu:
```bash
php artisan access:expire-grants
```
- Menelusuri seluruh baris `core_access_grants` berstatus `active` atau `suspended` dengan `valid_to < now()`.
- Menjalankan transisi imutabel ke status `expired`.

### C. Eksekusi Kampanye Re-Sertifikasi Akses Periodik
Setiap kuartal atau siklus audit bulanan:
```bash
# Membuat kampanye re-sertifikasi baru
php artisan access:recert-campaign --create --scope=critical_roles --deadline=14d

# Menyapu kampanye yang melewati batas waktu (auto-revocation)
php artisan access:recert-campaign --sweep
```

---

## 2. Prosedur Penanganan Insiden Keamanan

### Prosedur 2.1: Pencabutan Hak Akses Segera (Sub-Detik)
Jika ditemukan anomali perilaku pengguna atau kebocoran kredensial:
1. Buka antarmuka manajemen hak akses pada `/admin/access/grants`.
2. Klik tombol **Cabut Izin (Revoke)** pada grant terkait atau eksekusi perintah CLI:
   ```bash
   php artisan access:revoke-grant --id={grant_id} --reason="Security incident ticket #SEC-991"
   ```
3. Sistem secara atomik:
   - Mengubah status grant menjadi `revoked` di basis data.
   - Menaikkan `grant_version` pada cache memori terdistribusi.
   - Seluruh request berikutnya yang membawa token lama seketika ditolak dengan `403 Forbidden` dalam waktu $< 1$ detik.

### Prosedur 2.2: Deteksi Kebocoran Isolasi Tenant (Cross-Tenant Leak)
Jika alarm `TENANT_CROSS_BOUNDARY_LEAK` aktif:
1. Periksa log insiden pada tabel `core_tenant_scope_bypasses` dan log audit:
   ```bash
   php artisan access:audit
   ```
2. Identifikasi query atau model yang memicu bypass tenant filter.
3. Pastikan model tersebut terdaftar di `TenantScopeRegistry`.
4. Jika kebocoran terjadi melalui kueri kustom atau raw SQL, isolasi rute terdampak segera.

---

## 3. Verifikasi Integritas Dua-Sumber (Audit CLI)

Untuk memverifikasi keabsahan data grant, kebijakan, dan integritas sertifikat kalkulasi:
```bash
php artisan access:audit
```
- **Hasil Sukses:**
  ```text
  [ACCESS_AUDIT_OK] Seluruh 5,420 grants, 128 kebijakan, dan 350 tautan identitas konsisten dengan riwayat transisi imutabel.
  Exit Code: 0
  ```
- **Hasil Galat (Fixture Sabotase atau Manipulasi):**
  ```text
  [ACCESS_AUDIT_FAIL] Ditemukan 1 diskrepansi: Grant #GRT-0042 berstatus active tanpa riwayat approved di core_access_grant_transitions.
  Exit Code: 1
  ```

