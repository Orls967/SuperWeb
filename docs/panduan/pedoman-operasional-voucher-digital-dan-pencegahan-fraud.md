# Pedoman Operasional: Voucher Digital & Pencegahan Kecurangan (Anti-Fraud Protocol) — Fase 112

## Bab 1: Dinamika Keamanan Voucher Digital Poin
Voucher digital yang diterbitkan dari penukaran poin Travel Pass (voucher makan, voucher spa, voucher upgrade) adalah instrumen bernilai moneter yang menarik potensi tindak kecurangan (fraud) baik dari pihak internal karyawan maupun pihak eksternal. Untuk memitigasi risiko tersebut, sistem menerapkan protokol keamanan kriptografis dan prosedur validasi ketat.

### Karakteristik Voucher Digital Aman:
1. **Kode Unik Gapless Bertanda Tangan Kriptografis (Cryptographically Signed Token):**
   Setiap voucher memiliki kode alfanumerik (format `VCH-YYYY-XXXXXXXX`) yang dihasilkan bersama hash HMAC-SHA256 yang memuat ID pass, nilai voucher, tanggal kedaluwarsa, dan salt rahasia server.
2. **Kode QR Dinamis Satu Kali Pakai (One-Time Dynamic QR):**
   Tampilan kode QR pada aplikasi seluler tamu diperbarui setiap 60 detik menggunakan algoritma Time-based One-Time Password (TOTP). Tangkapan layar (screenshot) kode QR tidak dapat digunakan karena kedaluwarsa dalam 1 menit.
3. **Validasi Status Real-Time:**
   Setiap pemindaian voucher di outlet restoran atau spa langsung memeriksa status di basis data pusat dan mengubah state menjadi `REDEEMED` seketika untuk mencegah pemakaian ganda di lokasi berbeda (double redemption prevention).

---

## Bab 2: Indikator Kecurangan (Red Flags) & Investigasi Internal
Sistem pemantauan fraud otomatis memicu peringatan (alert) kepada Manajer Keamanan & Kepatuhan bila terdeteksi salah satu kondisi berikut:
1. **Frekuensi Penukaran Tidak Wajar (High-Velocity Redemption):**
   Satu akun anggota melakukan penukaran lebih dari 5 voucher bernilai tinggi dalam rentang waktu kurang dari 10 menit.
2. **Kesesuaian Geografis yang Mustahil (Impossible Geographic Velocity):**
   Voucher yang sama atau akun yang sama dipindai di dua properti hotel yang terpisah ratusan kilometer dalam hitungan menit.
3. **Pola Kolusi Karyawan & Tamu (Employee-Guest Collusion Patterns):**
   Satu kasir outlet tertentu memproses persentase penukaran voucher digital yang jauh lebih tinggi (di atas 3 standar deviasi) dibandingkan kasir lainnya pada shift yang sama.

---

## Bab 3: Prosedur Pembekuan & Pembatalan Voucher
- Bila indikasi fraud terkonfirmasi, petugas keamanan berwenang menekan tombol **Emergency Freeze** pada dasbor admin.
- Seluruh voucher aktif milik akun terkait langsung berstatus `SUSPENDED_FOR_AUDIT`.
- Akun tamu dibekukan sementara dan tim audit internal menghubungi tamu untuk verifikasi identitas resmi.

