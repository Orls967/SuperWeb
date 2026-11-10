# Konsep Arsitektur: Kotak Masuk "Tugas Saya" & Orkestrasinya Lintas Modul (§19.1)

**Fase:** 194 — Platform: Unified Task Inbox & Action Center  
**Klasifikasi:** Fondasi Operasional Enterprise  
**Terkait:** §19.1 Rencana Gelombang 8

---

## 1. Definisi & Ruang Lingkup Masalah

Sebelum implementasi Fase 194, setiap modul di SuperWeb memiliki alur persetujuan terpisah yang mengharuskan pejabat otoritas (seperti Supervisor, Finance Manager, Operations Director, atau Legal Counsel) untuk masuk ke masing-masing menu modul secara berkala guna memeriksa apakah ada pekerjaan tertunda.

Hal ini menimbulkan masalah operasional serius:
1. **Bottleneck Persetujuan (Approval Latency):** Dokumen penting seperti pesanan suku cadang darurat, walk-overbooking hotel, atau addendum kontrak terhambat berhari-hari karena approver tidak menyadari adanya permohonan baru.
2. **Ketiadaan Visibilitas Prioritas Terpadu:** Pengguna tidak dapat melihat mana pekerjaan yang paling kritis dan mendekati tenggat waktu secara lintas modul.
3. **Penyebaran Log Audit Aksi:** Riwayat siapa yang menyetujui atau menolak permohonan tersebar di puluhan tabel berbeda tanpa format pelacakan terpadu.

Kotak Masuk "Tugas Saya" (`/tugas-saya` atau `/tasks`) menyelesaikan masalah ini dengan menyediakan **satu pusat aksi kerja terpadu** bagi seluruh pengguna di SuperWeb.

---

## 2. Model Data Terpadu: `core_tasks` & `core_task_histories`

Setiap pekerjaan atau permintaan persetujuan dimodelkan sebagai rekaman terpadu pada tabel `core_tasks`.

### Kolom-Kolom Kunci Entitas:
- `task_uuid`: Pengidentifikasi global unik deterministik berbasis UUIDv4.
- `title`: Judul ringkas pekerjaan yang dapat dipahami tanpa perlu membuka dokumen asal.
- `module`: Kode modul sumber (misal `AutoServe`, `Hotel`, `Contract`, `Marine`, `Platform`).
- `task_type`: Klasifikasi jenis tugas:
  - `APPROVAL`: Membutuhkan persetujuan biner (Setuju / Tolak).
  - `REVIEW`: Membutuhkan peninjauan dokumen dan umpan balik kepatuhan.
  - `ACTION`: Tindakan operasional langsung (misal: verifikasi fisik aset, serah terima barang).
  - `AUDIT`: Permintaan klarifikasi atau penyelesaian temuan audit internal.
- `priority`: Tingkat urgensi tugas:
  - `CRITICAL`: Harus diselesaikan dalam waktu $< 4$ jam.
  - `HIGH`: Harus diselesaikan dalam waktu $< 24$ jam.
  - `MEDIUM`: Harus diselesaikan dalam waktu $< 3$ hari.
  - `LOW`: Rutin tanpa tenggat waktu mendesak.
- `status`: Status siklus hidup tugas:
  - `PENDING`: Menunggu tindakan dari penerima tugas.
  - `IN_PROGRESS`: Sedang dalam proses pengerjaan oleh penerima tugas.
  - `COMPLETED`: Telah disetujui atau diselesaikan.
  - `REJECTED`: Permohonan ditolak oleh approver.
  - `ESCALATED`: Tugas telah melewati tenggat waktu dan dialihkan ke atasan langsung.
  - `DELEGATED`: Tugas dialihkan kepada rekan kerja lain.
- `assigned_role`: Peran organisasi yang berhak mengeksekusi tugas.
- `assigned_user_id`: Pengguna spesifik jika tugas ditugaskan secara personal.
- `due_date`: Batas waktu penyelesaian tugas (SLA).
- `action_url`: URL langsung ke halaman rincian transaksi sumber.

---

## 3. Protokol Eskalasi Otomatis & Notifikasi Digest

Sistem menyediakan dua mekanisme otomatis untuk menjamin SLA organisasi:

1. **Daemon Eskalasi Terjadwal (`inbox:escalate`):**  
   Daemon yang berjalan setiap jam memeriksa seluruh tugas dengan status `PENDING` atau `IN_PROGRESS`. Apabila waktu saat ini melampaui `due_date`:
   - Status tugas diubah secara atomik menjadi `ESCALATED`.
   - Riwayat transisi dicatat di `core_task_histories` dengan aktor `SYSTEM_ESCALATION_DAEMON`.
   - Tugas diberi lencana visual merah menyala `ESCALATED` pada antarmuka web pengguna.

2. **Ringkasan Notifikasi Harian (`inbox:digest`):**  
   Perintah konsol `inbox:digest` mengumpulkan rekapitulasi tugas aktif untuk setiap pengguna/peran dan mengirimkan ringkasan notifikasi setiap pagi, memuat:
   - Jumlah tugas pending baru.
   - Daftar tugas yang mendekati jatuh tempo hari ini.
   - Tautan langsung satu klik menuju kotak masuk.

---

## 4. Antarmuka Pengguna & Integrasi Desain Sistem (`x-ds.*`)

Antarmuka Kotak Masuk "Tugas Saya" dibangun 100% menggunakan komponen kanonikal Blade `x-ds.*` yang telah dirilis pada Fase 240:
- `x-ds.shell`: Kerangka tata letak terpadu dengan slot navigasi dan banner otorisasi.
- `x-ds.tabs`: Tab navigasi filter status tugas tanpa reload halaman.
- `x-ds.badge`: Lencana prioritas berwarna semantik (`red` untuk CRITICAL, `amber` untuk HIGH, `blue` untuk MEDIUM, `neutral` untuk LOW).
- `x-ds.button`: Tombol aksi cepat dengan varian warna kanonikal (`primary`, `danger`, `outline`).
- `x-ds.modal`: Dialog konfirmasi saat pengguna melakukan aksi penolakan atau delegasi tugas.
- `x-ds.toast`: Notifikasi umpan balik sukses seketika setelah aksi tugas berhasil dieksekusi.

