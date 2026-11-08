# Pedoman Tata Kelola: Manajemen Risiko Poin & Cadangan Likuiditas Keuangan — Fase 112

## Bab 1: Kerangka Kerja Pengendalian Risiko Finansial Poin
Penerbitan poin loyalitas dalam skala puluhan juta unit memerlukan kerangka manajemen risiko likuiditas yang setara dengan lembaga keuangan. Direksi Keuangan menetapkan kebijakan cadangan kas likuiditas (escrow liquidity reserve) untuk menjamin solvabilitas program Travel Pass setiap saat.

### Pilar Manajemen Risiko Utama:
1. **Rasio Kecukupan Cadangan Kas (Reserve Coverage Ratio):**
   Grup hotel wajib memarkir dana tunai di rekening giro perbankan terpisah (`clearing:external:IDR`) sekurang-kurangnya sebesar 30% dari total nilai nominal liabilitas poin yang beredar aktif di neraca.
2. **Stress Testing Musiman (Seasonal Redemption Shock Simulation):**
   Setiap triwulan, tim treasury melakukan simulasi skenario ekstrem: "Bagaimana jika 40% anggota melakukan penebusan kamar dan konversi miles maskapai secara serentak pada musim libur akhir tahun?".
3. **Penyusutan Otomatis Melalui Breakage Terjadwal:**
   Masa aktif batch poin yang dibatasi 730 hari menjamin siklus pembersihan liabilitas berjalan alami tanpa perlu intervensi pemotongan saldo sepihak.

---

## Bab 2: Monitoring Indikator Kesehatan Keuangan (Financial Health KPIs)
Setiap hari Senin pagi, ringkasan indikator berikut disajikan di hadapan Dewan Direksi:
- **Outstanding Points Velocity (OPV):** Kecepatan perputaran poin dari saat diterbitkan hingga saat ditebus.
- **Burn-to-Earn Ratio:** Rasio jumlah poin yang ditebus dibandingkan jumlah poin yang diterbitkan pada bulan berjalan (target sehat: $0.70 \le \text{Ratio} \le 0.85$).
- **Cost of Redemption (CoR):** Rata-rata biaya tunai riil yang dikeluarkan grup hotel untuk melayani penukaran setiap 1.000 PTS.

---

## Bab 3: Prosedur Intervensi Dinamis Treasury
Bila rasio pencairan poin melampaui batas aman likuiditas ($> 95\%$):
1. Sistem secara otomatis menaikkan koefisien tarif poin kamar menggunakan algoritma `AwardNightPricer` (H11) untuk meredam laju penebusan.
2. Membuka promosi eksklusif penukaran voucher produk internal (F&B dan spa) yang memiliki margin laba kotor lebih tinggi dibandingkan penukaran kas tunai.
3. Melakukan injeksi likuiditas tambahan dari kas induk holding ke akun clearing loyalitas.

