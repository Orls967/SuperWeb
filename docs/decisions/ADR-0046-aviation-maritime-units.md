# ADR-0046: Satuan Pengukuran Standar Aviasi dan Maritim (G7-D46)

## Status
Diterima (Accepted) — 2026-11-08

## Konteks
Operasional aviasi komersial dan armada maritim melibatkan konversi nilai moneter, massa muatan, jarak tempuh, durasi waktu, temperatur, dan koordinat spasial lintas yurisdiksi. Ketidaktepatan representasi tipe data floating point pada sistem finansial dan kalkulasi teknis berisiko menimbulkan galat pembulatan kumulatif fatal.

## Keputusan
Menetapkan standardisasi tipe data dan satuan ukur integer deterministik untuk seluruh modul aviasi (`Aviation`) dan maritim (`Port`, `Marine`):
1. **Moneter:** IDR integer rupiah penuh; USD dalam integer sen (cents, 2 desimal). Persentase dan rasio dinyatakan dalam integer basis poin (bps, 10.000 bps = 100,00%).
2. **Massa & Berat:** Bagasi penumpang dan paket kargo udara dalam gram (integer); hasil panen akuakultur dalam kilogram (integer); bahan bakar bunker kapal dalam integer ton metrik.
3. **Jarak & Dimensi:** Jarak tempuh udara dalam kilometer (km integer); jarak pelayaran laut dalam nautical miles (nm integer); volume kargo dalam milimeter kubik ($mm^3$) atau $m^3 \times 1.000$.
4. **Fisika & Waktu:** Temperatur dalam kelipatan 0,1 °C integer (mis. 24,5 °C disimpan sebagai 245); durasi operasional dalam integer menit; koordinat lintang dan bujur disimpan sebagai derajat $\times 10.000$ integer (presisi 4 desimal).
5. **Waktu:** Seluruh stempel waktu disimpan dalam format UTC secara internal; ditampilkan dalam zona waktu lokal bandara/pelabuhan (WIB, WITA, WIT) saat dipresentasikan ke pengguna. Float dilarang mutlak untuk kuantitas dan uang.

## Konsekuensi
Kalkulasi pembagian proporsional menggunakan algoritma Hamilton / Largest Remainder dengan tie-break deterministik. Seluruh antarmuka API dan DTO menerima dan mengembalikan representasi integer.
