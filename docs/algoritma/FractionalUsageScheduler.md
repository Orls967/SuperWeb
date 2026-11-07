# Spesifikasi & Pembuktian Matematis: FractionalUsageScheduler (H8)

## 1. Definisi Masalah
Sistem kepemilikan fraksional vila/resort membagi 1 unit properti menjadi $K = 52$ fraksi mingguan dalam setahun. Pemilik $m$ fraksi memiliki hak guna tepat $m$ minggu ($1 \le m \le 52$).
Jadwal yang dihasilkan wajib memenuhi kondisi:
$$W_{owner} \cap W_{rental} = \emptyset \quad \text{dan} \quad |W_{owner}| + |W_{rental}| = m$$
di mana:
- $W_{owner}$ adalah himpunan minggu yang dipilih pemilik untuk ditinggali.
- $W_{rental}$ adalah himpunan sisa hak minggu yang dialihkan ke pool sewa publik hotel (*Hotel Public Rental Pool*).

## 2. Invarian Bebas Benturan (Zero-Collision Invariant)
Untuk setiap pasangan minggu $w_i, w_j \in (W_{owner} \cup W_{rental})$ dengan $i \neq j$:
$$w_i \neq w_j \quad \text{dan} \quad 1 \le w_i \le 52$$

## 3. Penentuan Musim Kalender (Seasonality Index)
Minggu kalender diklasifikasikan ke dalam 4 musim:
- **Peak Summer**: Minggu 24 s/d 32 (Liburan musim panas)
- **Peak Year End**: Minggu 50 s/d 52 dan Minggu 1 s/d 2 (Natal & Tahun Baru)
- **High Season**: Minggu 13 s/d 18 (Libur Paskah & Idul Fitri)
- **Low Season**: Seluruh minggu lainnya.

## 4. Pembagian Hasil Sewa Rental Pool
Minggu dalam $W_{rental}$ yang terjual kepada tamu umum menghasilkan pendapatan kotor $R_{gross}$. Dividen bersih pemilik dihitung dengan formula:
$$Dividen = R_{gross} \times (1 - Fee_{management}) \quad \text{dengan } Fee_{management} = 15\%$$

