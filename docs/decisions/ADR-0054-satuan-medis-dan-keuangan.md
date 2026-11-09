# ADR-0054: Standar Satuan Medis dan Keuangan Modul Hospital

## Konteks & Masalah
Pengelolaan rekam medis dan transaksi rumah sakit melibatkan berbagai satuan fisik dan moneter yang rentan terhadap galat pembulatan jika menggunakan bilangan pecahan (floating-point). Keuangan rumah sakit, data fisiologis vital, dan hasil uji diagnostik menuntut presisi mutlak.

## Keputusan Arsitektur
1. **Keuangan**: Nilai moneter disimpan dalam satuan minor terkecil integer Rupiah (`IDR`). Persentase disimpan dalam basis points (`bps`, 1% = 100 bps, 100% = 10.000 bps).
2. **Suhu Tubuh & Cold-Chain**: Disimpan dalam integer sepersepuluh derajat Celsius ($0,1^\circ\text{C}$). Contoh: $36,8^\circ\text{C} = 368$.
3. **Tekanan Darah**: Disimpan dalam integer milimeter merkuri (`mmHg`).
4. **Saturasi Oksigen**: Disimpan dalam integer persentase (`SpO2%`).
5. **Waktu**: Seluruh penanda waktu disimpan dalam UTC dengan representasi objek `\Carbon\CarbonImmutable`. Tampilan antarmuka dikonversikan ke zona waktu operasional rumah sakit (WIB/WITA/WIT).

## Konsekuensi
- Menghilangkan bug pembulatan IEEE-754 pada seluruh perhitungan akuntansi dan formula klinis.
- Mengikuti Konvensi Umum SuperWeb U-1 dan U-2.
