# Spesifikasi Matematis & Algoritma: DuplicateWindowDetector (A1)

## 1. Landasan Teoretis

Deteksi duplikasi kode skala besar pada modul Platform dan Quality menggunakan algoritma rolling hash Rabin-Karp berbasis evaluasi polinomial Horner. Algoritma ini dirancang untuk mendeteksi kesamaan blok token kode tanpa terpengaruh oleh penamaan variabel (alpha-renaming), spasi, indentasi, maupun komentar inline.

### Polinomial Rolling Hash Horner
Untuk deretan token kode $T = [t_0, t_1, \dots, t_{k-1}]$ dengan ukuran sliding window $k$, nilai hash polinomial dihitung menggunakan modulus bilangan prima besar $M = 1.000.000.007$ ($10^9 + 7$) dan basis perkalian prima $B = 257$:

$$H(T) = \left( \sum_{i=0}^{k-1} t_i \cdot B^{k-1-i} \right) \pmod M$$

Evaluasi skema Horner diterapkan secara efisien dalam waktu $O(k)$ untuk inisialisasi:
$$H_0 = 0$$
$$H_{i+1} = (H_i \cdot B + t_i) \pmod M$$

### Rolling Transition Window
Ketika sliding window bergeser satu token dari posisi $j$ ke $j+1$ (mengeluarkan token $t_j$ dan memasukkan token $t_{j+k}$):
$$H_{new} = \left( (H_{old} - t_j \cdot B^{k-1}) \cdot B + t_{j+k} \right) \pmod M$$

Nilai pengali derajat tertinggi $B^{k-1} \pmod M$ dihitung di awal menggunakan algoritma modular exponentiation sehingga setiap pergeseran jendela diselesaikan dalam kompleksitas waktu konstan $O(1)$.

## 2. Tokenisasi Deterministik

Sebelum proses penghitungan hash, kode sumber diproses melalui tahapan normalisasi:
1. Parsing token PHP menggunakan fungsi bawaan `token_get_all()`.
2. Penghilangan token whitespace (`T_WHITESPACE`), komentar baris tunggal (`T_COMMENT`), dan blok komentar dokumentasi (`T_DOC_COMMENT`).
3. Pengabstraksian variabel (`T_VARIABLE`) menjadi token seragam `T_VAR_ABSTRACT` agar perubahan nama variabel lokal tidak mengelabui pendeteksi duplikasi.
4. Pengabstraksian literal string (`T_CONSTANT_ENCAPSED_STRING`) dan numerik (`T_LNUMBER`, `T_DNUMBER`) menjadi `T_LITERAL_ABSTRACT`.

## 3. Struktur Sertifikat Kriptografis (DuplicateWindowCertificate)

Untuk menjamin akuntabilitas hasil evaluasi gate kualitas, generator duplikasi menerbitkan sertifikat yang berisi:
- `analyzed_files`: Daftar berkas yang dianalisis beserta SHA-256 fingerprint masing-masing.
- `window_size`: Ukuran sliding window yang digunakan (standar $k = 30$ token).
- `duplicate_blocks`: Pasangan blok berkas, baris awal-akhir, dan nilai hash Horner yang cocok.
- `duplicate_density`: Rasio token duplikat terhadap total token dalam basis kode.
- `signature`: Hash HMAC-SHA256 dari seluruh payload metrik menggunakan seed verifikasi audit.

## 4. Verifikasi Independen (DuplicateWindowCertificateVerifier)

Kelas `DuplicateWindowCertificateVerifier` bertindak sebagai verifikator pihak ketiga yang mengevaluasi:
1. Validitas struktur sertifikat (keberadaan semua metadata wajib).
2. Kesesuaian fingerprint berkas dengan kondisi riil pada disk.
3. Kepatuhan terhadap ambang batas: rasio duplikasi harus berada di bawah ambang maksimum toleransi yang ditentukan dalam konfigurasi platform (`max_duplicate_ratio <= 0.05` atau 5%).
