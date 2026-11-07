# Katalog Galat & Pengecualian Domain Hotel (Fase 91)

Dokumen ini mendokumentasikan kode status HTTP, pesan galat, dan resolusi dari exception khusus yang dapat dilempar oleh modul Hotel.

## 1. Daftar Pengecualian & Status HTTP

| Kode Exception / Kelas | Status HTTP | Pesan Standar Galat | Akar Masalah & Solusi Pemulihan |
| :--- | :--- | :--- | :--- |
| `OverbookingCapacityExceededException` | 422 Unprocessable | *Alokasi overbooking melebihi batas kebijakan 3%.* | Permintaan reservasi melebihi kapasitas fisik ditambah kuota batas risiko. Tawarkan tanggal lain atau kamar kategori lain. |
| `MissingIdempotencyKeyException` | 422 Unprocessable | *Header Idempotency-Key diperlukan untuk tindakan ini.* | Klien web/API tidak menyertakan header kunci idempotensi unik pada transaksi pembayaran, deposit, atau relokasi. |
| `DuplicateIdempotencyConflictException`| 409 Conflict | *Payload berbeda terdeteksi untuk Idempotency-Key yang sama.* | Klien mengirimkan request ulang dengan Idempotency-Key yang sudah dipakai sebelumnya namun memiliki payload isi yang berlainan. |
| `InvalidRoomStatusForCheckInException` | 422 Unprocessable | *Check-in hanya diizinkan untuk kamar berstatus INSPECTED atau AVAILABLE.* | Kamar target masih berstatus DIRTY, CLEANING, atau OUT_OF_ORDER. Alihkan tamu ke kamar lain yang sudah diinspeksi. |
| `UnsettledFolioBalanceCheckoutException`| 422 Unprocessable | *Check-out dilarang sebelum saldo folio bernilai 0.* | Tamu masih memiliki sisa tagihan yang belum lunas atau sisa deposit yang belum dikembalikan. Lakukan pelunasan terlebih dahulu. |
| `InvalidStateTransitionException` | 422 Unprocessable | *Transisi status tidak sah dari {from} ke {to}.* | Pelanggaran diagram state machine (misal: reservasi checked-in dibatalkan langsung tanpa check-out). |
| `DigitalKeyExpiredException` | 403 Forbidden | *Sesi kunci digital kamar telah kedaluwarsa.* | Tamu mencoba membuka pintu kamar setelah waktu check-out berakhir atau reservasi telah dibatalkan. |
| `UnauthorizedFolioAccessException` | 403 Forbidden | *Akses ditolak: Anda tidak memiliki wewenang membuka data tamu lain.* | Tamu mencoba mengakses rute folio milik tamu lain (upaya pelanggaran IDOR terdeteksi dan diblokir). |
| `ApoaComplianceMissingPassportException`| 422 Unprocessable | *Nomor paspor dan masa berlaku wajib diisi untuk tamu berkebangsaan asing.* | Petugas front desk belum memasukkan nomor paspor tamu WNA saat check-in. Lengkapi formulir keimigrasian. |

---

## 2. Kode Galat Penjurnalan Ledger Perhotelan

| Kode Galat Internal | Penjelasan Masalah | Penanganan Teknis |
| :--- | :--- | :--- |
| `ERR_LEDGER_UNBALANCED_POSTING` | Total debit tidak sama dengan total kredit pada transaksi folio. | Pastikan rumus `Debit (Guest Ledger) = Kredit (Room Rev) + Kredit (PBJT) + Kredit (Service Charge)` terpenuhi persis tanpa deviasi desimal. |
| `ERR_LEDGER_ACCOUNT_NOT_SEEDED` | Akun pembukuan yang dipanggil belum terdaftar di `HotelAccounts`. | Daftarkan kode akun ke registry `HotelAccounts` dan sinkronkan dengan seeder ledger. |

