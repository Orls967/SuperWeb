# Standar Kliring & Prorasi Pendapatan Tiket Lintas Maskapai (IATA Interline & Codeshare Proration)

## 1. Pendahuluan & Struktur Perjanjian Antar-Maskapai (Interline Agreements)

Dalam industri aviasi global, penumpang seringkali melakukan perjalanan melintasi beberapa segmen rute yang dioperasikan oleh maskapai penerbangan yang berbeda, namun dicakup dalam satu nomor tiket elektronik (*single e-ticket*). Contoh rute:
- Segmen 1: Surabaya (SUB) ke Jakarta (CGK) — Dioperasikan oleh Garuda Indonesia (GA)
- Segmen 2: Jakarta (CGK) ke Tokyo Haneda (HND) — Dioperasikan oleh All Nippon Airways (NH)
- Segmen 3: Tokyo Haneda (HND) ke Los Angeles (LAX) — Dioperasikan oleh United Airlines (UA)

Tiket diterbitkan oleh satu maskapai (*Validating Carrier* atau *Plating Carrier*), yang menerima pembayaran tunai atau kartu kredit penuh dari penumpang. Namun, penerimaan tersebut harus didistribusikan kepada masing-masing maskapai yang mengoperasikan segmen penerbangan (*Operating Carrier*). Proses pembagian pendapatan tiket multi-segmen ini disebut **Prorasi Pendapatan Tiket** (*Fare Proration*).

---

## 2. Metodologi Prorasi Standar IATA (Prorate Manual Passenger / PMP)

Prorasi tiket dapat dilakukan melalui tiga metodologi utama:

### 2.1. Straight Rate Proration (SRP)
Prorasi tarif lurus membagi total tarif tiket bersih secara proporsional berdasarkan tarif lokal masing-masing segmen rute jika dibeli secara terpisah (*published local point-to-point fares*):
$$\text{Share}_i = \text{Total Fare} \times \frac{\text{LocalFare}_i}{\sum_{k=1}^m \text{LocalFare}_k}$$

Kelemahan SRP adalah ketergantungan pada fluktuasi tarif lokal yang seringkali tidak mencerminkan jarak sebenarnya atau biaya bahan bakar.

### 2.2. Mileage / Distance Proration (Ticketed Point Mileage - TPM)
Prorasi jarak membagi pendapatan berdasarkan jarak geografis resmi penerbangan (*Ticketed Point Mileage* yang diterbitkan IATA) antar-bandara:
$$\text{Share}_i = \text{Total Fare} \times \frac{\text{TPM}_i}{\sum_{k=1}^m \text{TPM}_k}$$

### 2.3. Prorasi Faktor Bobot (Prorate Factor Method / PFM - IATA Multilateral Prorate Agreement)
IATA mengembangkan metode faktor prorasi tertimbang (*Weighted Prorate Factor*) untuk memperhitungkan biaya operasional penerbangan jarak pendek (*short-haul fixed terminal costs*) yang lebih tinggi per kilometer dibandingkan penerbangan jarak jauh (*long-haul cruising efficiency*).
Faktor prorasi dihitung menggunakan fungsi kurva eksponensial terkalibrasi:
$$\text{PF}_i = a \cdot (\text{TPM}_i)^b + c$$
di mana parameter $a, b, c$ disepakati secara periodik dalam konferensi IATA PMP.

Bagi hasil untuk segmen $i$ dihitung sebagai:
$$\text{Share}_i = \text{Net Revenue} \times \frac{\text{PF}_i}{\sum_{j=1}^m \text{PF}_j}$$

---

## 3. Jenis Kerjasama Codeshare & Pengaruhnya pada Pembagian Pendapatan

Perjanjian codeshare (*Codeshare Agreement*) merupakan bentuk integrasi komersial yang lebih erat daripada sekadar interline biasa. Maskapai mitra memasang kode penerbangannya pada penerbangan maskapai lain.

### 3.1. Block-Space Agreement
Marketing Carrier membeli blok kursi tetap pada penerbangan Operating Carrier.
1. **Hard Block (Risk-bearing)**:
   - Marketing Carrier membeli $N$ kursi dengan tarif tetap $P_{\text{seat}}$.
   - Kewajiban pembayaran timbul terlepas dari apakah kursi tersebut berhasil dijual kepada penumpang ritel atau terbang kosong (*deadweight loss* ditanggung Marketing Carrier).
   - Pengakuan pendapatan oleh Operating Carrier bersifat pasti (*guaranteed yield*).
