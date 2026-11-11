# Runbook Tanggap Insiden Keamanan Tata Kelola Akses (Fase 188)

Runbook ini menjadi pedoman operasional bagi tim *Security Operations Center* (SOC), *Platform Engineers*, dan *Site Reliability Engineers* (SRE) dalam merespon anomali akses, dugaan kebocoran lintas-tenan, atau penyalahgunaan hak istimewa darurat (*break-glass*).

---

## 1. Klasifikasi Insiden & Tingkat Keparahan (Severity Tiers)

| Tingkat | Kriteria Insiden | SLA Respon | Eskalasi Utama |
|---|---|---|---|
| **SEV-1 (Kritis)** | Kebocoran data lintas-tenan (*cross-tenant leakage* terdeteksi di probe atau SIEM alert), kebocoran kunci master, eksekusi *break-glass* tanpa tiket insiden valid. | < 15 Menit | CISO, CTO, VP Engineering |
| **SEV-2 (Tinggi)** | Kegagalan audit rekonsiliasi dua-sumber (terdapat *ghost grants* atau divergensi status), pelanggaran batas kredit vendor kelompok > Rp 500M tanpa persetujuan. | < 1 Jam | Lead Security Engineer, VP Finance |
| **SEV-3 (Sedang)** | Upaya login perjalanan mustahil (*impossible travel velocity*), kegagalan SLA re-sertifikasi akses supervisor (> 14 hari). | < 4 Jam | SOC Analyst, Department Supervisor |
| **SEV-4 (Rendah)** | Deviasi kecil pada skor kepercayaan perangkat (*device trust score* < 40), permintaan akses peran standar di luar jam kerja. | < 24 Jam | Service Desk / SysAdmin |

---

## 2. Prosedur Tanggap Darurat SEV-1: Pembekuan Hak Akses Instan

Jika terdeteksi indikasi penyusupan akun atau penyalahgunaan peran berlebih:

### Langkah 1: Eksekusi Pencabutan Sub-Detik melalui CLI
Jalankan pencabutan paksa (*force revocation*) secara langsung dari konsol produksi:

```bash
php artisan tinker --execute="
    \$grant = \Modules\Core\Domain\Models\CoreAccessGrant::where('grant_code', 'GRN-TARGET-CODE')->first();
    app(\Modules\Core\Domain\Access\Cache\SubSecondEvictionManager::class)->executeRevocation(\$grant, 'SEV-1 Emergency Containment');
    \$grant->status = 'REVOKED';
    \$grant->save();
"
```

### Langkah 2: Verifikasi Pembekuan Cache
Pastikan memori cache dan kunci Redis terhapus:

```bash
php artisan tinker --execute="
    echo 'Memory cache count: ' . app(\Modules\Core\Domain\Access\Cache\AbacMemoryCache::class)->count();
"
```

### Langkah 3: Investigasi Jejak Audit Append-Only
Periksa seluruh riwayat transisi hak akses pada tabel audit untuk melacak kapan hak akses dibuat, siapa yang memberi otorisasi, dan kapan transisi terakhir terjadi:

```sql
SELECT id, grant_id, from_status, to_status, transition_code, reason, performed_by, payload_hash, occurred_at
FROM core_access_grant_transitions
WHERE grant_id = (SELECT id FROM core_access_grants WHERE grant_code = 'GRN-TARGET-CODE')
ORDER BY id ASC;
```

---

## 3. Prosedur Investigasi Kebocoran Lintas-Tenan (Cross-Tenant Leakage)

Bila probe harian `access:tenant-probe` atau SIEM memicu peringatan `CROSS_TENANT_LEAK_DETECTED`:

```
[Alarm CROSS_TENANT_LEAK_DETECTED Muncul di Dasbor]
                         |
                         v
[Langkah 1: Identifikasi Model & Query Pelanggar]
  ├── Periksa log kejadian: Target Model, Tenant Pengakses, Tenant Pemilik
  └── Catat trace tumpukan panggilan (*call stack trace*)
                         |
                         v
[Langkah 2: Periksa Registri Model di TenantScopeRegistry]
  ├── Apakah model memiliki trait BelongsToTenant?
  └── Apakah model terdaftar di TenantScopeRegistry::registeredModels()?
                         |
                         v
[Langkah 3: Periksa Indikasi Bypass tanpa Global Scope]
  ├── Cari pemanggilan withoutGlobalScope atau withoutTenancy di kode
  └── Periksa apakah ada raw SQL DB::table() tanpa filter tenant_id
                         |
                         v
[Langkah 4: Deploy Hotfix & Jalankan Ulang Probe]
  ├── php artisan access:tenant-probe --samples=100
  └── Pastikan skor isolasi kembali ke 100.0%
```

---

## 4. Prosedur Tinjauan Pasca-Insiden Sesi Break-Glass (*Post-Incident Review*)

Setiap aktivasi hak istimewa darurat `BREAK_GLASS` (maksimal TTL 4 jam) WAJIB melalui proses telaah pasca-insiden dalam waktu 48 jam:

1. **Korelasi Tiket Insiden**: Verifikasi apakah insiden tiket yang dicantumkan (misal `INC-9941`) memang aktif dan membutuhkan hak istimewa darurat.
2. **Audit Sesi Perintah**: Tinjau seluruh query dan mutasi data yang dilakukan oleh pengguna selama sesi *break-glass* aktif.
3. **Pencabutan Formal**: Pastikan status grant telah berubah menjadi `EXPIRED` atau `REVOKED`.
4. **Penandatanganan Berita Acara**: Buat berita acara penutupan insiden yang ditandatangani oleh pejabat otorisasi sekunder (*authorizing officer*) dan CISO.

