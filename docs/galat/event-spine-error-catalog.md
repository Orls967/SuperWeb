# Katalog Galat & Penanganan Insiden — Universal Event Spine (Fase R8)

Dokumen referensi resmi kode kesalahan, penyebab akar, dampak operasional, dan panduan pemulihan sistem untuk Event Spine SuperWeb.

---

## 1. Kode Galat Algoritma & Skema (A4, A5, A6)

### `ESP-A4-001`: Aggregate Sequence Gap Detected
- **Tingkat Keparahan**: Peringatan (Warning) / Kritis jika melampaui SLA 30 menit.
- **Penyebab**: Ditemukan celah nomor urut pada aggregate stream tertentu (mis. pesan 4 dan 7 ada, tetapi pesan 5 dan 6 hilang).
- **Dampak**: Konsumen yang membutuhkan pemrosesan urut (*strict FIFO*) berpotensi menerima status yang tidak lengkap.
- **Tindakan Pemulihan**:
  1. Periksa `core_spine_gaps` melalui konsol `/admin/spine/gaps`.
  2. Periksa apakah pesan yang hilang masih berstatus `pending` di outbox pengirim.
  3. Jalankan `php artisan spine:gap-scan` untuk verifikasi apakah celah telah terisi.

### `ESP-A4-002`: Duplicate Sequence Number Detected
- **Tingkat Keparahan**: Peringatan (Warning).
- **Penyebab**: Nomor urut yang sama dipancarkan dua kali untuk aggregate ID yang identik.
- **Dampak**: Pesan duplikat akan ditolak oleh `core_consumer_inbox` (`claim() = false`), tidak ada efek samping ganda.
- **Tindakan Pemulihan**: Investigasi bug pada modul pengirim yang memicu pemancaran ganda tanpa memajukan nomor sequence.

### `ESP-A6-001`: Schema Breaking Change Rejected (`PROPERTY_REMOVED`)
- **Tingkat Keparahan**: Error (Pendaftaran Skema Ditolak).
- **Penyebab**: Modul mencoba mendaftarkan versi baru dari skema event yang menghapus properti yang sebelumnya ada.
- **Dampak**: Registrasi ditolak dengan `SchemaBreakingChangeException`.
- **Tindakan Pemulihan**: Pertahankan properti lama dengan nilai nullable/opsional, atau buat versi event baru dengan mayor increment (mis. `v2`).

### `ESP-A6-002`: Schema Breaking Change Rejected (`REQUIRED_ADDED`)
- **Tingkat Keparahan**: Error (Pendaftaran Skema Ditolak).
- **Penyebab**: Modul menambahkan properti baru langsung ke daftar `required`, yang menyebabkan produsen lama tidak dapat memancarkan event.
- **Tindakan Pemulihan**: Jadikan properti baru bersifat opsional pada versi berjalan, atau naikkan versi event.

---

## 2. Kode Galat Pengiriman & Dead-Letter Queue (DLQ)

### `ESP-DLQ-001`: Max Retry Limit Exceeded (`dead_lettered`)
- **Tingkat Keparahan**: Tinggi (High).
- **Penyebab**: Handler konsumen gagal memproses pesan sebanyak 6 kali berturut-turut setelah menerapkan backoff Algoritma A5.
- **Dampak**: Pesan dipindahkan ke status `dead_lettered` dan dicatat di antrean DLQ. Offset stream konsumen tetap dapat berjalan jika tidak diblokir.
- **Tindakan Pemulihan**:
  1. Buka antarmuka DLQ di `/admin/spine/dlq`.
  2. Periksa kolom `last_error` dan `payload`.
  3. Setelah perbaikan dependensi eksternal/bug, lakukan replay via `php artisan spine:dlq-replay --id=<delivery_id>`.

### `ESP-DLQ-002`: Poison Message Quarantined
- **Tingkat Keparahan**: Kritis (Critical).
- **Penyebab**: Pesan memicu kegagalan berulang 3 kali pada validasi skema dasar atau unhandled fatal exception di handler.
- **Dampak**: Pesan diisolasi ke `core_poison_messages` agar offset konsumen dapat melompat maju tanpa kemacetan.
- **Tindakan Pemulihan**:
  1. Periksa tabel `core_poison_messages` di `/admin/spine/dlq?tab=poison`.
  2. Koreksi payload yang cacat atau perbaiki parser modul penerima.

### `ESP-AUD-001`: Dual-Source Audit Corruption Detected
- **Tingkat Keparahan**: Bencana (Fatal Integrity Violation).
- **Penyebab**: Perintah `spine:audit` mendeteksi perbedaan hitungan pesan outbox vs spine, status delivered tanpa entri inbox, atau offset melampaui event terakhir.
- **Tindakan Pemulihan**: Hentikan pipeline, telusuri log transaksi database pada rentang offset terkait, dan jalankan sinkronisasi rekonsiliasi.

