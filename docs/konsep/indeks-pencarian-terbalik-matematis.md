# Indeks Pencarian Terbalik Matematis (Mathematical Inverted Indexing)

## Ringkasan Konsep
Sistem pencarian global pada Superweb dirancang beroperasi tanpa dependensi mesin pencari eksternal seperti Elasticsearch atau Meilisearch. Arsitektur pencarian enterprise mengandalkan representasi matematis *Inverted Index* (indeks terbalik) berbasis database relasional terindeks penuh.

## Model Matematis
Diberikan koleksi dokumen $\mathcal{D} = \{d_1, d_2, \dots, d_N\}$ dan perbendaharaan term $\mathcal{V} = \{t_1, t_2, \dots, t_M\}$.
Setiap term $t_j \in \mathcal{V}$ memetakan ke sebuah daftar posting:
$$\mathcal{P}(t_j) = \{ (d_i, \text{tf}(t_j, d_i), \text{pos}(t_j, d_i)) \mid t_j \in d_i \}$$

Di mana:
1. $d_i$ adalah ID dokumen atau entitas bisnis (misal: ID Order, ID Invoice, ID Polisi Kendaraan).
2. $\text{tf}(t_j, d_i)$ adalah frekuensi kemunculan term $t_j$ di dalam dokumen $d_i$.
3. $\text{pos}(t_j, d_i)$ adalah offset posisi token untuk pencocokan frasa eksak (*exact phrase matching*).

## Normalisasi dan Tokenisasi
Sebelum term dimasukkan ke dalam tabel posting `core_search_terms`, teks mentah melalui proses pipeline:
1. **Case Folding**: Konversi ke huruf kecil (`mb_strtolower`).
2. **Punctuation Stripping**: Pembersihan karakter non-alfanumerik tanpa merusak tanda hubung nomor dokumen (misal: `INV-2026-001`).
3. **Stopword Elimination**: Eliminasi kata penghubung bahasa Indonesia dan Inggris yang tidak memiliki nilai diskriminatif tinggi (seperti "dan", "yang", "di", "the", "and", "or").
4. **Trigram Generation**: Pembentukan n-gram berukuran 3 karakter untuk keperluan pencarian toleran saltik (*fuzzy typo tolerance*).

## Kompleksitas Waktu dan Ruang
- **Pengindeksan**: $\mathcal{O}(L)$ di mana $L$ adalah panjang karakter teks dokumen.
- **Pencarian Posting List**: $\mathcal{O}(1)$ melalui B-Tree index pada kolom `term_hash`.
- **Penggabungan Posting (Intersection/Union)**: $\mathcal{O}(k \log k)$ untuk $k$ entitas teratas.
