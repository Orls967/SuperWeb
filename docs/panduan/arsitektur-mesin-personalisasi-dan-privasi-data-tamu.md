# Arsitektur Mesin Personalisasi Perjalanan & Perlindungan Privasi Tamu — Fase 112

## Bab 1: Landasan Etika & Privasi Data Tamu (PDP & GDPR)
Mesin personalisasi perjalanan pada program Global Travel Pass beroperasi di bawah prinsip perlindungan data pribadi yang sangat ketat sesuai Undang-Undang Pelindungan Data Pribadi (UU PDP No. 27/2022) dan General Data Protection Regulation (GDPR). 

Prinsip fundamental privasi meliputi:
1. **Persetujuan Eksplisit (Informed Consent):** Tamu memiliki hak memilih untuk mengaktifkan atau menonaktifkan fitur rekomendasi personalisasi pada profil Travel Pass mereka.
2. **Kepatuhan Unsubscribe Instan:** Setiap rekomendasi kampanye penawaran tujuan liburan yang dikirimkan melalui surel atau aplikasi seluler wajib menyertakan tombol berhenti berlangganan (one-click unsubscribe) yang langsung dieksekusi secara instan tanpa masa tunggu.
3. **Penyimpanan Terenkripsi (Field-Level Encryption):** Riwayat kesehatan preferensi bantal, alergi makanan, dan data paspor disimpan dengan enkripsi AES-256-GCM.
4. **Prinsip Hak untuk Dilupakan (Right to Be Forgotten):** Jika tamu menutup akun, seluruh vektor profil personalisasi dihapus permanen atau dianonimkan untuk keperluan analisis statistik makro tanpa identitas pribadi.

---

## Bab 2: Vektor Profil Tamu 360 Derajat
Mesin rekomendasi mengolah data interaksi tamu dalam representasi vektor multi-dimensi tanpa melibatkan model AI probabilistik yang tidak terduga, melainkan menggunakan algoritma skoring terukur berbasis aturan deterministik (deterministic rule-based vector scoring):
1. **Dimensi Frekuensi & Pola Perjalanan (Travel Velocity):**
   - Menginap akhir pekan vs hari kerja (Business vs Leisure ratio).
   - Preferensi iklim dan topografi (Resor Pantai, Resor Pegunungan, Hotel Pusat Kota Metropolis).
2. **Dimensi Selera Rekreasi & Gaya Hidup:**
   - Frekuensi pemesanan tiket konser musik / festival di modul Venue.
   - Kunjungan ke fasilitas spa, pusat kebugaran, dan kelas yoga.
   - Pilihan santapan kuliner (fine dining wine tasting, makanan vegetarian, hidangan laut segar).
3. **Dimensi Sensitivitas Harga & Nilai Poin:**
   - Kecenderungan menukarkan poin di saat promosi reward flash sale vs penukaran kamar reguler.
   - Rasio pembayaran tunai gabungan (Cash + Points combo).

---

## Bab 3: Logika Pembentukan Rekomendasi Destinasi
Algoritma pembentukan kampanye perjalanan personalisasi menjalankan tiga tahapan seleksi:
1. **Penyaringan Ketersediaan & Relevansi Geografis:**
   Mencocokkan properti hotel atau konser festival yang memiliki kuota kamar award kosong dalam kurun waktu 14 hingga 60 hari mendatang.
2. **Perhitungan Skor Kesesuaian (Match Affinity Score):**
   $$\text{Score} = w_1 \cdot \text{CategoryAffinity} + w_2 \cdot \text{TierMultiplier} + w_3 \cdot \text{SeasonalityBonus}$$
   Jika skor kesesuaian melampaui ambang batas $0.75$, sistem menghasilkan kartu penawaran khusus pada dasbor tamu.
3. **Pencatatan Jejak Audit (Audit Trail):**
   Setiap rekomendasi yang diterbitkan dicatat dalam tabel `htl_personalized_travel_campaigns` lengkap dengan alasan penawaran, tanggal terbit, stempel waktu interaksi tamu, dan status persetujuan komunikasi.

