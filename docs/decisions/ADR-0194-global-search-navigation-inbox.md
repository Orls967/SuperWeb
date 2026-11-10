# ADR-0194: Mesin Pencarian Global Terpadu, Command Palette & Kotak Masuk "Tugas Saya"

**Nomor ADR:** `ADR-0194`  
**Status:** Diterima (*Accepted*)  
**Tanggal:** 2026-11-10  
**Penulis:** Pelaksana W8-P2  
**Keputusan Terkait:** `W8-D26`, `W8-D27`, `W8-D28`, `W8-D29`

---

## Konteks Bisnis & Masalah

SuperWeb berkembang mengelola puluhan modul bisnis (logistik, bengkel, showroom, marine, kontrak, aset, hotel, dll). Pengguna dan eksekutif menghadapi dua masalah operasional utama:
1. **Pencarian Fragmentaris:** Tidak adanya satu pintu pencarian cerdas yang mampu menemukan transaksi, nomor resi, pelat nomor kendaraan, kontrak, atau unit kapal secara lintas domain dalam hitungan milidetik.
2. **Ketiadaan Toleransi Saltik:** Kesalahan ketik satu huruf (misal: "trnsportasi" alih-alih "transportasi") menghasilkan nol hasil tanpa saran leksikal alternatif.
3. **Risiko Kebocoran Informasi (IDOR):** Pencarian lintas modul berisiko membocorkan data rahasia apabila filter otorisasi peran (RBAC) dan batasan tenant tidak dievaluasi di lapisan terdepan perankingan dokumen.
4. **Hambatan Eksekusi Alur Kerja (Workflow Friction):** Pengguna dengan peran manajer atau approver harus berpindah-pindah antar modul terpisah untuk menyetujui dokumen, memeriksa eskalasi tugas, atau meninjau pekerjaan yang mendekati tenggat waktu (§19.1).

---

## Keputusan Arsitektur

### 1. W8-D26: Mesin Relevansi BM25 & Pencocokan Fuzzy Trigram Native PHP
- Relevansi dokumen dihitung menggunakan algoritma standar industri BM25 (Robertson et al.) dengan parameter $k_1 = 1.2$ dan $b = 0.75$, diimplementasikan murni menggunakan aritmetika integer presisi basis-points (bps) pada kelas `Modules\Shared\Domain\Algorithms\Ui\Bm25Scorer`.
- Toleransi saltik dan saran "mungkin maksud Anda" menggunakan koefisien kesamaan Jaccard atas token 3-gram (trigram) berkarakter sentry pada kelas `TrigramJaccardMatcher`.
- Arsitektur ini menjamin zero external dependencies (tanpa Elasticsearch, Redisearch, atau Algolia) sesuai mandat IZIN-2 pemilik.

### 2. W8-D27: Evaluasi Kebijakan Otorisasi Terdepan (Policy-First Search Ranking)
- Perlindungan anti-IDOR ditegakkan secara absolut melalui algoritma `PolicyFirstRanker`.
- Matriks izin peran (`allowed_roles`) dan batasan `tenant_id` dievaluasi pada filter deterministik terdepan: dokumen yang tidak memiliki izin akses langsung dianulir menjadi skor 0 (dieliminasi dari hasil kueri) sebelum pemrosesan pembobotan teks dilakukan.
- Data sensitif seperti NIK, NPWP, email, dan nomor telepon disanitasi secara otomatis (PII redaction) pada teks ringkasan cuplikan pratinjau (*snippet preview*).

### 3. W8-D28: Tata Kelola Pekerjaan Indeks 8-Status (`IndexJobStateMachine`)
- Pekerjaan pembuatan dan pembaruan indeks pencarian dikendalikan oleh mesin status 8-status: `pending` $\rightarrow$ `queued` $\rightarrow$ `processing` $\rightarrow$ `indexed` $\rightarrow$ `reindexing` $\rightarrow$ `degraded` $\rightarrow$ `failed` $\rightarrow$ `archived`.
- Setiap mutasi status dijamin oleh idempotency key deterministik berbasis UUID pekerjaan dan status target, mencegah duplikasi pekerjaan indeksasi saat restart antrean pekerja.
- Audit konsistensi dua sumber antara data entitas asli dan catatan indeks diverifikasi secara terjadwal melalui perintah konsol `php artisan search:audit`.

### 4. W8-D29: Kotak Masuk Terpadu "Tugas Saya" (§19.1)
- Konsolidasi seluruh item tugas, tinjauan kepatuhan, dan otorisasi persetujuan lintas 30 lini ke dalam tabel terpadu `core_tasks` dan `core_task_histories`.
- Antarmuka web terpusat di `/tugas-saya` menyediakan tab filter status (Menunggu Tindakan, Selesai, Terdelegasi, Eskalasi) dengan tombol aksi cepat inline (Setujui, Tolak, Delegasikan) yang dilindungi oleh otorisasi role eksplisit dan idempotency key.
- Eskalasi otomatis tugas yang melewati tenggat waktu dieksekusi secara periodik oleh daemon scheduler melalui perintah `php artisan inbox:escalate`.

---

## Konsekuensi

- **Positif:** Pencarian enterprise instan (Ctrl+K) tersedia secara seragam di seluruh aplikasi; penelusuran leksikal kebal terhadap kesalahan saltik; jaminan zero information leak berkat evaluasi policy-first; efisiensi kerja approver meningkat drastis dengan adanya kotak masuk "Tugas Saya" satu pintu.
- **Batasan:** Indeks teks penuh tersimpan di basis data relasional lokal `core_search_indices` sehingga pembaruan data wajib disinkronkan via `SearchProviderInterface` masing-masing modul.

