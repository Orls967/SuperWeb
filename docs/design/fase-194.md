# Desain Arsitektur & Spesifikasi Teknis — Fase 194

**Fase:** 194 — Platform: Scope-Aware Global Search, Enterprise Command Palette & Kotak Masuk "Tugas Saya"  
**Modul Pemilik:** `Core` & `Shared`  
**Status:** DRAFT (Menunggu Verifikasi & Penggabungan)

---

## 1. Arsitektur Komponen & Alur Data

Pencarian global dan kotak masuk tugas dibangun di atas tiga pilar utama:
1. **Pipa Indeksasi Terdistribusi (Distributed Indexing Pipeline):** Setiap modul bisnis mengimplementasikan `SearchProviderInterface` dan `TaskProviderInterface`. Pembaruan entitas bisnis memicu pembaruan indeks secara asinkron atau batch melalui `IndexJob`.
2. **Mesin Relevansi & Perankingan Multi-Kriteria (Ranking Engine):** Kueri pengguna diproses melalui tiga lapisan: Tokenizer & Trigram Generator -> BM25 Document Scorer -> Policy-First Ranker (evaluasi filter peran RBAC dan tenant scope).
3. **Pusat Aksi Tugas Saya (Task Inbox Action Center):** Konsolidasi seluruh item tugas lintas modul ke dalam tabel terpadu `core_tasks` dengan transisi status yang dapat diaudit dan dieksekusi secara instan dari antarmuka web.

```mermaid
flowchart TD
    User["Pengguna Web (Ctrl+K / /tugas-saya)"] --> Controller["GlobalSearchController / TaskInboxController"]
    Controller --> Engine["SearchOrchestratorService"]
    
    subgraph "Mesin Perankingan Deterministik"
        Engine --> BM25["A4: Bm25Scorer (Relevansi Term)"]
        Engine --> Trigram["A5: TrigramJaccardMatcher (Saltik & Koreksi)"]
        Engine --> Policy["A6: PolicyFirstRanker (RBAC & Tenant Filter)"]
    end
    
    subgraph "Penyedia Indeks Lintas Modul"
        BM25 --> DB_Index[("core_search_indices")]
        AutoServe["AutoServeSearchProvider"] --> DB_Index
        AutoDex["AutoDexSearchProvider"] --> DB_Index
        Logistics["LogisticsSearchProvider"] --> DB_Index
        Contract["ContractSearchProvider"] --> DB_Index
        Asset["AssetSearchProvider"] --> DB_Index
        Marine["MarineSearchProvider"] --> DB_Index
        Party["PartySearchProvider"] --> DB_Index
    end

    subgraph "Kotak Masuk Tugas Saya"
        Controller --> InboxEngine["TaskInboxService"]
        InboxEngine --> TaskDB[("core_tasks & core_task_histories")]
        InboxEngine --> Escalator["inbox:escalate Cron"]
        InboxEngine --> Digest["inbox:digest Notifikasi"]
    end
```

---

## 2. Diagram State Machine 8 Status: `IndexJobStateMachine`

Siklus pekerjaan pengindeksan data pencarian dikendalikan oleh mesin status 8 status deterministik:

```mermaid
flowchart TD
    Pending["1. PENDING (Pekerjaan dibuat)"] --> Queued["2. QUEUED (Masuk antrean job)"]
    Queued --> Processing["3. PROCESSING (Ekstraksi & tokenisasi)"]
    Processing --> Indexed["4. INDEXED (Berhasil masuk indeks)"]
    
    Indexed --> Reindexing["5. REINDEXING (Pembaruan data entitas)"]
    Reindexing --> Indexed
    
    Processing --> Degraded["6. DEGRADED (Sebagian entitas gagal)"]
    Degraded --> Reindexing
    
    Processing --> Failed["7. FAILED (Kegagalan total/eksepsi)"]
    Failed --> Queued
    
    Indexed --> Archived["8. ARCHIVED (Entitas dihapus dari indeks)"]
    Failed --> Archived
    Degraded --> Archived
```

