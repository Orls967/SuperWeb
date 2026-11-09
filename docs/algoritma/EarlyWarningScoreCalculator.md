# Algoritma: EarlyWarningScoreCalculator (B2)

## 1. Intuisi & Latar Belakang
Skor Peringatan Dini Nasional (NEWS2 - Royal College of Physicians) digunakan untuk mendeteksi perburukan klinis akut pada pasien dewasa secara objektif dan terstandarisasi. Kalkulator ini mengevaluasi 7 parameter fisiologis vital pasien dan menghasilkan skor risiko komposit yang menentukan tingkat respons klinis yang diperlukan.

## 2. Tabel Poin Parameter Fisiologis
1. **Laju Pernapasan (Respiration Rate / menit)**:
   - <= 8: 3 poin
   - 9 - 11: 1 poin
   - 12 - 20: 0 poin
   - 21 - 24: 2 poin
   - >= 25: 3 poin
2. **Saturasi Oksigen SpO2 Skala 1 (%)**:
   - <= 91%: 3 poin
   - 92 - 93%: 2 poin
   - 94 - 95%: 1 poin
   - >= 96%: 0 poin
3. **Terapi Oksigen Tambahan (Supplemental Oxygen)**:
   - Tidak: 0 poin
   - Ya: 2 poin
4. **Tekanan Darah Sistolik (Systolic BP / mmHg)**:
   - <= 90: 3 poin
   - 91 - 100: 2 poin
   - 101 - 110: 1 poin
   - 111 - 219: 0 poin
   - >= 220: 3 poin
5. **Denyut Nadi (Heart Rate / bpm)**:
   - <= 40: 3 poin
   - 41 - 50: 1 poin
   - 51 - 90: 0 poin
   - 91 - 110: 1 poin
   - 111 - 130: 2 poin
   - >= 131: 3 poin
6. **Tingkat Kesadaran (Consciousness)**:
   - Alert (A): 0 poin
   - Confused / Voice / Pain / Unresponsive (CVPU): 3 poin
7. **Suhu Tubuh (Temperature dalam 0,1 °C)**:
   - <= 35,0 °C (<= 350): 3 poin
   - 35,1 - 36,0 °C (351 - 360): 1 poin
   - 36,1 - 38,0 °C (361 - 380): 0 poin
   - 38,1 - 39,0 °C (381 - 390): 1 poin
   - >= 39,1 °C (>= 391): 2 poin

## 3. Klasifikasi Tingkat Risiko (Risk Category)
- **TINGGI (HIGH)**: Total skor >= 7 (Eskalasi darurat tim medis gawat darurat / ICU)
- **SEDANG (MEDIUM)**: Total skor 5 - 6 (Pemberitahuan dokter jaga bangsal segera)
- **RENDAH_SEDANG (LOW_MEDIUM)**: Total skor <= 4 DENGAN setidaknya satu parameter bernilai 3 tunggal (Pemantauan frekuensi perawat ditingkatkan)
- **RENDAH (LOW)**: Total skor 0 - 4 tanpa ada parameter bernilai 3 (Pemantauan rutin shift 12 jam)

## 4. Vektor Uji Resmi
1. Input: (18, 97, tidak, 120, 72, A, 368)
   - Poin: 0 + 0 + 0 + 0 + 0 + 0 + 0 = 0
   - Kategori: `RENDAH` (0)
2. Input: (22, 94, ya, 105, 105, A, 385)
   - Poin: 2 + 1 + 2 + 1 + 1 + 0 + 1 = 8
   - Kategori: `TINGGI` (8)
3. Input: (14, 96, tidak, 88, 70, A, 365)
   - Poin: 0 + 0 + 0 + 3 + 0 + 0 + 0 = 3 (parameter sistolik bernilai 3)
   - Kategori: `RENDAH_SEDANG` (3)
4. Input: (24, 92, tidak, 100, 95, A, 370)
   - Poin: 2 + 2 + 0 + 2 + 1 + 0 + 0 = 7
   - Kategori: `TINGGI` (7)
5. Input: (20, 98, tidak, 130, 80, C, 372)
   - Poin: 0 + 0 + 0 + 0 + 0 + 3 + 0 = 3 (parameter kesadaran C bernilai 3)
   - Kategori: `RENDAH_SEDANG` (3)

## 5. Sertifikat & Verifikasi
Hasil kalkulasi dibungkus dalam `News2CalculationCertificate` yang memverifikasi kebenaran penjumlahan komponen dan validitas klasifikasi risiko melalui `News2CertificateVerifier`. Kegagalan sertifikat melempar `InvalidNews2CertificateException`.

- Kompleksitas: $O(1)$ waktu dan ruang.
- Lokasi Kode: `Modules\Hospital\Domain\Algorithms\EarlyWarningScoreCalculator.php`
