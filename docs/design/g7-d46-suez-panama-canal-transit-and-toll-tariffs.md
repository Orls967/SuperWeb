# G7-D46: Suez & Panama Canal Transit Toll Tariffs & Navigational Governance

## 1. Konteks Geopolitik & Operasional Jalur Terusan Utama Dunia

Dua arteri pelayaran samudera paling vital di dunia — Terusan Suez di Mesir dan Terusan Panama di Amerika Tengah — memangkas ribuan mil laut rute pelayaran antara Asia, Eropa, dan Amerika. Namun, biaya lintas (*Transit Toll*) yang mencapai ratusan ribu hingga jutaan dolar per pelayaran memerlukan pemodelan finansial dan kepatuhan navigasi yang sangat presisi.

Keputusan arsitektur **G7-D46** menetapkan standarisasi kalkulasi tarif tol Terusan Suez dan Panama, manajemen jendela konvoi navigasi, serta pembebanan akrual keuangan pelayaran.

---

## 2. Struktur Perhitungan Tarif Terusan Suez (SCA Standard)

Otoritas Terusan Suez (*Suez Canal Authority - SCA*) menggunakan tonase bersih khusus (*Suez Canal Net Tonnage - SC_NT*) dan mata uang hak tarik khusus IMF (*Special Drawing Rights - SDR*):

### 2.1 Formula Bertingkat (Tiered SDR Toll Rates)
Tarif dasar tol dihitung menggunakan sistem tarif marjinal bertingkat:
- **Tier 1 (5.000 SC_NT pertama)**: Tarif dasar tertinggi per ton.
- **Tier 2 (5.000 SC_NT berikutnya)**: Tarif menengah.
- **Tier 3 (10.000 SC_NT berikutnya)**: Tarif terdiskon bertahap.
- **Tier 4 (Sisa tonase di atas 20.000 SC_NT)**: Tarif skala ekonomis per ton.

$$\text{Total SDR Dasar} = \sum_{k=1}^{4} (\text{Tonase Tier}_k \times \text{Tarif SDR}_k)$$
$$\text{Total Biaya USD} = \text{Total SDR} \times \text{Kurs Tukar SDR/USD} \times (1 + \text{Biaya Tambahan Eskort Pandu \& Tunda})$$

### 2.2 Aturan Konvoi & Pinalti Keterlambatan Terusan Suez
Pelayaran melalui Terusan Suez dilakukan dalam sistem konvoi terkoordinasi satu arah:
- **Konvoi Arah Selatan (Southbound Convoy)**: Berkumpul di Port Said Anchorage sebelum batas waktu kedatangan (*Limit Time of Arrival - LTA*).
- **Konvoi Arah Utara (Northbound Convoy)**: Berkumpul di Suez Anchorage Port Tewfik.
- Keterlambatan melewati LTA dikenakan denda *Late Arrival Surcharge* hingga $10\%$ dari total tarif tol atau terpaksa menunggu konvoi hari berikutnya.

---

## 3. Struktur Perhitungan Tarif Terusan Panama (ACP Standard)

Otoritas Terusan Panama (*Autoridad del Canal de Panamá - ACP*) menerapkan sistem pengukuran universal (*Panama Canal Universal Measurement System - PC/UMS*):

| Komponen Biaya Tol Panama | Deskripsi Regulasi | Batasan / Parameter Operasi |
|---|---|---|
| **Tarif Dasar PC/UMS** | Dihitung berdasarkan kapasitas angkut kapal atau volume TEU untuk kapal kontainer. | Pintu air Panamax vs Pintu air Neopanamax (kapal berukuran lebih besar). |
| **Freshwater Surcharge (Biaya Air Tawar)** | Kompensasi fluktuasi ketinggian air di Danau Gatun akibat fenomena El Niño. | Terdiri atas komponen tetap ($10.000\,\text{USD}$) dan komponen variabel ($1-10\%$) tergantung level air danau. |
| **Slot Reservasi Lelang (Booking Auction)** | Biaya lelang untuk menjamin kepastian tanggal transit tanpa harus mengantre di labuh jangkar. | Dapat melonjak hingga jutaan dolar saat antrean kapal menumpuk di perairan Balboa/Cristobal. |
| **Jasa Lokomotif Listrik (Mules) & Pandu** | Penarikan kapal di dalam bilik pintu air (*Locks*) oleh lokomotif listrik. | Wajib 4 hingga 8 unit lokomotif penarik tergantung tonase dan panjang kapal. |

---

## 4. Evaluasi Trade-Off Rute: Terusan vs Tanjung Harapan (Cape of Good Hope)

Layanan `CanalTransitAndPassageTollService` menyediakan analisis komparasi ekonomi real-time:
$$\Delta \text{Biaya Rute} = (\text{Biaya Bunker}_{\text{Cape}} + \text{Sewa Harian}_{\text{Cape}} \times \Delta t) - (\text{Tarif Tol Terusan} + \text{Biaya Bunker}_{\text{Canal}} + \text{Sewa Harian}_{\text{Canal}} \times t)$$
Jika harga bahan bakar bunker sedang anjlok dan tarif tol kanal sangat tinggi, berlayar memutar melalui Tanjung Harapan di Afrika Selatan dapat menghasilkan efisiensi biaya bersih (*cost saving*) meskipun menambah durasi pelayaran $10-14$ hari.
