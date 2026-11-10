# ADR-0195: Arsitektur Kotak Masuk "Tugas Saya" & Eskalasi Otomatis SLA

**Nomor ADR:** `ADR-0195`  
**Status:** Diterima (*Accepted*)  
**Tanggal:** 2026-11-10  
**Penulis:** Pelaksana W8-P2  
**Keputusan Terkait:** `W8-D29`, §19.1

---

## Konteks Bisnis & Masalah

Pengguna SuperWeb yang mengelola alur persetujuan lintas divisi sering mengalami penumpukan berkas karena ketiadaan agregator tugas yang terpusat. Untuk mengoptimalkan kecepatan operasi bisnis (*business velocity*), diperlukan satu portal terpadu di mana seluruh item persetujuan dari 30 lini dapat dilihat, disortir berdasarkan urgensi, dan disetujui dalam satu klik tanpa harus masuk ke masing-masing menu modul.

---

## Keputusan Arsitektur

1. **Pusat Data Tugas Tunggal di Modul Core:**  
   Seluruh permintaan tugas disimpan dalam tabel `core_tasks` dan `core_task_histories`. Modul-modul bisnis mendaftarkan listener event atau memanggil `TaskInboxService::createTask()` saat entitas bisnis membutuhkan tindakan pengguna.

2. **Perlindungan Anti-IDOR pada Eksekusi Aksi:**  
   Setiap aksi (Setujui, Tolak, Delegasikan) memvalidasi bahwa `user->id` atau `user->role` cocok dengan `assigned_user_id` atau `assigned_role` tugas. Pelanggaran langsung menghasilkan HTTP 403 Forbidden.

3. **Idempotency Key Deterministik:**  
   Setiap mutasi status tugas mewajibkan parameter `idempotency_key` dalam format deterministik:  
   `task-action:{task_id}:{target_status}:{actor_id}`.

4. **Daemon Eskalasi Terjadwal (`inbox:escalate`):**  
   Pekerjaan cron berkala memindai tugas yang melewati `due_date` dan menaikkan status menjadi `ESCALATED`, memicu notifikasi peringatan eskalasi kepada supervisor penanggung jawab.

---

## Konsekuensi

- **Positif:** Mengurangi waktu tunggu persetujuan dokumen operasional hingga lebih dari 70%; meningkatkan kepatuhan SLA korporat; memberikan transparansi penuh jejak audit tindakan persetujuan.
- **Batasan:** Setiap modul yang memiliki alur kerja persetujuan wajib memancarkan event ke `TaskInboxService` agar tugas terdaftar di kotak masuk pengguna.

