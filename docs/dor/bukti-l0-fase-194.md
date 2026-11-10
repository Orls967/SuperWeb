# Bukti Awal Kondisi Master & Sumber Algoritma — Fase 194

**Fase:** 194 — Platform: Scope-Aware Global Search, Enterprise Command Palette & Kotak Masuk "Tugas Saya"  
**Tanggal:** 2026-11-10  
**Merge-Base SHA Awal:** `ad92ae6647b838dcbbce91e424fc15336c13b651` (commit merge PR #105 di branch `master`)

---

## 1. Kondisi Awal Trunk & Kode Warisan

1. **Status CI Master:**  
   Commit `ad92ae66` adalah merge commit PR #105 (Fase 240) yang menggabungkan seluruh 31 komponen kanonikal Blade `x-ds.*`, token warna multi-tema, 3 algoritma aksesibilitas bergaransi sertifikat, dan migrasi 10 halaman antarmuka modul. Seluruh alur kerja CI GitHub Actions (`Composer Gate`, `CI Quality Gate & Portability`, dan `Regresi perilaku & keamanan`) telah terverifikasi 100% hijau.

2. **Audit Kode Warisan di Modul Integration:**  
   Kelas `Modules\Integration\Application\Services\GlobalSearchService` (dibuat commit `b194001`, +82 baris) diidentifikasi sebagai kerangka pengujian statis lama yang hanya melakukan operasi `DB::table('src_global_search_indices')` sederhana tanpa indeks inverted, tanpa ranking relevansi term, tanpa toleransi kesalahan ketik (saltik), dan tanpa integrasi navigasi keyboard global atau kotak masuk tugas. Sesuai instruksi pemilik IZIN-3, kelas ini dipertahankan tanpa dihapus dan telah ditandai secara formal dengan tag `@deprecated`.

3. **Kode yang Diperdalam di `modules/Shared` dan `modules/Core`:**  
   - `Modules\Shared\Application\SearchRegistry`: Registry penyedia hasil pencarian modular yang didaftarkan oleh masing-masing lini bisnis.
   - `Modules\Shared\Contracts\SearchProviderInterface`: Kontrak standar penyedia entitas pencarian (telah diimplementasikan sebagian modul).
   - `Modules\Shared\Application\Queries\GlobalSearchQuery` & `Http\Controllers\GlobalSearchController`: Endpoint pencarian rute `api.global-search`.
   - Komponen Blade `x-enterprise.command-palette` dan modal pencarian Ctrl+K di `resources/views/layouts/app.blade.php`.

---

## 2. Rujukan Sumber Standar Matematis & Algoritma

1. **Algoritma A4: Best Matching 25 (BM25) Scorer**  
   - **Rujukan:** Robertson, S., & Zaragoza, H. (2009). *The Probabilistic Relevance Framework: BM25 and Beyond*. Foundations and Trends in Information Retrieval, 3(4), 333–389.  
   - **Karakteristik Matematis:**
     $$\text{IDF}(q_i) = \ln \left( \frac{N - n(q_i) + 0.5}{n(q_i) + 0.5} + 1 \right)$$
     $$\text{Score}(D, Q) = \sum_{i=1}^{n} \text{IDF}(q_i) \cdot \frac{f(q_i, D) \cdot (k_1 + 1)}{f(q_i, D) + k_1 \cdot \left(1 - b + b \cdot \frac{|D|}{\text{avgdl}}\right)}$$
     Parameter kanonikal: $k_1 = 1.2$, $b = 0.75$. Transformasi presisi dilakukan dengan basis points (bps) dan integer fixed-point arithmetic untuk menjamin determinisme 100%.

2. **Algoritma A5: Trigram Jaccard Matcher**  
   - **Rujukan:** Jaccard, P. (1912). *The Distribution of the Flora in the Alpine Zone*. New Phytologist, 11(2), 37–50.  
   - **Karakteristik Matematis:**
     $$J(A, B) = \frac{|A \cap B|}{|A \cup B|}$$
     di mana $A$ dan $B$ adalah himpunan 3-gram (trigram) berkarakter padding kanonikal `$$...$$` dari string kueri dan kandidat leksikal, memberikan toleransi terhadap kesalahan ketik, transposisi karakter, dan saran leksikal "mungkin maksud Anda".

3. **Algoritma A6: Policy-First Ranker (Multi-Criteria RBAC & Recency Scorer)**  
   - **Rujukan:** Sandhu, R. S., Coyne, E. J., Feinstein, H. L., & Youman, C. E. (1996). *Role-Based Access Control Models*. IEEE Computer, 29(2), 38–47.  
   - **Karakteristik Matematis:** Peringkat komposit multi-dimensi:
     $$\text{FinalScore} = \text{PolicyFactor} \times (\alpha \cdot \text{BM25Score} + \beta \cdot \text{FuzzyScore} + \gamma \cdot \text{RecencyScore} + \delta \cdot \text{PriorityWeight})$$
     di mana $\text{PolicyFactor} \in \{0, 1\}$ bertindak sebagai gerbang deterministik (zero-leak IDOR protection) yang langsung menganulir skor menjadi 0 bila peran atau tenant pengguna tidak memiliki izin akses terhadap entitas target.

---

## 3. Inventaris Integrasi Lintas Modul (≥ 6 Lini Bisnis)

Pencarian global Fase 194 mengintegrasikan setidaknya 6 adaptor pencarian modular resmi:
1. `AutoServe`: Entitas Work Order dan Pelanggan Bengkel.
2. `AutoDex`: Entitas Kendaraan dan Inventaris Unit Showroom.
3. `Logistics`: Entitas Resi Pengiriman dan Rute Armada.
4. `Contract`: Entitas Dokumen Kontrak Legal dan Perjanjian Kerjasama.
5. `Asset`: Entitas Aset Fisik dan Jadwal Pemeliharaan Fasilitas.
6. `Marine`: Entitas Kapal, Manifest Kargo, dan Surat Persetujuan Berlayar.
7. `Party`: Entitas Pihak Ketiga, Vendor, dan Rekanan Bisnis.

---

## 4. Konsep Kotak Masuk "Tugas Saya" (§19.1)

Kotak Masuk "Tugas Saya" (`/tugas-saya` atau `/tasks`) berfungsi sebagai agregator aksi kerja terpadu (action center) bagi pengguna aplikasi di seluruh 30 lini bisnis:
- **Tugas Approval:** Permintaan persetujuan pembelian, pengeluaran kas, otorisasi cuti, walk-overbooking hotel, dan release komponen design system.
- **Tugas Tinjauan/Verifikasi:** Tinjauan kepatuhan kontrak, pemeriksaan klaim asuransi, dan review hasil evaluasi heuristik UX.
- **Tugas Tenggat/Eskalasi:** Tugas tertunda yang mendekati jatuh tempo mendapatkan lencana eskalasi otomatis berbasis cron scheduler (`php artisan inbox:escalate`).
- **Aksi Cepat Inline:** Pengguna dapat menyetujui (`Approve`), menolak (`Reject`), atau mendelegasikan tugas langsung dari daftar tanpa harus membuka modul satu per satu.
