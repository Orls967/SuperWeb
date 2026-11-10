# Definition of Ready (DoR) — Fase 194

**Fase:** 194 — Platform: Scope-Aware Global Search, Enterprise Command Palette & Kotak Masuk "Tugas Saya"  
**Klasifikasi:** Standar Lebar (Ambang Keras: B1 ≥ 7.000, B2 ≥ 2.800, B3 ≥ 1.500)  
**Merge-Base SHA Awal:** `ad92ae6647b838dcbbce91e424fc15336c13b651`

---

## 1. Kriteria Terima (Acceptance Criteria) Terukur

- **AC-194.1 (Scope-Aware Inverted Index & BM25 Relevance):**  
  Indeks pencarian teks penuh terbalik (`core_search_indices`) mengindeks entitas dari setidaknya 6 modul bisnis (`AutoServe`, `AutoDex`, `Logistics`, `Contract`, `Asset`, `Marine`, `Party`). Algoritma `Bm25Scorer` menghitung skor relevansi secara deterministik (berbasis integer bps) dengan pemfilteran RBAC terdepan (policy-first, zero-leak IDOR protection).

- **AC-194.2 (Fuzzy Trigram & Spell Correction):**  
  Algoritma `TrigramJaccardMatcher` memberikan toleransi kesalahan pengetikan kueri leksikal (saltik) dengan koefisien Jaccard $\ge 0.35$ dan memberikan saran "mungkin maksud Anda" (did-you-mean suggestion) saat kueri pengguna menghasilkan hasil nol.

- **AC-194.3 (State Machine 8 Status Pekerjaan Indeks):**  
  Siklus pekerjaan pengindeksan dokumen diatur oleh `IndexJobStateMachine` dengan 8 status: `pending`, `queued`, `processing`, `indexed`, `reindexing`, `degraded`, `failed`, `archived`. Setiap mutasi status dijamin oleh idempotency key deterministik dan audit log dua arah.

- **AC-194.4 (Kotak Masuk "Tugas Saya" Terpadu §19.1):**  
  Pengguna memiliki antarmuka Kotak Masuk Tugas Saya (`/tugas-saya`) yang mengonsolidasikan semua item persetujuan, tinjauan, dan tugas lintas modul dengan kemampuan aksi langsung (Setujui, Tolak, Tinjau), lencana prioritas/tenggat waktu, serta filter kategori.

- **AC-194.5 (Console Commands & Background Automation):**  
  Tersedia 5 command artisan: `search:reindex`, `search:audit`, `search:zero-result-report`, `inbox:digest`, dan `inbox:escalate`. Perintah `search:audit` memverifikasi konsistensi dua sumber antara data entitas asli dan indeks pencarian.

---

## 2. Rencana Anggaran Biaya (RAB) Baris Kode

| Kategori | Target Ambang | Rencana Realisasi | Komponen Pembentuk |
|---|---|---|---|
| **B1: Logika PHP** | **≥ 7.000** | **~7.250** | Algoritma A4 `Bm25Scorer` (450), A5 `TrigramJaccardMatcher` (400), A6 `PolicyFirstRanker` (450); Verifier Sertifikat Algoritma (350); State Machine 8 Status `IndexJobStateMachine` (450); Model & Migrasi `core_search_*` dan `core_task_*` (500); Search Adaptors 7 modul (1.400); Layanan Kotak Masuk `TaskInboxService` (550); Action Actions Pencarian & Indeksasi (1.100); Console Commands (950); Controllers & Form Requests (650). |
| **B2: UI / Blade / CSS** | **≥ 2.800** | **~3.050** | Modal Command Palette Global Search (650); Halaman Kotak Masuk Tugas Saya `/tugas-saya` (850); Komponen Task List & Urgency Badge (450); Halaman Analitik & Laporan Pencarian `/admin/search/analytics` (650); Modal Aksi Cepat Persetujuan Tugas (450). |
| **B3: Dokumentasi Tulis Tangan** | **≥ 1.500** | **~1.750** | `docs/dor/bukti-l0-fase-194.md` (150); `docs/dor/fase-194.md` (200); `docs/design/fase-194.md` (350); `docs/decisions/ADR-0194-*.md` (250); 3 Dokumen Algoritma (500); Panduan & Runbook (300). |

---

## 3. Matriks Hak Akses & Jalan Masuk

| Rute Web / API | Metode | Middleware Peran | Fungsi |
|---|---|---|---|
| `/tugas-saya` | GET | `auth`, `role:admin,operator,manager,approver,viewer` | Halaman Kotak Masuk "Tugas Saya" pengguna |
| `/tugas-saya/{id}/action` | POST | `auth`, `role:admin,operator,manager,approver` | Eksekusi aksi tugas (setujui, tolak, delegasikan) |
| `/api/global-search` | GET | `auth`, `role:admin,operator,manager,approver,viewer` | API pencarian leksikal cepat BM25 + Jaccard |
| `/admin/search/analytics` | GET | `auth`, `role:admin,system_architect` | Dasbor analitik kueri, kata kunci nol hasil, & latensi |
| `/admin/search/reindex` | POST | `auth`, `role:admin,system_architect` | Pemantik pekerjaan reindex katalog pencarian |

---

## 4. Batasan Ketat Pemilik (Guarantees)

1. **IZIN-1 (Tanpa Modul Baru):** Semua tabel database menggunakan prefiks `core_` (`core_search_indices`, `core_search_index_jobs`, `core_search_query_logs`, `core_tasks`, `core_task_histories`).
2. **IZIN-2 (Tanpa Dependensi Baru):** Seluruh algoritma tokenisasi leksikal, BM25, Trigram, dan Jaccard diimplementasikan murni PHP 8.4 native tanpa Elasticsearch, Algolia, Meilisearch, atau paket Composer pihak ketiga.
3. **IZIN-3 (Integritas Kode Lama):** Kelas `GlobalSearchService` di modul `Integration` dibiarkan utuh dengan tag `@deprecated`.
4. **Maksimal 5 Acceptance Test:** Acceptance test bertempat di `tests/Acceptance/SearchNav/SearchNavAcceptanceTest.php`.

