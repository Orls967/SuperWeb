# Skema Tabel dan Relasi Search & Inbox

## Arsitektur Data Persisten
Pencarian global dan Kotak Masuk "Tugas Saya" menyimpan representasi data terindeks dan siklus tugas pada tabel-tabel berprefiks `core_` di dalam modul `Core`. Seluruh tabel menggunakan UUIDv4 atau string ID terstandarisasi untuk integritas multi-tenant.

## Daftar Tabel dan Spesifikasi Kolom

### 1. `core_search_indices`
Menyimpan dokumen kanonikal yang siap disajikan ke pengguna.
- `id` (string/uuid, primary key): Identifier unik dokumen indeks.
- `module` (string): Nama modul asal (misal: `AutoServe`, `AutoDex`, `Contract`, `Asset`, `Marine`, `Party`, `Logistics`).
- `entity_type` (string): Tipe entitas (misal: `WorkOrder`, `Invoice`, `Vehicle`, `ContractAgreement`).
- `entity_id` (string): Primary key asli dari entitas domain.
- `title` (string): Judul atau label representatif entitas.
- `content` (text): Isi teks terindeks ringkas.
- `metadata` (json): Konteks atribut tambahan (kode referensi, tanggal, status bisnis, tautan URL).
- `required_permission` (string, nullable): Izin RBAC minimal untuk membaca dokumen ini.
- `scope_type` (string, nullable): Tipe pembatasan cakupan (misal: `branch`, `department`, `vendor`).
- `scope_id` (string, nullable): Nilai pembatasan cakupan.
- `document_length` (integer): Jumlah token unik dalam dokumen untuk normalisasi BM25.
- `indexed_at` (datetime): Waktu dokumen terakhir kali diproses.
- `timestamps`: `created_at`, `updated_at`.

### 2. `core_search_terms`
Tabel inverted index yang memetakan token term ke entitas dokumen.
- `id` (bigint auto-increment, primary key).
- `search_index_id` (string/uuid, foreign key ke `core_search_indices`).
- `term` (string, index): Kata atau token normalisasi.
- `term_hash` (string, index): Hash deterministik (misal CRC32 atau sha256) untuk lookup cepat.
- `frequency` (integer): Jumlah kemunculan kata dalam dokumen tersebut.
- `field_weight` (integer): Bobot field (misal: title = 3, content = 1, metadata = 2).

### 3. `core_search_index_jobs`
Menyimpan riwayat dan status pekerjaan indexing asinkron maupun sinkron (State Machine 8 status).
- `id` (string/uuid, primary key).
- `job_type` (string): `full_reindex`, `incremental`, `entity_sync`, `audit_verify`.
- `module` (string, nullable): Modul target.
- `status` (string): `pending`, `queued`, `processing`, `indexed`, `reindexing`, `degraded`, `failed`, `archived`.
- `records_total` (integer): Total catatan yang direncanakan.
- `records_processed` (integer): Catatan yang berhasil diproses.
- `error_message` (text, nullable): Pesan kesalahan bila terjadi kegagalan.
- `started_at` (datetime, nullable).
- `completed_at` (datetime, nullable).
- `payload` (json, nullable): Parameter tambahan eksekusi.
- `timestamps`: `created_at`, `updated_at`.

### 4. `core_search_query_logs`
Mencatat telemetri dan audit kueri pencarian (tanpa data sensitif/PII).
- `id` (bigint auto-increment, primary key).
- `user_id` (string, nullable): Pengguna yang mengeksekusi pencarian.
- `user_role` (string): Peran pengguna saat kueri dieksekusi.
- `raw_query_hash` (string): Hash kueri untuk analisis tanpa membocorkan PII rahasia.
- `redacted_query` (string): Teks kueri setelah masking nomor kartu/NIK/telepon.
- `results_count` (integer): Jumlah hasil yang ditemukan.
- `execution_time_ms` (decimal): Waktu eksekusi kueri dalam milidetik.
- `clicked_result_id` (string, nullable): ID dokumen yang diklik pengguna (telemetri relevansi).
- `created_at` (datetime).

### 5. `core_tasks`
Menyimpan tugas, persetujuan, dan tindakan kerja di Kotak Masuk "Tugas Saya".
- `id` (string/uuid, primary key).
- `title` (string): Ringkasan tugas yang harus ditindaklanjuti.
- `description` (text, nullable): Deskripsi detail instruksi tugas.
- `module` (string): Modul asal tugas (misal: `Procurement`, `Finance`, `Operations`).
- `source_entity_type` (string): Tipe entitas sumber (misal: `ApprovalRequest`, `WorkOrderReview`).
- `source_entity_id` (string): ID entitas sumber.
- `assignee_user_id` (string, nullable): ID pengguna penanggung jawab.
- `assignee_role` (string, nullable): Peran yang berhak mengeksekusi jika unassigned (misal: `manager`, `supervisor`).
- `status` (string): `pending`, `in_progress`, `approved`, `rejected`, `delegated`, `escalated`, `cancelled`, `completed`.
- `priority` (string): `low`, `normal`, `high`, `urgent`.
- `due_at` (datetime, nullable): Tenggat waktu penyelesaian tugas.
- `action_url` (string, nullable): URL tindakan langsung di antarmuka sistem.
- `payload` (json, nullable): Data kontekstual tindakan persetujuan/penolakan.
- `escalation_level` (integer, default 0): Tingkat eskalasi tugas jika melewati tenggat.
- `timestamps`: `created_at`, `updated_at`.

### 6. `core_task_histories`
Audit trail mutasi status tugas untuk integritas akuntabilitas.
- `id` (bigint auto-increment, primary key).
- `task_id` (string/uuid, foreign key ke `core_tasks`).
- `actor_id` (string, nullable): Pengguna yang memicu mutasi.
- `action` (string): Aksi yang diambil (`created`, `approved`, `rejected`, `delegated`, `escalated`, `completed`).
- `from_status` (string, nullable): Status sebelum mutasi.
- `to_status` (string): Status setelah mutasi.
- `notes` (text, nullable): Catatan justifikasi persetujuan atau alasan penolakan.
- `created_at` (datetime).
