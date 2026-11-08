# Spesifikasi Arsitektur Keamanan API Gateway Program Loyalitas — Fase 112

## Bab 1: Model Ancaman & Vektor Serangan Keamanan Poin
Saldo poin loyalitas memiliki nilai moneter nyata yang setara dengan kas (cash-equivalent asset). Oleh karena itu, antarmuka pemrograman aplikasi (API Gateway) modul Hotel menerapkan perimeter keamanan pertahanan berlapis (defense-in-depth) untuk mencegah ancaman berikut:
1. **Pencurian Akun & Pembobolan Kredensial (Credential Stuffing):**
   Serangan otomatis untuk membajak akun tamu dan menguras saldo poin ke voucher belanja atau transfer miles gelap.
2. **Manipulasi Transaksi di Jalur Transmisi (Man-in-the-Middle Attack):**
   Upaya menyadap atau mengubah kuantitas poin atau nomor rekening mitra saat proses transfer aliansi berlangsung.
3. **Eksploitasi Transaksi Bersamaan (Race Condition & Double Spending):**
   Mencoba membelanjakan saldo poin yang sama secara bersamaan di dua properti hotel berbeda dalam interval milidetik.
4. **Kebocoran Data Pribadi Tamu (PII Leakage):**
   Penyadapan data nomor paspor, NIK/NPWP, nomor kartu kredit, dan pola perjalanan tamu.

---

## Bab 2: Mekanisme Perlindungan & Kontrol Perimeter
1. **Kunci Idempotensi Wajib (Enforced Idempotency Key):**
   Setiap endpoint mutasi poin (Earn, Redeem, Transfer) mewajibkan header `X-Idempotency-Key` bertipe UUID v4. Sistem menyimpan kunci dalam Redis cache selama 86.400 detik. Permintaan berulang dengan payload identik mengembalikan respons ter-cache seketika; permintaan berulang dengan payload berbeda langsung ditolak dengan status HTTP `409 Conflict`.
2. **Kunci Baris Transaksi Terdistribusi (Pessimistic Row Locking):**
   Saat pemotongan poin berlangsung, record tabel `htl_travel_passes` dikunci menggunakan `DB::table(...)->where('id', $id)->lockForUpdate()` di dalam transaksi database ACID. Hal ini secara mutlak memusnahkan potensi race condition atau double spending.
3. **Pemberian Tanda Tangan Kriptografis (HMAC-SHA256 Request Signing):**
   Seluruh panggilan API dari gerai POS atau gateway mitra wajib menyertakan tanda tangan digital yang dihitung dari gabungan timestamp, request path, dan request body menggunakan kunci rahasia simetris yang dirotasi berkala.
4. **Enkripsi Kolom Sensitif (Field-Level Sensitive Casting):**
   Nomor identitas mitra dan nomor paspor tamu dienkripsi menggunakan OpenSSL cipher AES-256-CBC melalui trait bawaan Laravel `encrypted` cast sebelum disimpan ke dalam piringan disk basis data.

---

## Bab 3: Pemantauan Deteksi Anomali & Rate Limiting
- **Pembatasan Frekuensi Permintaan (Rate Limiting):**
  Maksimum 60 permintaan per menit per alamat IP untuk antarmuka publik tamu, dan 300 permintaan per menit untuk gateway mitra terverifikasi.
- **Deteksi Geometris Penarikan Poin (Velocity Check):**
  Sistem membekukan penarikan poin otomatis (trigger security lock) jika mendeteksi penukaran poin berturut-turut di dua lokasi geografis yang terpisah lebih dari 500 kilometer dalam kurun waktu kurang dari 2 jam.