2. **Soft Block (Non-risk bearing)**:
   - Marketing Carrier mencadangkan $N$ kursi hingga batas waktu pelepasan (*release date* / cutoff $T-7$ hari atau $T-14$ hari sebelum keberangkatan).
   - Kursi yang tidak terjual pada batas waktu tersebut dikembalikan ke inventaris Operating Carrier tanpa penalti finansial.

### 3.2. Free-Sale Agreement
Marketing Carrier menjual kursi secara dinamis langsung dari inventaris terbuka milik Operating Carrier.
- Pembagian pendapatan disepakati menggunakan tarif khusus yang dinegosiasikan (*Special Prorate Agreement* / SPA) atau komisi persentase basis poin (*bps revenue split*, e.g., 8.500 bps = 85% untuk Operating Carrier, 1.500 bps = 15% untuk Marketing Carrier sebagai komisi penjualan).

---

## 4. Algoritma A8 (CodeshareMileageAccrualEngine)

Tujuan algoritma A8 adalah:
1. Membagi nilai penerimaan tiket antara Operating Carrier dan Marketing Carrier sesuai ketentuan perjanjian aktif (`avi_codeshare_agreements`).
2. Menghitung kredit frequent flyer miles yang menjadi hak penumpang berdasarkan kelas kabin, jarak tempuh, dan tier status.
3. Mencegah manipulasi klaim ganda (*anti-double dipping claim*).

### 4.1. Rumus Perhitungan Poin Miles Loyalitas
Poin miles yang diakui penumpang dihitung sebagai:
$$\text{Miles Accrued} = \text{TPM} \times M_{\text{cabin}} \times (1 + B_{\text{tier}})$$
- $\text{TPM}$: Jarak segmen penerbangan resmi IATA (dalam mil laut / statuta).
- $M_{\text{cabin}}$: Faktor pengali kelas kabin:
  - Economy Promo (V, L, T): $0.50$
  - Economy Standard (Y, B, M): $1.00$
  - Business Class (J, C, D): $1.50$
  - First Class (F, A): $2.00$
- $B_{\text{tier}}$: Bonus tier penumpang:
  - Classic: $0.00$ (tanpa bonus)
  - Silver: $0.25$ (+25% bonus miles)
  - Gold: $0.50$ (+50% bonus miles)
  - Platinum: $1.00$ (+100% bonus miles)

### 4.2. Algoritma Kriptografis Anti-Double Dipping
Untuk mencegah penumpang mengklaim penerbangan codeshare yang sama pada dua program loyalitas aliansi (misalnya GarudaMiles dan Singapore Airlines KrisFlyer):
1. **Pembangkitan Kunci Unik Deterministik**:
   Setiap klaim miles menghasilkan token pendaftaran unik:
   $$\text{ClaimToken} = \text{HMAC-SHA256}\left(\text{SecretKey}, \text{TicketNo} \parallel \text{SegmentCouponNo} \parallel \text{FlightDate}\right)$$
2. **Pengecekan Konsensus Ledger**:
   Sebelum transaksi dicatat, sistem memeriksa tabel `avi_loyalty_transactions` untuk mendeteksi apakah `ClaimToken` sudah pernah dicatat dengan status `ACCRUED` atau `PENDING_CLEARING`.
3. **Penyelesaian Transaksi Terdistribusi**:
   Jika `ClaimToken` sudah ada, algoritma menolak transaksi dengan exception `DuplicateFrequentFlyerClaimException` dan mencatat upaya manipulasi ke log audit kepatuhan.

---

## 5. Kliring & Penyelesaian Antar-Maskapai (IATA Clearing House / ICH)

Pada akhir siklus bulanan atau mingguan, maskapai-maskapai mitra melakukan rekonsiliasi total tagihan dan piutang melalui berkas standar ekspor kliring codeshare (*Codeshare Clearing Manifest CSV*).

Struktur berkas kliring meliputi:
- Identitas Periode Kliring (Bulan/Tahun, Siklus ICH 1 s.d. 4).
- Kode Dua Karakter Maskapai Penagih (*Creditor Carrier Code*).
- Kode Dua Karakter Maskapai Tertagih (*Debtor Carrier Code*).
- Nomor Tiket Elektronik 13-Digit IATA.
- Nomor Kupon Penerbangan (1 s.d. 4).
- Sektor Penerbangan (Asal - Tujuan, e.g., DPS-CGK).
- Jarak Terbang (TPM).
- Nilai Tiket Kotor (*Gross Fare Currency & Minor Units*).
- Nilai Pembagian Bersih (*Net Prorated Settlement Amount*).
- Pajak dan Biaya Penanganan (*ISC - Interline Service Charge*, umumnya 9%).
- Status Persetujuan Faktur (*Accepted, Disputed, Adjusted*).

