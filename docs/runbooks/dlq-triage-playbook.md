# Playbook Triase & Penanganan Dead-Letter Queue (DLQ) Universal Event Spine

Dokumen ini merupakan panduan operasional standar (*Standard Operating Procedure* / SOP) bagi *Site Reliability Engineer* (SRE), staf operasional, dan pengembang backend dalam menangani insiden kegagalan pengiriman event, pesan beracun (*poison messages*), dan antrean surat mati (*Dead-Letter Queue*) pada Universal Event Spine.

---

## 1. Tingkat Keparahan Insiden & Matriks Eskalasi

| Kategori Insiden | Kriteria Pemicu | Target Waktu Respon (MTTA) | Target Waktu Pemulihan (MTTR) | Kanal Eskalasi |
| :--- | :--- | :--- | :--- | :--- |
| **SEV-1 (Kritis)** | DLQ berisi event domain keuangan/kesehatan > 10 pesan ATAU ada poison message berulang pada alur pembayaran/IGD. | $\le 15$ menit | $\le 1$ jam | PagerDuty SRE, WhatsApp Darurat, TaskInbox Urgent |
| **SEV-2 (Mayor)** | DLQ bertambah dengan laju > 5 pesan/menit pada modul logistik/pengadaan ATAU lag P95 consumer group > 10.000 pesan. | $\le 30$ menit | $\le 4$ jam | TaskInbox Spine Operator, Notifikasi Slack/Teams |
| **SEV-3 (Minor)** | DLQ terisolasi pada modul analitik/telematika non-kritis dengan pesan gagal < 50. | $\le 4$ jam | $\le 24$ jam | Laporan Harian DLQ Dashboard |

---

## 2. Alur Triase Insiden DLQ Langkah demi Langkah

```
                           [Insiden DLQ Terdeteksi]
                                       │
                                       ▼
                  [Langkah 1: Identifikasi Topik & Konsumen]
                 Buka Konsol Admin: /admin/spine/dlq
                 Jalankan CLI: php artisan spine:dlq-replay --dry-run
                                       │
                                       ▼
                  [Langkah 2: Klasifikasi Kategori Kegagalan]
                 ┌─────────────────────┴─────────────────────┐
                 │                                           │
         [Transient / Jaringan]                     [Permanent / Bug Koding]
         (DB Lock, Timeout, Downstream API)         (Schema Breaking, NullPointer)
                 │                                           │
                 ▼                                           ▼
      [Langkah 3A: Verifikasi Jaringan]          [Langkah 3B: Karantina & Hotfix]
      Periksa ketersediaan modul hilir           Pesan otomatis masuk core_poison_messages
                 │                               Deploy patch koding / hotfix migration
                 │                                           │
                 └─────────────────────┬─────────────────────┘
                                       │
                                       ▼
                  [Langkah 4: Prosedur Replay Dua-Langkah]
                  Kuantitas $\le 20$: Eksekusi mandiri operator
                  Kuantitas $> 20$: ApprovalEngine (Persetujuan Manajer)
                                       │
                                       ▼
                  [Langkah 5: Verifikasi Pasca-Replay]
                  Audit parity hash & verifikasi status delivered
```

---

## 3. Prosedur Investigasi Teknis

### 3.1 Menginspeksi Rekaman DLQ via CLI
Gunakan perintah artisan untuk memeriksa distribusi kegagalan per grup konsumen:
```bash
# Menampilkan ringkasan lag dan status DLQ per grup konsumen
php artisan spine:lag-report

# Menampilkan 10 pesan DLQ teratas dengan rincian galat
php artisan spine:audit --limit=10
```

### 3.2 Menelaah Log Galat & Riwayat Transisi Status
Setiap pengiriman gagal memiliki jejak transisi di `core_event_deliveries`:
- Cek kolom `last_error` untuk melihat pesan eksepsi dan baris kode sumber.
- Cek `retry_count`: jika telah mencapai `max_retries` (default: 5), pesan otomatis dialihkan ke status `dead_lettered`.
- Cek apakah pesan ditandai sebagai `poison`: jika exception berupa syntax error atau invalid JSON schema yang tidak dapat dipulihkan dengan retry, pesan dipindahkan ke `core_poison_messages` untuk menghindari penahanan antrean (*unblocking head-of-line*).

---

## 4. Prosedur Replay Pesan Terkendali

### 4.1 Replay Batch Standar ($\le 20$ Pesan)
Untuk pesan yang gagal akibat masalah sementara (misal database lock atau restart layanan):
1. Buka antarmuka web `/admin/spine/dlq`.
2. Pilih checkbox pesan yang ingin di-replay atau masukkan ID pengiriman.
3. Klik tombol **Replay Batch**.
4. Sistem akan mengubah status pesan dari `dead_lettered` menjadi `replayed`, mereset `retry_count` ke 0, dan menjadwalkannya untuk dieksekusi oleh consumer worker pada iterasi berikutnya.

Melalui terminal:
```bash
php artisan spine:dlq-replay --consumer=finance_gl_poster --limit=20
```