### Matriks Transisi Status yang Sah:
| Status Asal | Status Target yang Diizinkan | Guard Condition |
|---|---|---|
| `pending` | `queued`, `archived` | Idempotency key terdaftar |
| `queued` | `processing`, `failed` | Worker alur kerja mengambil job |
| `processing` | `indexed`, `degraded`, `failed` | Parsial / total keberhasilan pemrosesan term |
| `indexed` | `reindexing`, `archived` | Mutasi entitas bisnis terdeteksi |
| `reindexing` | `indexed`, `degraded`, `failed` | Hasil indeks ulang tersedia |
| `degraded` | `reindexing`, `archived` | Percobaan remediasi parsial |
| `failed` | `queued`, `archived` | Retry backoff policy terpenuhi |
| `archived` | *(status terminal)* | Nihil |

---

## 3. Skema Database Entitas (`core_search_*` & `core_task_*`)

```mermaid
erDiagram
    core_search_indices ||--o{ core_search_terms : "memiliki relasi"
    core_search_indices {
        bigint id PK
        string entity_type "AUTOSERVE, LOGISTICS, CONTRACT, dll"
        string entity_id "UUID atau ID string entitas"
        string title "Judul ringkas entitas"
        text content "Teks penuh yang diindeks"
        string route_url "Tautan navigasi langsung"
        string allowed_roles "JSON array role yang berhak membaca"
        string tenant_id "ID tenant / perusahaan"
        integer term_count "Jumlah token dokumen"
        timestamp indexed_at "Waktu terakhir diindeks"
    }

    core_search_index_jobs {
        bigint id PK
        string job_uuid UK
        string status "pending, queued, processing, indexed, dll"
        string entity_type
        integer batch_size
        integer processed_items
        text error_message
        string idempotency_key UK
        timestamp created_at
        timestamp updated_at
    }

    core_tasks ||--o{ core_task_histories : "memiliki riwayat"
    core_tasks {
        bigint id PK
        string task_uuid UK
        string title "Judul tugas"
        string module "Modul asal tugas"
        string task_type "APPROVAL, REVIEW, ACTION, AUDIT"
        string status "PENDING, IN_PROGRESS, COMPLETED, REJECTED, ESCALATED"
        string priority "CRITICAL, HIGH, MEDIUM, LOW"
        string assigned_role "Role penerima tugas"
        bigint assigned_user_id "ID pengguna khusus jika ada"
        string action_url "Tautan aksi langsung"
        timestamp due_date "Tenggat waktu pengerjaan"
        timestamp completed_at "Waktu selesai"
    }

    core_task_histories {
        bigint id PK
        bigint task_id FK
        string from_status
        string to_status
        string actor_id
        text notes
        timestamp transitioned_at
    }
```

---

## 4. Karakteristik Algoritma Inti

1. **A4: `Bm25Scorer`**  
   - Menghitung BM25 kueri multi-term terhadap kumpulan dokumen indeks.
   - Menggunakan cache statistik koleksi ($N$, $\text{avgdl}$, $n(q_i)$).
   - Menghasilkan skor relevansi dalam integer basis points (bps) dan tanda tangan kriptografis HMAC-SHA256 untuk audit trail.

2. **A5: `TrigramJaccardMatcher`**  
   - Menghasilkan token n-gram 3 karakter dengan sentry `$$`.
   - Mengukur koefisien Jaccard $J(A, B) \times 10.000$ (basis point).
   - Menyediakan pencarian koreksi saltik saat ambang batas $J \ge 0.35$ terpenuhi.

3. **A6: `PolicyFirstRanker`**  
   - Memastikan pengguna tanpa role yang sah mendapatkan skor 0 secara instan sebelum pemrosesan peringkat lanjutan (zero information leak).
   - Memadukan faktor BM25, faktor saltik Jaccard, penyesuaian resensi waktu (decay fungsi eksponensial), dan bobot prioritas tipe entitas bisnis.
