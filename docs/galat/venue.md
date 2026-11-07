# Katalog Galat Domain: Modul Venue & Entertainment

Dokumen ini mendefinisikan seluruh kelas eksepsi bisnis, kode status HTTP, pesan kesalahan berbahasa Indonesia, akar penyebab teknis, dan langkah pemecahan masalah (*troubleshooting*) bagi operator dan pengguna.

---

## 1. Daftar Eksepsi Bisnis & Penanganan

| Nama Kelas Eksepsi | Kode HTTP | Pesan Galat Resmi | Akar Masalah Teknis | Prosedur Penanganan |
|---|---|---|---|---|
| `UnderagePatronException` | 422 | `Usia tamu (:age tahun) di bawah batas minimum legal venue (:min tahun)` | Hasil verifikasi umur dari `PartyAgeVerificationInterface` mengembalikan usia di bawah ketentuan izin tempat hiburan malam (minimal 21 tahun). | Petugas gerbang menolak akses masuk penonton secara sopan. Mencegah pelanggaran hukum perizinan tempat hiburan dengan sajian beralkohol. |
| `PassbackViolationException` | 422 | `Pelanggaran anti-passback: tiket telah berstatus di dalam venue (scanned IN)` | Tiket yang sama dipindai masuk kembali tanpa adanya catatan pindaian keluar terlebih dahulu. Terindikasi upaya penyalahgunaan tiket ganda. | Tolak pindaian tiket. Jelaskan kepada pembawa tiket bahwa tiket telah tercatat berada di dalam venue. |
| `ReplayScanException` | 422 | `Serangan replay terdeteksi: token nonce (:nonce) telah digunakan` | Kode QR yang dipindai membawa parameter `nonce` yang identik dengan pindaian yang telah sukses diproses sebelumnya. | Minta tamu memuat ulang (*refresh*) halaman tiket pada ponsel untuk menghasilkan kode QR dinamis dengan nonce baru. |
| `ReentryLimitExceededException` | 422 | `Batas keluar-masuk (re-entry) maksimal :limit kali telah terlampaui` | Pengunjung telah melakukan pindaian keluar-masuk melebihi kuota toleransi resmi (maksimal 2 kali re-entry). | Tolak akses masuk kembali. Sarankan pengunjung membeli tiket masuk reguler baru jika kuota masih tersedia. |
| `ZoneOvercapacityException` | 422 | `Zona :zone telah mencapai batas kapasitas keselamatan (:capacity orang)` | Akumulasi okupansi riil zona telah mencapai batas kapasitas efektif dengan margin keselamatan $5\%$ dari izin kepolisian. | Arahkan penonton untuk menunggu di area selasar atau zona lain yang kepadatannya masih di bawah ambang batas aman. |
| `TableDepositMismatchException` | 422 | `Nilai hold deposit tidak sesuai dengan ketentuan minimum spend meja` | Pembayaran uang muka deposit meja VIP tidak memenuhi persentase minimum yang ditetapkan sistem. | Pastikan gateway pembayaran berhasil mengunci saldo penuh sebelum mengonfirmasi reservasi meja VIP. |
| `EventNotClosedException` | 422 | `Acara :event belum berstatus CLOSED; pendapatan belum dapat diakui` | Aksi pengakuan pendapatan atau rekonsiliasi akhir dipanggil saat status acara masih `live` atau `on_sale`. | Tutup acara secara resmi terlebih dahulu melalui antarmuka manajer acara atau perintah `venue:close-event`. |
| `InvalidCertificateException` | 500 | `Verifikasi sertifikat algoritma gagal untuk :algorithm` | Nilai keluaran komputasi algoritma tidak cocok dengan pembuktian matematis yang diverifikasi oleh `CertificateVerifier`. | Laporkan ke tim pengembang; transaksi dibatalkan seketika untuk melindungi konsistensi buku besar dan state sistem. |
| `EventPermitExpiredException` | 422 | `Izin keramaian acara :event telah kedaluwarsa atau belum disetujui` | Penjualan tiket atau pembukaan gerbang dilakukan pada acara yang surat izin kepolisiannya belum berstatus `APPROVED`. | Hubungi petugas kepatuhan hukum untuk melengkapi perizinan resmi sebelum membuka penjualan tiket ke publik. |
| `TicketAlreadyRefundedException` | 422 | `Tiket :ticket telah dibatalkan atau dikembalikan sebelumnya` | Percobaan pemindaian atau pembalikan ulang pada tiket yang telah berstatus `refunded` atau `void`. | Tolak pemindaian; tiket tidak lagi memiliki hak akses masuk ke dalam venue. |
| `IdempotencyConflictException` | 409 | `Kunci idempotensi :key telah diproses dengan payload transaksi berbeda` | Klien mengirimkan request dengan `Idempotency-Key` yang sama tetapi parameter transaksi (harga/tier/user) berbeda. | Periksa integrasi klien; gunakan kunci unik untuk setiap transaksi baru. |
