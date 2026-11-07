# Katalog Galat & Kode Pengecualian Pasar Sekunder (Fase 116)

## 1. Pendahuluan
Katalog galat ini mendokumentasikan seluruh kode pengecualian (*exception codes*), pesan galat (*error messages*), akar penyebab (*root cause*), dan prosedur penyelesaian (*remediation steps*) yang dihasilkan oleh modul pasar sekunder tiket, dynamic bundling, dan daftar tunggu antrean Superweb Venue.

Standar arsitektur mewajibkan seluruh galat domain melempar pengecualian bertipe kuat (*strongly typed domain exceptions*) tanpa mengembalikan respons HTTP ambigu atau pembungkus `back()->errors()` yang dapat memicu HTTP 500 (Aturan A13).

---

## 2. Tabel Kode Galat Domain

| Kode Galat | Pengecualian Domain | Pesan Galat Pengguna | Akar Masalah & Konteks | Prosedur Remediasi |
| :--- | :--- | :--- | :--- | :--- |
| **SEC-ERR-001** | `SecondaryMarketException::priceExceedsLegalCap` | Harga penawaran melanggar batas legal anti-scalping (Maks: 120%). | Pengguna mencoba mendaftarkan tiket sekunder dengan harga di atas plafon $1.2 \times \text{Face Value}$. | Minta penjual menurunkan harga penawaran agar berada di dalam batas yang diizinkan sistem. |
| **SEC-ERR-002** | `SecondaryMarketException::singleTransferViolation` | Tiket telah pernah dipindahtangankan dan tidak dapat dijual kembali. | Tiket yang ditargetkan sudah pernah sukses dipindahtangankan sekali di masa lalu ($T \ge 1$). | Informasikan kepada pemilik bahwa aturan hukum membatasi transfer tiket hanya satu kali seumur hidup. |
| **SEC-ERR-003** | `SecondaryMarketException::sellerCannotBuyOwnListing` | Pengguna tidak diizinkan membeli tiket yang didaftarkannya sendiri. | ID pembeli (`buyer_user_id`) sama persis dengan ID penjual (`seller_user_id`). | Sistem menolak transaksi manipulatif (*wash trading*). Pembeli harus menggunakan akun lain yang sah. |
| **SEC-ERR-004** | `SecondaryMarketException::waitlistOfferExpired` | Batas waktu penawaran waitlist untuk token telah berakhir (15 menit). | Pembeli mencoba mengklaim alokasi tiket setelah jendela waktu 900 detik terlewati. | Tiket telah dialihkan ke antrean berikutnya. Pengguna harus mendaftar ulang di daftar tunggu. |
| **SEC-ERR-005** | `SecondaryMarketException::dynamicBundleFloorViolated` | Harga paket bundle dinamis melanggar batas floor price pelindung laba. | Konfigurasi diskon promosi menghasilkan harga jual di bawah batas biaya modal mitra. | Kurangi persentase diskon promosi atau sesuaikan konfigurasi komponen biaya paket. |
| **SEC-ERR-006** | `SecondaryMarketException::botScalpingDetected` | Akses diblokir sementara karena pola perilaku bot scraping tidak wajar. | Mesin `AntiScalpingBotDetectorService` mendeteksi laju permintaan $> 60$ RPM atau lingkungan headless. | Ikuti prosedur banding verifikasi identitas resmi bila terjadi false positive pada pengguna manusia. |
| **SEC-ERR-007** | `DomainException` | Status listing resale bukan LISTED melainkan ESCROW_HOLD / SETTLED. | Terjadi perebutan pesanan oleh dua pembeli pada saat yang bersamaan (*race condition*). | Transaksi pertama yang mengunci baris basis data yang berhasil; pembeli kedua disarankan memilih tiket lain. |
| **SEC-ERR-008** | `DomainException` | Kamar hotel pilihan tidak tersedia untuk tanggal acara yang dipilih. | Kontrak aditif `HotelPackageAvailabilityValidator` (G7-K20) melaporkan kamar habis (*sold out*). | Pilih opsi paket penginapan dengan tipe kamar lain atau tanggal check-in alternatif. |
| **SEC-ERR-009** | `DomainException` | Rute shuttle bus penuh atau tidak beroperasi pada jadwal yang diminta. | Kontrak aditif `EventShuttleFleetScheduler` (G7-K21) melaporkan kapasitas armada telah penuh. | Alihkan reservasi transportasi ke gelombang keberangkatan shuttle bus berikutnya. |
| **SEC-ERR-010** | `RuntimeException` | Pembagian konservasi moneter tertutup menghasilkan selisih saldo tidak seimbang. | Terjadi kegagalan perhitungan integer pembagian waterfall distribusi pajak dan royalti. | Jalankan perintah audit darurat `php artisan venue:audit-secondary` untuk memeriksa ledger. |

---

## 3. Logika Penanganan di Lapisan Pengontrol (Controller Layer)
Seluruh pengontrol HTTP (`VenueSecondaryMarketController` dan `VenueSecondaryAdminController`) membungkus eksekusi tindakan dalam blok `try-catch` terstandarisasi:
1. Menangkap `SecondaryMarketException` atau `DomainException`.
2. Mencatat rincian galat dan jejak tumpukan (*stack trace*) ke log aplikasi Laravel.
3. Mengembalikan respons ramah pengguna dengan pesan galat spesifik via `withErrors(['error' => $e->getMessage()])` atau status JSON HTTP 422 Unprocessable Entity.
4. Mencegah kebocoran struktur basis data internal ke publik.

