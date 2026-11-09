# Algoritma: BedEfficiencyIndicators (B4)

## 1. Intuisi & Latar Belakang
Indikator Efisiensi Tempat Tidur Barber-Johnson merupakan standar pengukuran kinerja manajemen rumah sakit yang mengintegrasikan empat metrik esensial: Bed Occupancy Rate (BOR), Average Length of Stay (ALOS), Turn Over Interval (TOI), dan Bed Turn Over (BTO). Keempat indikator ini membentuk grafik Barber-Johnson untuk menentukan apakah rumah sakit beroperasi pada zona efisiensi ideal (BOR 75-85%, ALOS 3-12 hari, TOI 1-3 hari, BTO >= 30 kali/tahun).

## 2. Formulasi Matematis Presisi Integer (Tanpa Float)
Seluruh kalkulasi menggunakan matematika rasional dan integer unit minor sesuai Konvensi U-1:
1. **BOR (Bed Occupancy Rate)** dalam basis points (bps, 100% = 10.000 bps):
   $$\text{BOR (bps)} = \left\lfloor \frac{\text{hari\_rawat} \times 10.000}{\text{jumlah\_bed} \times \text{jumlah\_hari\_periode}} \right\rfloor$$
2. **ALOS (Average Length of Stay)** dalam mili-hari (1 hari = 1.000 mili):
   $$\text{ALOS (mili)} = \left\lfloor \frac{\text{hari\_rawat} \times 1.000}{\text{pasien\_keluar}} \right\rfloor$$
3. **TOI (Turn Over Interval)** dalam mili-hari:
   $$\text{TOI (mili)} = \left\lfloor \frac{(\text{jumlah\_bed} \times \text{jumlah\_hari\_periode} - \text{hari\_rawat}) \times 1.000}{\text{pasien\_keluar}} \right\rfloor$$
4. **BTO (Bed Turn Over)** dalam mili-putaran per bed:
   $$\text{BTO (mili)} = \left\lfloor \frac{\text{pasien\_keluar} \times 1.000}{\text{jumlah\_bed}} \right\rfloor$$

## 3. Vektor Uji Resmi
1. **Kasus Uji A**:
   - Parameter: 200 bed, 30 hari periode, 4.800 hari-rawat, 1.000 pasien keluar.
   - Perhitungan:
     - Kapasitas total bed-hari: $200 \times 30 = 6.000$ bed-hari.
     - BOR: $(4.800 \times 10.000) / 6.000 = 8.000$ bps (80,00%).
     - ALOS: $(4.800 \times 1.000) / 1.000 = 4.800$ mili (4,8 hari).
     - TOI: $((6.000 - 4.800) \times 1.000) / 1.000 = (1.200 \times 1.000) / 1.000 = 1.200$ mili (1,2 hari).
     - BTO: $(1.000 \times 1.000) / 200 = 5.000$ mili (5,0 kali putaran).
   - Hasil: `BOR: 8.000, ALOS: 4.800, TOI: 1.200, BTO: 5.000`.

2. **Kasus Uji B**:
   - Parameter: 100 bed, 30 hari periode, 1.500 hari-rawat, 600 pasien keluar.
   - Perhitungan:
     - Kapasitas total bed-hari: $100 \times 30 = 3.000$ bed-hari.
     - BOR: $(1.500 \times 10.000) / 3.000 = 5.000$ bps (50,00%).
     - ALOS: $(1.500 \times 1.000) / 600 = 2.500$ mili (2,5 hari).
     - TOI: $((3.000 - 1.500) \times 1.000) / 600 = (1.500 \times 1.000) / 600 = 2.500$ mili (2,5 hari).
     - BTO: $(600 \times 1.000) / 100 = 6.000$ mili (6,0 kali putaran).
   - Hasil: `BOR: 5.000, ALOS: 2.500, TOI: 2.500, BTO: 6.000`.

## 4. Sertifikat & Verifikasi
Keluaran dibungkus dalam `BedEfficiencyCertificate` dan diverifikasi oleh `BedEfficiencyCertificateVerifier`. Verifikasi memastikan bahwa hubungan invarian matematis Barber-Johnson terpenuhi:
$$\text{BOR} = \frac{\text{ALOS}}{\text{ALOS} + \text{TOI}} \times 10.000$$

- Kompleksitas: $O(1)$ waktu dan ruang.
- Lokasi Kode: `Modules\Hospital\Domain\Algorithms\BedEfficiencyIndicators.php`

