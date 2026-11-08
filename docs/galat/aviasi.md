# Katalog Galat & Penanganan Pengecualian (Exceptions) Modul Aviation

Dokumen ini mendokumentasikan kode status, klasifikasi tingkat keparahan (severity level), akar penyebab teknis, dan tindakan perbaikan operasional untuk seluruh pengecualian domain pada Modul Aviasi.

---

## 1. Matriks Klasifikasi Tingkat Keparahan

| Level | Definisi Operasional | SLA Penanganan | Eskalasi |
|---|---|---|---|
| **P1 - CRITICAL** | Pelanggaran kelaikan terbang armada (A2) atau pelanggaran amplop batas titik berat W&B (A3) pada penerbangan yang siap berangkat. | < 15 menit | Chief Pilot & Direktur Operasi Penerbangan (DO) |
| **P2 - HIGH** | Kegagalan sinkronisasi pembukuan buku besar (ledger invariant breach) atau kegagalan orkestrasi saga pemesanan tiket. | < 1 jam | Lead Software Engineer & Akuntan Manajemen Aviasi |
| **P3 - MEDIUM** | Penolakan penerimaan kargo barang berbahaya (DG), kuota alokasi slot penuh, atau kedaluwarsa penahanan kursi (hold TTL). | < 4 jam | Duty Dispatcher & Supervisor Kargo Lini Satu |
| **P4 - LOW** | Galat validasi masukan formulir UI atau kegagalan unduh manifes non-kritis. | < 24 jam | Tim Layanan Pelanggan (Customer Care) |

---

## 2. Rincian Pengecualian Domain (Domain Exceptions)

### 2.1 `AirworthinessFailedException`
- **Kode HTTP:** `422 Unprocessable Entity`
- **Tingkat Keparahan:** `P1 - CRITICAL`
- **Penyebab Teknis:**
  - Status registrasi armada sedang `grounded`.
  - Sertifikat kelaikan udara (Certificate of Airworthiness / CoA) telah melewati tanggal kedaluwarsa sebelum estimasi jam pendaratan.
  - Akumulasi jam terbang melampaui interval pemeriksaan A-Check tanpa rilis teknisi MRO berwenang.
  - Item Minimum Equipment List (MEL) kategori tertunda melampaui batas toleransi penerbangan.
- **Tindakan Pemulihan:**
  1. Hentikan proses pelepasan penerbangan (*stop dispatch*).
  2. Ganti armada pesawat ke registrasi cadangan yang memiliki status laik udara (*tail swap*).
  3. Hubungi lini perawatan MRO untuk penerbitan izin penerbangan feri (*ferry flight permit*) bila diperlukan.

### 2.2 `DangerousGoodsRejectedException`
- **Kode HTTP:** `422 Unprocessable Entity`
- **Tingkat Keparahan:** `P3 - MEDIUM`
- **Penyebab Teknis:**
  - Nomor UN atau klasifikasi kelas DG IATA dilarang dimuat pada kabin pesawat penumpang (*Cargo Aircraft Only / CAO*).
  - Jumlah berat bersih (*net quantity*) melebihi batas toleransi per kemasan IATA DGR.
  - Kemasan kargo tidak memiliki sertifikat standar spesifikasi PBB (*UN Specification Packaging*).
  - Dokumen deklarasi pengirim (*Shipper's Declaration for Dangerous Goods*) belum ditandatangani oleh personil bersertifikat DGR.
- **Tindakan Pemulihan:**
  1. Tolak penerimaan fisik kargo di gudang lini satu.
  2. Berikan formulir berita acara penolakan (*Notice of DG Rejection*) kepada agen kargo pengirim.
  3. Kembalikan paket ke area karantina kargo khusus barang berbahaya.

### 2.3 `SeatHoldExpiredException`
- **Kode HTTP:** `410 Gone` atau `422 Unprocessable Entity`
- **Tingkat Keparahan:** `P4 - LOW`
- **Penyebab Teknis:**
  - Calon penumpang terlambat menyelesaikan pembayaran melewati batas TTL 15 menit sejak pemilihan kursi.
  - Kunci reservasi sementara telah dilepaskan otomatis oleh perintah `avi:expire-holds`.
- **Tindakan Pemulihan:**
  1. Tampilkan notifikasi ramah pengguna bahwa waktu pembayaran telah habis.
  2. Arahkan pengguna kembali ke layar pemilihan jadwal dan kursi untuk membuat penahanan baru.

### 2.4 `SanctionedPassengerException`
- **Kode HTTP:** `403 Forbidden`
- **Tingkat Keparahan:** `P2 - HIGH`
- **Penyebab Teknis:**
  - Nama dan identitas calon penumpang cocok dengan basis data cekal penerbangan (*No-Fly List*) nasional atau daftar sanksi internasional.
- **Tindakan Pemulihan:**
  1. Blokir penerbitan tiket dan batalkan penahanan kursi secara otomatis.
  2. Laporkan insiden pencocokan ke Bagian Keamanan Penerbangan Sipil (Aviation Security / AVSEC) bandara.

### 2.5 `InvalidStateTransitionException`
- **Kode HTTP:** `409 Conflict`
- **Tingkat Keparahan:** `P2 - HIGH`
- **Penyebab Teknis:**
  - Upaya mengubah status entitas (penerbangan, tiket, kargo, atau kunjungan perawatan) yang melanggar graf transisi mesin status terbatas (*Finite State Machine*). Contoh: Mencoba memberangkatkan (*depart*) penerbangan yang belum berstatus *airworthy_cleared*.
- **Tindakan Pemulihan:**
  1. Periksa alur prasyarat pada diagram alir state machine.
  2. Pastikan gerbang verifikasi perantara telah dieksekusi dengan sukses sebelum memanggil aksi berikutnya.
