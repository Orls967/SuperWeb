# Panduan Teknis Data Sintetis & Penyamaran PII (Fase 237)

## 1. Kepatuhan Privasi Data & UU PDP
Dalam ekosistem enterprise yang mengelola identitas nasabah perbankan, riwayat rekam medis rumah sakit, data manifest penumpang penerbangan, dan log transaksi ritel, privasi data menjadi fondasi hukum yang mutlak. Sesuai Undang-Undang Perlindungan Data Pribadi (UU PDP) dan standar ISO/IEC 27701:
- Dilarang keras mereplikasi data identitas nasabah riil (plaintext PII) ke lingkungan non-produksi, sandbox, maupun log analitik.
- Seluruh pengujian wajib memanfaatkan **data sintetis deterministik** atau dataset bertopeng (masked dataset).

Modul `Platform` menyediakan `MaskPiiAction`, `AnonymizeDatasetAction`, dan `VerifyMaskingAction` untuk menjamin nol kebocoran data pribadi.

---

## 2. Pola Penyamaran Deterministik (Deterministic Masking)

Penyamaran deterministik memastikan bahwa string input yang identik akan selalu menghasilkan representasi tersamar yang sama di seluruh sistem, memungkinkan pengujian relasi foreign key dan integritas referensial tanpa mengekspos data mentah.

### Aturan Transformasi Penyamaran:

1. **Alamat Surel (Email Address)**:
   - Format: Huruf pertama + `***` + huruf terakhir nama lokal + `@domain`.
   - Contoh: `developer.private@typeshit.com` $\rightarrow$ `d***e@typeshit.com`.
   - Mencegah pembacaan nama lengkap developer sambil mempertahankan domain tujuan untuk pengujian perutean email.

2. **Nomor Telepon Seluler (MSISDN)**:
   - Format: 5 digit awalan negara/operator + `*` (panjang variabel) + 4 digit akhiran.
   - Contoh: `+6281234567890` $\rightarrow$ `+6281*****7890`.
   - Mempertahankan informasi kode negara dan kode provider (misal Telkomsel/Indosat) untuk pengujian gateway SMS/OTP.

3. **Nomor Identitas Kependudukan (NIK / National ID)**:
   - Format: 4 digit kode wilayah + 8 digit bintang + 4 digit kode unik.
   - Contoh: `3201234567890001` $\rightarrow$ `3201********0001`.
   - Mempertahankan kode provinsi dan kabupaten/kota tanpa membocorkan tanggal lahir atau nomor register pribadi.

4. **Alamat Fisik Domisili (Physical Address)**:
   - String jalan disanitasi menjadi `Jl. [MASKED]` guna mencegah profiling geolokasi tempat tinggal.

5. **Nomor Rekening Bank & Kartu (Account Number)**:
   - Menyamarkan segmen tengah rekening: `123456789012` $\rightarrow$ `1234****9012`.

---

## 3. Indeks Buta Bergaram (Salted Blind Indexing)

Ketika sistem membutuhkan kapabilitas pencarian eksak (exact match lookup) pada kolom PII terenkripsi, penyimpanan plaintext indeks pencarian dilarang keras. Modul `Platform` menggunakan pola **HMAC-SHA256 Blind Index**:

$$\text{BlindIndex} = \text{HMAC-SHA256}(\text{Normalisasi}(\text{Plaintext}), \text{Salt}_{\text{Vault}})$$

Karakteristik Keamanan:
- Bersifat satu arah (one-way hashing) dengan salt kriptografis rahasia.
- Menghasilkan hash 64 karakter heksadesimal yang deterministik untuk input yang sama.
- Mencegah kamus pelangi (rainbow table attacks) dan penyerangan frekuensi (frequency analysis).

---

## 4. Generator Dataset Sintetis Relasional

Kelas `DeterministicDataFactory` menyediakan generator data tiruan berbasis pseudo-random hash generator dengan seed idempoten:
- **Domain Banking**: Menghasilkan UUID mutasi rekening, nomor rekening sintetis berawalan `ACC-`, dan nominal transaksi terdistribusi wajar.
- **Domain Inventory**: Menghasilkan SKU produk, kode gudang `WH-`, dan mutasi kuantitas stok.
- **Domain Order**: Menghasilkan kode pesanan `ORD-`, total harga, dan jumlah baris pesanan.

Setiap dataset yang dihasilkan dicatat pada tabel `plt_synth_datasets` lengkap dengan checksum SHA-256 untuk memastikan reproduktibilitas pengujian antar-developer.

---

## 5. Verifikasi Kebocoran PII Otomatis

Command Artisan `platform:mask-verify` menjalankan pemindaian otomatis terhadap dataset sandbox:
- Memindai pola email terbuka (regex RFC 5322 tanpa simbol masking `*`).
- Memindai deret 16 digit angka kontinu (indikasi NIK terbuka).
- Memindai format nomor telepon internasional tanpa masking.
Jika ditemukan setidaknya 1 kebocoran, command mengembalikan kode keluar `1` dan memicu alert keamanan.

