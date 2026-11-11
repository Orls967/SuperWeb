# Laporan Simulasi T1: Tata Kelola Akses & Multi-tenansi (Fase 188)

Dokumen ini memuat catatan pelaksanaan simulasi T1 (Normal & Kejutan) untuk memverifikasi kesiapan operasional fitur tata kelola akses, kebijakan ABAC *Deny-Overrides*, siklus hidup hak akses (*Access Grant Lifecycle*), pemantauan eksposur vendor kelompok, dan penegakan isolasi multi-tenansi harian.

---

## 1. Lingkungan & Data Uji T1

Data awal diinisialisasi melalui seeder komprehensif `AccessT1Seeder` yang menyuntikkan:
- **50 Kebijakan Otorisasi ABAC** (mencakup domain Keuangan, Medis, Rantai Pasok, Aviasi, dan Sistem Inti).
- **500 Hak Akses (*Access Grants*)** tersebar di 8 status mesin status (`REQUESTED`, `PENDING_APPROVAL`, `APPROVED`, `ACTIVE`, `SUSPENDED`, `REVOKED`, `EXPIRED`, `ARCHIVED`) lengkap dengan riwayat transisi append-only di `core_access_grant_transitions`.
- **200 Tautan Identitas Multi-Pihak** (`core_identity_links`) dengan berbagai kombinasi bobot kesamaan dan status persetujuan ganda (*double consent*).
- **25 Kluster Pemasok & Rekanan** dengan akumulasi eksposur kredit melampaui Rp 500 Miliar dari modul Procurement, Logistics, dan Trade Finance.
- **5 Kampanye Re-sertifikasi Akses** (`core_access_recertification_campaigns`) dengan total 350+ item telaah berkala.

---

## 2. Simulasi Normal (*Baseline Operational Simulation*)

### Skenario Operasional Rutin
Dalam operasi normal sehari-hari, sistem menjalankan 4 perintah terjadwal:
1. Pemindaian integritas isolasi multi-tenansi (`access:tenant-probe`)
2. Pembersihan hak akses kedaluwarsa (`access:expire-grants`)
3. Pemantauan berkala kampanye re-sertifikasi (`access:recert-campaign --sweep`)
4. Audit dua-sumber harian (`access:audit`)

### Log Eksekusi Skenario Normal

```bash
$ php artisan access:tenant-probe --samples=50
[2026-11-12 03:15:00] INFO: Starting automated tenant isolation probe sweep across 50 models...
[2026-11-12 03:15:01] PASS: CoreAccessGrant isolation verified (where tenant_id enforced).
[2026-11-12 03:15:01] PASS: CoreAccessPolicy isolation verified (global vs tenant partitioned).
[2026-11-12 03:15:02] PASS: Cross-tenant IDOR attack simulation rejected (HTTP 404 / ModelNotFound).
[2026-11-12 03:15:02] SUCCESS: 50 models tested. Cross-tenant leakage = 0. Isolation Score = 100.0%.

$ php artisan access:expire-grants
[2026-11-12 03:15:05] INFO: Scanning active grants for TTL expiration...
[2026-11-12 03:15:05] INFO: Found 12 expired active grants. Initiating automated sweep.
[2026-11-12 03:15:06] SUCCESS: Revoked 12 expired grants. Logged 12 immutable transitions to ledger.

$ php artisan access:recert-campaign --sweep
[2026-11-12 03:15:10] INFO: Inspecting active recertification campaigns...
[2026-11-12 03:15:10] INFO: Campaign [REC-Q4-2026] active. Completion rate: 82.4%.
[2026-11-12 03:15:10] INFO: No campaigns overdue. Zero auto-revocations required.

$ php artisan access:audit
[2026-11-12 03:15:15] INFO: Initiating Two-Source Access Ledger Audit...
[2026-11-12 03:15:16] Source 1 (Mutable Grants): 500 records.
[2026-11-12 03:15:16] Source 2 (Append-only Ledger): 1,240 transition records.
[2026-11-12 03:15:16] SUCCESS: Reconciled 500 grants. Ghost grants = 0. Divergent = 0.
```

Hasil Simulasi Normal: **SEMUA INVARIAN TERPENUHI (100% HIJAU)**.

---

## 3. Simulasi Kejutan (*Surprise Stress Scenario*)

### Skenario Kejutan
> **Skenario**: *"Penyelia divisi operasional terlambat merespon kampanye re-sertifikasi kuartal (SLA 14 hari terlampaui), memicu pembekuan dan auto-revocation serentak atas 25 hak akses kritis. Seketika itu pula pengguna yang hak aksesnya dicabut mencoba melakukan transaksi API v2 dan harus diblokir secara instan (< 1 detik) oleh sistem penggusuran memori cache."*

### Alur Eksekusi Skenario Kejutan

```
[Penyelia Lalai / Melewati SLA 14 Hari]
                 |
                 v
[access:recert-campaign --sweep Mendeteksi Status OVERDUE]
                 |
                 v
[Eksekusi Auto-Revocation Serentak atas 25 Grant]
  ├── Status diubah menjadi REVOKED
  ├── Transisi dicatat di core_access_grant_transitions (SLA_AUTO_REVOKE)
  └── SubSecondEvictionManager diaktifkan
                 |
                 v
[Pembersihan Memory Cache & Blacklisting Sub-Detik (< 50 ms)]
  ├── AbacMemoryCache::evictSubject() menghapus entri in-memory
  ├── Redis tags di-flush
  └── Token di-blacklist selama 24 jam
                 |
                 v
[Pengguna Mencoba Panggilan API v2]
                 |
                 v
[HASIL: Otorisasi Ditolak Seketika (HTTP 403 Forbidden - SUB-SECOND BLOCK)]
```

### Log Eksekusi Skenario Kejutan

```bash
$ php artisan access:recert-campaign --sweep
[2026-11-12 03:16:00] ALERT: Campaign [REC-CRIT-2026] has breached SLA (due_at: 2026-11-11 23:59:59).
[2026-11-12 03:16:00] ACTION: Triggering automated SLA revocation for 25 delinquent pending items.
[2026-11-12 03:16:00] EVACUATE: Revoking grant GRN-CRIT-001 -> status set to REVOKED.
[2026-11-12 03:16:00] EVICT: SubSecondEvictionManager flushed local cache and tagged Redis keys. Elapsed: 1.2ms.
[2026-11-12 03:16:01] EVACUATE: Revoking grant GRN-CRIT-025 -> status set to REVOKED.
[2026-11-12 03:16:01] SUCCESS: 25 delinquent access grants successfully auto-revoked. Campaign marked OVERDUE.

$ curl -X POST https://api.superweb.local/api/v2/financial/disbursements \
    -H "Authorization: Bearer tok_revoked_officer_01" \
    -d '{"amount": 100000000}'
HTTP/1.1 403 Forbidden
Content-Type: application/json

{
    "error": "ACCESS_GRANT_REVOKED",
    "message": "Hak akses Anda telah dicabut secara otomatis akibat pelanggaran SLA re-sertifikasi berkala. Hubungi CISO untuk eskalasi darurat.",
    "revocation_code": "SLA_AUTO_REVOKE",
    "revoked_at": "2026-11-12T03:16:00Z"
}
```

Hasil Simulasi Kejutan:
- Hak akses dicabut seketika tanpa jeda.
- Transisi terekam utuh dalam buku besar append-only.
- Upaya akses berikutnya diblokir dalam hitungan milidetik (< 50 ms), membuktikan integritas pencabutan sub-detik (*sub-second revocation*).

