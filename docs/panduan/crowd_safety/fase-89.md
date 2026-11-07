# Panduan Pengguna: Crowd Safety Officer (Fase 89)

Dokumen ini merupakan panduan praktis bagi petugas keselamatan kerumunan (*crowd safety officer*) dalam memantau dinamika kepadatan penonton, mengatur batas kapasitas efektif zona, mengendalikan laju aliran masuk via *token bucket*, serta mengaktifkan prosedur tanggap darurat dan evakuasi.

---

## 1. Antarmuka Pemantauan Kepadatan Real-Time (/venue/zones)
Halaman `/venue/zones` menyajikan dasbor terpadu yang memvisualisasikan peta tata letak (*spatial floorplan*) venue beserta status operasional zona:
- **Peta Heatmap SVG Vektor**:
  - Warna zona berubah secara dinamis berdasarkan persentase keterisian terhadap kapasitas efektif ($\lfloor \text{legal} \times 0,95 \rfloor$).
  - Menampilkan angka okupansi riil saat ini ($\sum IN - \sum OUT$), kapasitas legal izin polisi, batas keselamatan efektif, dan laju kedatangan orang per menit.
- **Tabel Rekapitulasi Zona**:
  - Menampilkan daftar seluruh zona fisik (Pool Deck, VIP Balcony, Dance Floor, Beachfront, Garden Lounge).
  - Kolom status tombol aksi: *Normal*, *Throttle Inflow*, *Lockdown Active*, *Evacuate*.

---

## 2. Tingkat Kepadatan & Respon Operasional

### Tingkat 1: Hijau (Okupansi $< 70\%$ Kapasitas Efektif)
- **Kondisi**: Aliran massa lancar, sirkulasi udara optimal, tidak ada penumpukan di pintu masuk.
- **Tindakan**:
  - Pos gerbang beroperasi dalam mode reguler.
  - Algoritma governor V3 mengizinkan laju admisi penuh hingga 30 orang/menit sesuai kuota token bucket.

### Tingkat 2: Kuning (Okupansi $70\% - 89\%$ Kapasitas Efektif)
- **Kondisi**: Kepadatan mulai terasa di sekitar panggung utama atau bar sentral.
- **Tindakan**:
  - Petugas keamanan ditempatkan di sekitar pintu masuk zona untuk mengarahkan barisan antrean satu jalur (*single file queuing*).
  - Laju admisi mulai diperlambat secara otomatis oleh sistem.

### Tingkat 3: Oranye (Okupansi $90\% - 99\%$ Kapasitas Efektif)
- **Kondisi**: Zona mendekati ambang batas keselamatan maksimum.
- **Tindakan**:
  - Sistem menampilkan peringatan visual berkedip oranye pada konsol `/venue/zones`.
  - Crowd Safety Officer menekan tombol **Perlambat Aliran (Throttle Inflow)**. Token bucket dibatasi hingga 10 orang/menit.
  - Petugas mengalihkan penonton baru ke zona sekunder (contoh: dari Dance Floor ke Garden Lounge).

### Tingkat 4: Merah (Okupansi $\ge 100\%$ Kapasitas Efektif)
- **Kondisi**: Zona mencapai batas kapasitas efektif (contoh: 475 orang dari kapasitas izin 500 orang). Margin keselamatan $5\%$ mulai tersentuh.
- **Tindakan**:
  - Sistem secara otomatis mengaktifkan status **Kunci Gerbang Masuk (*Crowd Lockdown Active*)**.
  - Pintu putar gerbang masuk zona menolak seluruh pindaian tiket masuk dengan notifikasi `ZONE_OVERCAPACITY`.
  - Hanya pindaian keluar (`OUT`) yang diizinkan hingga okupansi turun kembali ke bawah $90\%$.

---

## 3. Prosedur Tanggap Darurat & Evakuasi Massal

1. Dalam hal terjadi insiden mendesak (kebakaran, kericuhan massa, atau gempa bumi):
   - Crowd Safety Officer menekan tombol darurat raksasa berlatar merah: **AKTIFKAN EVAKUASI DARURAT**.
2. Konfirmasi aksi darurat pada kotak dialog modal.
3. Respon Sistem Seketika:
   - Seluruh pintu putar gerbang masuk dan keluar terbuka bebas (*free spin fail-safe*).
   - Layar monitor digital di seluruh zona menampilkan panah jalur evakuasi hijau dan teks instruksi titik kumpul aman (*assembly point*).
   - Log insiden darurat mencatat waktu aktivasi, identitas petugas pelaksana, dan status ambulans siaga terdaftar.
4. Petugas di lapangan memandu pengunjung keluar dengan tenang menuju area pantai terbuka atau lapangan parkir utama.
