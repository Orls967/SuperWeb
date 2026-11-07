# ADR-0117: Arsitektur Keamanan Kerumunan Deterministik, Akses Biometrik Privacy-Preserving, dan Personalisasi Pengalaman Tamu On-Site

## Konteks & Permasalahan

Dalam operasional festival dan venue hiburan berkapasitas ribuan orang (misal 5.000–50.000 pengunjung), kegagalan kontrol massa berpotensi menimbulkan bencana fatal (crowd crush/surge) dan kerugian reputasi tak terpulihkan. Di sisi lain, turnstile gerbang yang lambat dan antrean bar yang panjang merusak pengalaman tamu VIP serta menurunkan pendapatan per jam.

Terdapat tiga tantangan arsitektural utama:
1. **Privasi Data Biometrik:** Regulasi ketat (UU PDP Indonesia No. 27/2022 dan GDPR) melarang penyimpanan data biometrik mentah tanpa izin eksplisit dan perlindungan kriptografis tinggi.
2. **Keterbatasan Reaktivitas Kerumunan:** Jika operator menunggu kerumunan padat secara fisik sebelum bertindak, tindakan sudah terlambat untuk mencegah penumpukan di lorong sempit. Diperlukan prediksi deterministik berbasis waktu (15 menit ke depan).
3. **Spam Penawaran Promosi:** Mengirim terlalu banyak notifikasi promosi ke tamu di lokasi menciptakan kejenuhan dan komplain (alert fatigue).

## Keputusan Arsitektur

1. **Pemisahan Template Biometrik Nir-Balik (One-Way Salted Biometric Hashing):**
   - Vektor fitur wajah dikonversi menggunakan garam kriptografis unik menjadi hash HMAC-SHA256 (`biometric_template_hash`).
   - Fitur asli dibuang seketika setelah pencocokan memori tanpa pernah ditulis ke disk atau basis data.
   - Hak pencabutan (Right to Revoke) didukung penuh via transisi status ke `REVOKED`.

2. **Model Prediksi Lonjakan Kepadatan Linier-Eksponensial 15 Menit:**
   - Memproyeksikan headcount dan densitas orang per meter persegi dengan mengombinasikan turunan laju masuk ($d H_{in}/dt$) dan keluar ($d H_{out}/dt$).
   - Pembatasan gerbang (`GATE_RESTRICTED`) dipicu otomatis jika kepadatan diproyeksikan melampaui $4.5 \text{ orang/m}^2$ atau $95\%$ kapasitas legal.

3. **Indeks Layanan WPS (Wait Per Service) Bar & F&B:**
   - Menerapkan teori antrean M/M/c untuk mengukur skor WPS (0–100).
   - Menghubungkan lonjakan antrean secara otonom ke kontrak HCM Shift Bounty untuk menambah tenaga kerja kasir secara dinamis.

4. **Filter Anti-Fatigue & Idempotensi Penawaran VIP:**
   - Membatasi penawaran on-site maksimal 1 per jam dan maksimal 3 per malam.
   - Enkapsulasi idempotensi deterministik mencegah duplikasi promosi.

## Konsekuensi

- **Positif:** Keamanan kerumunan proaktif, nol kebocoran data biometrik mentah, kepatuhan hukum privasi penuh, antrean bar terkendali, dan konversi promosi VIP optimal.
- **Negatif:** Diperlukan kamera sensor liveness terverifikasi di gerbang turnstile dan sinkronisasi data sensor telemetri secara periodik.

