# Pedoman Perlindungan Data Pribadi Tamu Hotel Sesuai UU No. 27 Tahun 2022 (UU PDP)

Dokumen ini mendokumentasikan tata kelola kepatuhan privasi data tamu hotel, pembatasan akses data PII, enkripsi data identitas, dan pencegahan insiden kebocoran informasi.

---

## 1. Prinsip Perlindungan Data Pribadi (UU PDP)

Sesuai Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi:
1. **Kategori Data Pribadi Spesifik**: Nomor KTP, nomor paspor, data biometrik pengenalan wajah, dan catatan riwayat kesehatan (seperti riwayat alergi makanan/diet khusus) diklasifikasikan sebagai Data Pribadi Spesifik yang memerlukan pengamanan enkripsi tingkat tinggi.
2. **Minimisasi Data**: Sistem PMS hanya meminta dan menyimpan data identitas yang benar-benar esensial untuk tujuan pemenuhan kewajiban hukum keimigrasian dan penagihan folio.
3. **Penyimpanan Terenkripsi**: Kolom nomor paspor dan KTP tamu disimpan dalam bentuk terenkripsi menggunakan algoritma standar AES-256-CBC. Pencarian identitas dilakukan menggunakan teknik *blind index* (HMAC SHA-256 ber-salt rahasia) tanpa mendeskripsi database secara massal.

---

## 2. Pencegahan Kerentanan Akses Langsung (Anti-IDOR Policy)

1. **Prinsip Kepemilikan Objek**: Setiap reservasi dan kunci kamar digital terikat langsung dengan `guest_user_id` tamu yang terotentikasi.
2. **Validasi Middleware & Policy**: Pengguna dilarang keras membuka data folio atau kunci kamar tamu lain dengan memanipulasi parameter URL (Insecure Direct Object Reference / IDOR).
3. **Penyembunyian Data Sensitif (Data Masking)**:
   - Resepsionis dan General Manager dapat melihat nama tamu lengkap untuk keperluan operasional.
   - Peran non-front office (seperti petugas tata graha dan staf F&B) hanya melihat nomor kamar fisik tanpa menampilkan identitas lengkap tamu.
