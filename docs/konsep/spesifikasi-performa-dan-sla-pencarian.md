# Spesifikasi Performa, Anggaran Kueri & SLA Pencarian Enterprise

**Fase:** 194 — Platform: Search Performance Benchmarks, Query Budget & SLA  
**Modul Pemilik:** `Core` & `Shared`

---

## 1. Anggaran Kueri & Batas Latensi (Query Performance Budget)

Pencarian global dirancang untuk melayani ribuan pengguna simultan dengan pengalaman pengetikan instan (*as-you-type search*).

### Batas Latensi Operasional (SLA):
- **p50 (Median Latency):** $\le 20$ milidetik.
- **p95 (95th Percentile):** $\le 45$ milidetik.
- **p99 (99th Percentile):** $\le 80$ milidetik.
- **Batas Kueri Basis Data (Database Query Budget):** Maksimal 2 kueri SQL per permintaan pencarian (1 kueri indeks leksikal terbalik + 1 kueri pencatatan log asinkron).
- **Penggunaan Memori Maksimal (Peak Memory Usage):** $\le 12$ MB per siklus permintaan HTTP pencarian.

---

## 2. Strategi Pengoptimalan Basis Data & Indeksasi

Untuk memenuhi anggaran performa ketat tanpa mesin pencari eksternal:
1. **Indeks Majemuk Selektif:** Tabel `core_search_indices` menggunakan indeks gabungan `(tenant_id, entity_type)` dan indeks teks penuh `FULLTEXT(title, content)`.
2. **Kueri Terfilter Ringkas:** Kolom yang diseleksi dalam respons kueri dibatasi hanya pada atribut kunci (`id`, `entity_type`, `entity_id`, `title`, `route_url`, `allowed_roles`), menghindari pemuatan teks dokumen penuh ke dalam memori.
3. **Penyimpanan Cache Statistik Korpus:** Nilai total dokumen ($N$) dan panjang rata-rata dokumen ($\text{avgdl}$) disimpan dalam cache aplikasi berjangka 10 menit, memangkas kebutuhan kueri agregasi `COUNT(*)` dan `AVG(term_count)` pada setiap pencarian kueri.

---

## 3. Ambang Batas Kegagalan & Health Degraded Mode

Bila latensi kueri rata-rata melampaui 100 ms selama 5 menit berturut-turut:
- Sistem secara otomatis mengaktifkan mode degradasi anggun (*graceful degradation mode*).
- Pada mode ini, kalkulasi toleransi saltik Trigram Jaccard dinonaktifkan sementara untuk memprioritaskan pencocokan leksikal langsung BM25.
- Log peringatan didelegasikan ke monitoring SRE melalui event `SearchPerformanceDegradedEvent`.

