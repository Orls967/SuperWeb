# Tanya Jawab & Panduan Pemecahan Masalah Platform Rilis (FAQ)

Dokumen ini merangkum pertanyaan umum dan pemecahan kendala teknis yang sering ditemui saat menjalankan perkakas rilis modul Platform.

---

## 1. Pertanyaan yang Sering Diajukan (FAQ)

### T: Mengapa `platform:migration-lint` menolak migrasi saya yang berisi `dropColumn`?
**J**: Modul Platform menerapkan kebijakan zero-downtime yang melarang operasi DDL destruktif langsung. Gunakan pola *Expand and Contract*: hilangkan referensi kode pada rilis pertama, kemudian hapus kolom pada rilis berikutnya setelah kode stabil di produksi.

### T: Bagaimana cara melewati pemeriksaan risiko CAB untuk hotfix mendesak di tengah malam?
**J**: Gunakan opsi `--emergency` dan tautkan nomor tiket insiden SEV-1 aktif. Sistem akan mengaktifkan `EmergencyApprovalBypassGuard` dan mewajibkan review retrospektif dalam kurun 24 jam setelah pemulihan.

### T: Mengapa deployment canary saya otomatis dibatalkan padahal error hanya naik sedikit?
**J**: Algoritma A6 (`CanaryErrorEvaluator`) menghitung delta tingkat kesalahan terhadap baseline. Jika selisih galat melampaui 5% atau menghabiskan anggaran kesalahan dengan laju burn rate di atas 14.4x dalam jendela 1 jam, sistem secara otomatis mengeksekusi auto-rollback demi keselamatan pengguna.

---

## 2. Pemecahan Masalah (Troubleshooting)

| Masalah | Penyebab Umum | Solusi Langkah Demi Langkah |
|---|---|---|
| Kunci Lingkungan Macet (`EnvironmentLockedException`) | Sesi deployment sebelumnya mati tanpa melepas lease | Jalankan `php artisan platform:environment-unlock --environment=<env>` |
| Job Backfill Tertahan di Status `processing` | Worker terbunuh oleh Linux OOM-killer | Jalankan `php artisan platform:backfill-resume --stale-minutes=15` |
| Hash Jejak Audit Tidak Valid (`AuditTamperingException`) | Ada manipulasi manual langsung pada tabel database | Lakukan investigasi forensik dan sinkronkan ulang rantai hash |
