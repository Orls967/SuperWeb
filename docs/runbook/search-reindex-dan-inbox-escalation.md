# Runbook: Pengindeksan Pencarian Ulang & Otomasi Eskalasi Kotak Masuk

**Fase:** 194 — Platform: Search & Inbox Maintenance  
**Sasaran:** Tim DevOps, System Administrator, dan SRE

---

## 1. Pemeliharaan Indeks Pencarian

### A. Membangun Ulang Indeks Penuh (Full Reindex)
Bila terjadi migrasi skema besar atau inisialisasi lingkungan baru, jalankan perintah reindex batch:
```bash
php artisan search:reindex --all --batch-size=500
```
Untuk mengindeks ulang modul tertentu saja:
```bash
php artisan search:reindex --module=AutoServe
php artisan search:reindex --module=Logistics
php artisan search:reindex --module=Contract
```

### B. Audit Konsistensi Dua Sumber Indeks (`search:audit`)
Perintah ini membandingkan data aktual pada tabel entitas bisnis terhadap catatan di tabel `core_search_indices` dan memvalidasi integritas state machine `core_search_index_jobs`:
```bash
php artisan search:audit
```
Kode keluar (*exit code*):
- `0`: Indeks 100% konsisten dengan data sumber (audit lulus).
- `1`: Terdeteksi diskrepansi (entitas belum terindeks atau hash isi tidak sinkron).

### C. Laporan Kueri Nol Hasil (`search:zero-result-report`)
Untuk menganalisis istilah yang sering dicari pengguna tetapi menghasilkan 0 hasil:
```bash
php artisan search:zero-result-report --limit=20 --days=7
```

---

## 2. Otomasi Eskalasi & Notifikasi Tugas

### A. Eskalasi Tugas Melewati Tenggat (`inbox:escalate`)
Perintah ini dipasang pada crontab sistem untuk berjalan setiap jam:
```cron
0 * * * * cd /path/to/superweb && php artisan inbox:escalate >> /var/log/superweb-escalate.log 2>&1
```
Logika eskalasi:
1. Memindai tabel `core_tasks` dengan status `PENDING` atau `IN_PROGRESS`.
2. Jika `now() > due_date`, status diubah menjadi `ESCALATED`.
3. Memperbarui riwayat di `core_task_histories` dengan pencatatan otomatis `SYSTEM_ESCALATION_DAEMON`.
4. Menerbitkan event notifikasi ke kanal pengingat manajer tingkat dua.

### B. Notifikasi Ringkasan Harian (`inbox:digest`)
Perintah ini mengirimkan ringkasan tugas aktif ke email/notifikasi pengguna setiap pukul 08:00 pagi:
```cron
0 8 * * * cd /path/to/superweb && php artisan inbox:digest --dry-run=false >> /var/log/superweb-digest.log 2>&1
```

