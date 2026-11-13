# ADR-042: State Machine 7-Status Tugas Gudang & Pengujian Sabotase Audit

- **Status**: Diterima
- **Tanggal**: 2026-11-13
- **Konteks**: Fase 41 (Gudang & Pusat Distribusi — WMS Lanjutan)
- **Penulis**: Pelaksana [W8-P6]

---

## 1. Konteks Masalah
Pada versi awal modul `Wms`, status tugas gudang hanya menggunakan tiga status sederhana (`open`, `in_progress`, `completed`) tanpa catatan riwayat transisi maupun mekanisme penanganan pengecualian di lapangan (*exception handling* seperti bin penuh, barang rusak, atau selisih scan). Selain itu, perintah `wms:audit` hanya memeriksa stok negatif tanpa menguji apakah mekanisme audit benar-benar gagal ketika data sengaja dikorupsi.

---

## 2. Keputusan Arsitektur

### 2.1 State Machine 7-Status (`WarehouseTaskStateMachine`)
Seluruh transisi status `Task` wajib melewati `WarehouseTaskStateMachine::transition()` dengan tujuh status resmi (D41-1):
1. `created` — tugas baru terbentuk (kompatibel dengan status legacy `open` via normalisasi otomatis).
2. `assigned` — tugas sudah ditugaskan ke operator tertentu (`assigned_to`).
3. `in_progress` — operator sedang mengerjakan tugas di lorong (`started_at`).
4. `confirmed` — kuantitas & bin terkonfirmasi secara fisik (`completed_at`).
5. `exception` — terjadi kendala lapangan yang memerlukan intervensi atau retry.
6. `cancelled` — tugas dibatalkan sebelum selesai (status terminal).
7. `closed` — tugas selesai penuh dan terarsip (status terminal).

### 2.2 Log Audit Append-Only (`wms_task_events`)
Setiap panggilan `transition()` menyisipkan satu baris ke tabel `wms_task_events` yang bersifat *append-only* (tidak ada operasi `UPDATE`/`DELETE` pada log event). Ini memungkinkan pelacakan lengkap siapa yang mengeksekusi setiap langkah dan berapa lama durasi pengerjaan per operator.

### 2.3 Pengujian Sabotase Audit (`WmsAuditCorruptionFixture` — X41-5)
Untuk membuktikan bahwa `php artisan wms:audit` bukanlah pengecekan kosong (*false-green*), disediakan kelas `WmsAuditCorruptionFixture` dan suite pengujian `WmsLedgerAndAuditSabotageTest`:
- Pada kondisi bersih, `wms:audit` mengembalikan exit code `0`.
- Ketika `WmsAuditCorruptionFixture` menyuntikkan baris `wms_bin_stocks` dengan `qty_base = -5`, `wms:audit` wajib mendeteksi `bin_stocks.negatif` dan mengembalikan exit code `1`.

---

## 3. Konsekuensi
- Tidak ada controller atau action yang boleh mengubah kolom `wms_tasks.status` secara langsung tanpa melewati `WarehouseTaskStateMachine`.
- Seluruh tugas yang macet di status `exception` ditangani secara eksplisit melalui `ResolveTaskExceptionAction`.
- Regresi pada perintah `wms:audit` akan langsung tertangkap oleh CI melalui pengujian sabotase L.5.