### 4.2 Replay Batch Skala Besar ($> 20$ Pesan) — Wajib ApprovalEngine
Untuk melindungi sistem dari badai pemrosesan (*thundering herd problem*) dan memastikan integritas data keuangan:
1. Replay lebih dari 20 pesan secara serentak **wajib** mengajukan proposal ke `ApprovalEngine`.
2. Operator memasukkan alasan bisnis dan estimasi dampak.
3. Notifikasi persetujuan dikirim ke Manajer Operasional TI / Head of Engineering.
4. Setelah tiket disetujui, perintah replay dapat dieksekusi dengan menyertakan token approval:
```bash
php artisan spine:dlq-replay --consumer=logistics_tracker --batch-size=100 --approval-ticket=APP-2026-9912
```

---

## 5. Prosedur Pembuangan Pesan (*Discard*) Ber-Alasan

Dalam kondisi tertentu di mana pesan kadaluwarsa atau event duplikat yang tidak valid lagi (misal event GPS ping kendaraan dari 3 minggu lalu yang terpendam):
1. Pesan tidak boleh dihapus secara fisik dari database (*hard delete* dilarang keras).
2. Pesan harus ditransisikan ke status `discarded`.
3. Operator wajib mengisi kolom `discard_reason` dengan format:
   `[KODE_ALASAN] Penjelasan bisnis detail dan nomor tiket referensi`
   Contoh: `[EXPIRED_TELEMETRY] Data telematika GPS usang > 7 hari pasca pemeliharaan server (INC-4881)`.
4. Jika jumlah discard $> 20$ pesan, proses pembuangan juga memerlukan verifikasi ApprovalEngine tingkat 1.

---

## 6. Prosedur Penanganan Pesan Beracun (*Poison Message*)

Jika terdapat pesan dengan payload rusak atau memicu bug PHP Fatal / Segmentation Fault:
1. **Isolasi Non-Blocking**: Sistem secara otomatis mengisolasi pesan ke tabel `core_poison_messages` sehingga pesan berikutnya dalam antrean dapat terus berjalan lancar.
2. **Reproduksi Masalah di Lingkungan Staging**:
   - Salin isi kolom `payload` dari tabel `core_poison_messages`.
   - Buat unit test isolasi di `tests/Unit/Core/` dengan menggunakan payload tersebut sebagai input.
3. **Penyebaran Perbaikan Kode**:
   - Terapkan patch pada consumer listener yang menangani event tersebut.
   - Deploy kode baru ke kluster produksi.
4. **Re-Inject Pesan Beracun**:
   - Setelah bug diperbaiki, jalankan aksi *re-inject* via tombol di konsol admin atau method `PoisonMessageQuarantineService::release()`.
   - Pesan akan dikembalikan ke status `pending` di `core_event_deliveries` untuk diproses ulang dengan kode yang telah diperbaiki.


---

## 7. Skrip CLI Otomasi Investigasi Cepat

Untuk mempercepat triase saat insiden SEV-1 atau SEV-2 berlangsung di jam sibuk, SRE dapat menggunakan pintasan skrip artisan berikut:

```bash
# 1. Periksa ringkasan metrik kesehatan spine secara menyeluruh
php artisan spine:audit

# 2. Pantau laju antrean dan lag konsumen secara real-time
php artisan spine:lag-report

# 3. Pindai anomali celah urutan aggregate (A4)
php artisan spine:gap-scan --fix=false

# 4. Tampilkan 5 pesan beracun terbaru yang diisolasi
php artisan tinker --execute="
    \Modules\Core\Domain\Models\CorePoisonMessage::latest()
        ->take(5)
        ->get(['id', 'topic', 'exception_class', 'message_key', 'created_at'])
        ->dump();
"
```

---

## 8. Panduan Komunikasi Insiden (Template Status Page)

Saat terjadi lonjakan antrean DLQ yang berdampak pada keterlambatan sinkronisasi data antar-modul:

### 8.1 Pemberitahuan Awal (Investigating)
> **Pemberitahuan Insiden**: Keterlambatan Pemrosesan Event Sinkronisasi Antar-Modul  
> **Waktu Mulai**: [YYYY-MM-DD HH:mm WIB]  
> **Dampak**: Layanan penerbitan invoice dan pelacakan pengiriman logistik mengalami keterlambatan pembaruan status antara 5 hingga 15 menit. Transaksi pelanggan tetap tercatat dengan aman di outbox.  
> **Tindakan**: Tim SRE sedang menginvestigasi antrean DLQ dan melakukan isolasi pesan beracun. Pembaruan berikutnya diberikan dalam 15 menit.

### 8.2 Pemulihan (Resolved)
> **Pembaruan Insiden**: Layanan Sinkronisasi Event Telah Normal Kembali  
> **Waktu Selesai**: [YYYY-MM-DD HH:mm WIB]  
> **Penyebab**: Ditemukan kegagalan parsing format waktu ISO-8601 pada konsumen modul analitik yang telah diisolasi ke karantina non-blocking.  
> **Tindakan Pasca-Insiden**: Replay batch 48 pesan telah berhasil dieksekusi 100% tanpa inkonsistensi saldo. Log audit dan parity hash telah diverifikasi identik.
