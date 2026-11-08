# Algoritma Bersertifikat: PointsLiabilityFifo (H10)

## 1. Identifikasi
- **Kode:** H10
- **Nama:** Points Liability FIFO Allocation & Breakage Recognizer
- **Kelas:** Algoritma Akuntansi Finansial & Liabilitas Moneter
- **Modul:** `Hotel` (`Modules\Hotel\Domain\Algorithms\PointsLiabilityFifo`)

## 2. Deskripsi Matematis & Fungsional
Algoritma ini mengelola daur hidup liabilitas poin loyalitas yang diterbitkan oleh grup perhotelan dan 17 lini ekosistem. Setiap penerbitan poin (earning) dicatat dalam bentuk batch dengan nilai perolehan tertentu dan tanggal kedaluwarsa tetap (biasanya 2 tahun).

Saat penebusan poin terjadi (redemption), algoritma menerapkan metode FIFO (First-In First-Out) murni:
1. Memilih batch tertua yang masih aktif dan belum kedaluwarsa.
2. Memotong kuantitas poin sesuai kebutuhan penebusan.
3. Menghitung pengurangan nilai liabilitas moneter berdasarkan nilai historis batch terkait.
4. Menghasilkan sertifikat audit kriptografis SHA-256 yang memuat riwayat partisi batch terpilih.

Saat proses audit akhir periode atau night audit, algoritma memindai batch yang telah melewati masa berlaku:
1. Menandai sisa poin yang belum terpakai sebagai expired.
2. Menghitung nilai rupiah sisa poin tersebut sebagai pendapatan Breakage (IFRS 15 / PSAK 72).
3. Menerbitkan posting jurnal penyeimbang: Debit `htl:loyalty_points_liability:IDR`, Credit `htl:loyalty_breakage_revenue:IDR`.

## 3. Kompleksitas & Karakteristik
- **Waktu:** $\mathcal{O}(k)$ di mana $k$ adalah jumlah batch aktif milik anggota (rata-rata $k \le 12$).
- **Ruang:** $\mathcal{O}(k)$ untuk menyimpan hasil partisi pemotongan.
- **Deterministik:** 100% deterministik, bebas efek samping, tanpa nilai acak.
- **Sertifikasi:** Mengembalikan objek `PointsLiabilityFifoCertificate` dengan signature hash HMAC/SHA-256 yang divalidasi oleh `PointsLiabilityFifoCertificateVerifier`.

