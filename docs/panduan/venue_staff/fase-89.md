# Panduan Pengguna: Venue Staff (Fase 89)

Dokumen ini merupakan panduan praktis bagi petugas lapangan (*venue staff*) yang bertugas mengoperasikan pos pemindaian tiket masuk dan keluar di gerbang venue. Antarmuka aplikasi web dirancang responsif satu tangan dengan lebar layar 375 px pada ponsel pintar atau terminal genggam pemindai barcode/QR.

---

## 1. Antarmuka Gerbang Pemindaian Bergerak (375 px)
Antarmuka `/venue/gate` dirancang dengan prinsip ergonomis:
- **Tombol Mode Arah Besar**: Pilihan cepat `MASUK (IN)` atau `KELUAR (OUT)` di bagian atas layar.
- **Area Bidik Kamera / Pemindai**: Jendela pratinjau kode QR dinamis.
- **Kartu Indikator Status Raksasa**: Menampilkan warna kontras tinggi dan teks tebal (memenuhi pedoman aksesibilitas kontras AA):
  - **Latar Hijau Pekat**: Akses Diberikan (*Access Granted*).
  - **Latar Merah Pekat**: Akses Ditolak (*Access Denied*).
  - **Latar Biru Pekat**: Pindaian Keluar Tercatat (*Exit Recorded*).
- **Penjelasan Alasan Penolakan**: Disajikan dalam bahasa Indonesia sederhana tanpa menampilkan data pribadi tamu.

---

## 2. Prosedur Pemindaian Tamu Masuk (Pintu IN)

### 2.1 Alur Kerja Normal (Lampu Hijau)
1. Buka tautan `/venue/gate` pada peramban ponsel.
2. Pastikan tombol arah aktif pada **MASUK (IN)**.
3. Arahkan pemindai ke kode QR dinamis pada ponsel tamu.
4. Sistem memproses verifikasi kriptografis:
   - Memeriksa keabsahan hash tiket dan tanda tangan HMAC.
   - Memeriksa nonce sekali pakai (mencegah tangkapan layar lama).
   - Memverifikasi status usia $\ge 21$ tahun via KYC Party tanpa memaparkan tanggal lahir tamu.
   - Memeriksa aturan *anti-passback* V2 (tiket belum berada di dalam area).
   - Memeriksa kapasitas efektif zona V3 (zona belum penuh).
5. Layar menampilkan:
   ```
   +-------------------------------------+
   |         [ HIJAU - BESAR ]           |
   |                                     |
   |          AKSES DIBERIKAN            |
   |                                     |
   |  Tiket: TCK-EVT-01-0001             |
   |  Tier: General Admission            |
   |  Usia: LULUS VERIFIKASI (>= 21 th)  |
   |  Arah: MASUK KE VENUE               |
   +-------------------------------------+
   ```
6. Petugas mempersilakan tamu melangkah melewati gerbang putar.

---

### 2.2 Penanganan Pindaian Ditolak (Lampu Merah)

#### Skenario A: Tamu Di Bawah Usia Minimum (Underage)
- Penyebab: Hasil verifikasi modul Party menunjukkan usia tamu $< 21$ tahun.
- Tampilan Layar:
  ```
  +-------------------------------------+
  |          [ MERAH - BESAR ]          |
  |                                     |
  |            AKSES DITOLAK            |
  |                                     |
  |  Alasan: Usia di bawah batas        |
  |  minimal tempat hiburan (21+)       |
  |                                     |
  |  (Tanggal lahir dilindungi PDP)     |
  +-------------------------------------+
  ```
- Tindakan Petugas: Sampaikan secara ramah kepada tamu bahwa kebijakan izin usaha dan perda melarang pengunjung di bawah usia 21 tahun memasuki area dengan sajian alkohol. Jangan meminta atau mencatat KTP tamu secara manual.

#### Skenario B: Pelanggaran Anti-Passback (Tiket Sudah Di Dalam)
- Penyebab: Tiket dengan nomor seri tersebut telah dipindai masuk sebelumnya dan belum pernah tercatat keluar.
- Tampilan Layar:
  ```
  +-------------------------------------+
  |          [ MERAH - BESAR ]          |
  |                                     |
  |            AKSES DITOLAK            |
  |                                     |
  |  Alasan: Tiket telah tercatat       |
  |  berada di dalam venue (Passback)   |
  +-------------------------------------+
  ```
- Tindakan Petugas: Cegah masuk. Informasikan bahwa tiket tersebut terindikasi telah digunakan oleh orang lain di dalam area. Tamu dapat diminta membuktikan kepemilikan akun pada portal web tamu `/guest/tickets`.

#### Skenario C: Serangan Replay Nonce
- Penyebab: Kode QR yang dipindai menggunakan token nonce yang telah tercatat sebelumnya (biasanya tangkapan layar lama).
- Tampilan Layar: `AKSES DITOLAK - KODE QR KEDALUWARSA / REPLAY`.
- Tindakan Petugas: Minta tamu membuka kembali aplikasi dan memuat ulang kode QR dinamis yang disegarkan setiap 30 detik.

#### Skenario D: Batas Re-Entry Habis
- Penyebab: Tamu telah melakukan keluar-masuk sebanyak 2 kali (batas toleransi $N=2$).
- Tampilan Layar: `AKSES DITOLAK - BATAS RE-ENTRY HABIS (MAX 2x)`.
- Tindakan Petugas: Arahkan tamu ke meja loket tiket jika ingin membeli tiket baru.

---

## 3. Prosedur Pemindaian Tamu Keluar (Pintu OUT)

1. Alihkan tombol mode di layar `/venue/gate` ke **KELUAR (OUT)**.
2. Setiap tamu yang ingin meninggalkan area venue (baik untuk sementara waktu maupun pulang) wajib memindai tiketnya di pintu keluar.
3. Arahkan pemindai ke kode QR tamu.
4. Layar menampilkan:
   ```
   +-------------------------------------+
   |          [ BIRU - BESAR ]           |
   |                                     |
   |           KELUAR TERCATAT           |
   |                                     |
   |  Tiket: TCK-EVT-01-0001             |
   |  Sisa Hak Re-entry: 1 kali          |
   |  Okupansi Zona Berkurang: -1 orang  |
   +-------------------------------------+
   ```
5. **Dampak Sistem**: Mengurangi agregat $\sum IN - \sum OUT$ pada zona terkait, sehingga membuka kuota bagi pengunjung lain yang mengantre.
