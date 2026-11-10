# ADR-0196: Perankingan Relevansi Pencarian Menggunakan Algoritma BM25 Presisi Integer

**Nomor ADR:** `ADR-0196`  
**Status:** Diterima (*Accepted*)  
**Tanggal:** 2026-11-10  
**Penulis:** Pelaksana W8-P2  
**Keputusan Terkait:** `W8-D26`

---

## Konteks Bisnis & Masalah

Kueri pencarian multi-kata pada database relasional sering menghasilkan urutan hasil yang tidak relevan bila hanya menggunakan pencocokan substring biasa. Mesin pencari membutuhkan algoritma pembobotan istilah yang mampu membedakan kata kunci penting (misal: kode unik atau nama suku cadang langka) dari kata-kata umum, serta memperhitungkan panjang dokumen agar tidak bias terhadap dokumen panjang.

---

## Keputusan Arsitektur

1. **Implementasi Standar BM25 Robertson dkk.:**  
   Mengadopsi formula BM25 kanonikal dengan parameter industri teruji $k_1 = 1.2$ dan $b = 0.75$.

2. **Aritmetika Integer Deterministik (Basis Points):**  
   Semua operasi pembagian dan logaritma ditransformasikan ke fixed-point integer basis points (bps) untuk menghindari inkonsistensi pembulatan float antar mesin uji CI dan server produksi.

3. **Sertifikat Integritas Hasil Kalkulasi:**  
   Setiap perhitungan BM25 menerbitkan sertifikat bervalidasi HMAC-SHA256 yang memuat masukan, frekuensi term, dan skor akhir untuk keperluan audit.

---

## Konsekuensi

- **Positif:** Urutan hasil pencarian akurat dan intuitif; determinisme 100% pada semua lingkungan komputasi; kepatuhan tanpa ketergantungan paket eksternal (IZIN-2).
- **Batasan:** Membutuhkan penyimpanan statistik korpus ($N, \text{avgdl}, n(q)$) yang perlu diperbarui saat indeksasi massal dijalankan.

