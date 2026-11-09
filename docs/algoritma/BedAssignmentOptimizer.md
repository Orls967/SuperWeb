# Algoritma: BedAssignmentOptimizer (B1)

## 1. Intuisi & Latar Belakang
Algoritma `BedAssignmentOptimizer` menyelesaikan permasalahan alokasi tempat tidur rumah sakit secara adil, berjenjang, dan deterministik. Dalam skenario bangsal rawat inap nyata, tempat tidur khusus seperti ICU dan Kamar Isolasi tidak boleh disubstitusi oleh kelas lain karena standar peralatan medis hidup (life-support) dan pengendalian infeksi nosokomial. Sebaliknya, kelas reguler (Kelas 3, Kelas 2, Kelas 1) mengizinkan peningkatan kelas (upgrade) ke satu tingkat di atasnya apabila kelas hak pasien sedang penuh, dengan syarat kelebihan biaya ditanggung oleh rumah sakit.

## 2. Aturan & Prioritas Deterministik
1. **Tingkat Kegawatan (Priority Tier)**:
   - Gawat Darurat (`EMERGENCY` = 0)
   - Mendesak (`URGENT` = 1)
   - Elektif (`ELECTIVE` = 2)
2. **Tie-Breaking Rule**:
   - Jika tingkat kegawatan sama, pasien dengan waktu tunggu terlama (`waiting_minutes` menurun) diprioritaskan lebih dulu.
   - Jika waktu tunggu sama, gunakan `patient_id` menaik secara deterministik.
3. **Pencocokan Ranjang Bebas (Available Bed Matching)**:
   - Pasien ICU hanya boleh ditempatkan di ranjang kelas `ICU`. Bila tidak tersedia, masuk antrean ICU.
   - Pasien Isolasi hanya boleh ditempatkan di ranjang kelas `ISOLATION`. Bila tidak tersedia, masuk antrean Isolasi.
   - Pasien Kelas reguler:
     - Prioritas pertama: kelas yang persis sesuai permintaan.
     - Prioritas kedua: peningkatan 1 tingkat ke atas (K3 -> K2, K2 -> K1, K3 -> K1). Ditandai dengan flag `upgraded = true`.
   - Di antara ranjang yang memenuhi syarat kelas, pilih ranjang dengan nomor kode/ID terkecil.

## 3. Vektor Uji Resmi
- **Ranjang Tersedia**:
  - Bed 101: `ICU`
  - Bed 102: `ICU`
  - Bed 201: `ISOLATION`
  - Bed 301: `CLASS_1`
  - Bed 401: `CLASS_2`
  - Bed 501: `CLASS_3`
- **Daftar Pasien Masuk**:
  - P1: butuh `ICU`, prioritas 0, tunggu 10 menit
  - P2: butuh `ICU`, prioritas 1, tunggu 90 menit
  - P3: butuh `ICU`, prioritas 0, tunggu 5 menit
  - P4: butuh `ISOLATION`, prioritas 1, tunggu 30 menit
  - P5: butuh `CLASS_3`, prioritas 2, tunggu 200 menit
  - P6: butuh `CLASS_3`, prioritas 2, tunggu 100 menit
  - P7: butuh `CLASS_2`, prioritas 2, tunggu 50 menit

- **Hasil Perhitungan Langkah demi Langkah**:
  1. Urutan antrean setelah diurutkan:
     - P1 (Prio 0, Tunggu 10)
     - P3 (Prio 0, Tunggu 5)
     - P2 (Prio 1, Tunggu 90)
     - P4 (Prio 1, Tunggu 30)
     - P5 (Prio 2, Tunggu 200)
     - P6 (Prio 2, Tunggu 100)
     - P7 (Prio 2, Tunggu 50)
     Urutan: `[P1, P3, P2, P4, P5, P6, P7]`
  2. Alokasi satu per satu:
     - P1 (ICU) -> Bed 101 (ICU terkecil)
     - P3 (ICU) -> Bed 102 (ICU tersisa)
     - P2 (ICU) -> Tidak ada ranjang ICU tersisa; ICU dilarang substitusi -> `ANTRE` (unassigned)
     - P4 (ISOLATION) -> Bed 201 (ISOLATION)
     - P5 (CLASS_3) -> Bed 501 (CLASS_3 persis)
     - P6 (CLASS_3) -> Bed 501 sudah terisi; upgrade ke CLASS_2 -> Bed 401 (`upgrade`)
     - P7 (CLASS_2) -> Bed 401 sudah terisi; upgrade ke CLASS_1 -> Bed 301 (`upgrade`)

## 4. Sertifikat Verifikasi
Setiap pemanggilan algoritma menghasilkan objek sertifikat `BedAssignmentCertificate` yang memuat ringkasan keputusan dan hash kriptografis SHA-256 dari snapshot input dan alokasi. Kelas terpisah `BedAssignmentCertificateVerifier` memvalidasi bahwa tidak ada ranjang yang dialokasikan ganda dan tidak ada aturan non-substitusi ICU/Isolasi yang dilanggar di lingkungan produksi.

## 5. Batasan & Kompleksitas
- Waktu: $O(N \log N + M \log M)$ di mana $N$ adalah jumlah permintaan pasien dan $M$ adalah jumlah ranjang.
- Ruang: $O(N + M)$ untuk menyimpan struktur antrean dan lookup ranjang.
- Lokasi Kode: `Modules\Hospital\Domain\Algorithms\BedAssignmentOptimizer.php`

