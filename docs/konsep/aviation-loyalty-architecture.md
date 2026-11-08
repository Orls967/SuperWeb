# Arsitektur Program Loyalitas Aviasi (Frequent Flyer Program) & Valuasi Poin

## 1. Peran Strategis Program Loyalitas dalam Aviasi Modern

Program Loyalitas Maskapai Penerbangan (*Frequent Flyer Program* / FFP) bukan lagi sekadar program pemasaran untuk meningkatkan frekuensi terbang penumpang, melainkan telah berevolusi menjadi lini bisnis finansial mandiri berkapitalisasi tinggi. Maskapai terkemuka dunia bahkan memperoleh valuasi program loyalitas yang melebihi nilai operasi penerbangannya sendiri.

Poin loyalitas (*miles*) berfungsi sebagai mata uang kuasi-finansial (*quasi-currency*) yang:
1. Diterbitkan saat penumpang terbang (*Flight Accrual*) atau bertransaksi dengan mitra perbankan/kartu kredit (*Non-Air Accrual*).
2. Ditebus untuk tiket penerbangan (*Award Flight Redemption*), peningkatan kelas kabin (*Upgrade*), atau barang/layanan mitra merchant (*Partner Redemption*).
3. Dicatat sebagai liabilitas keuangan tertangguhkan (*Deferred Revenue Liability*) pada neraca maskapai sampai poin tersebut ditebus atau kedaluwarsa (*breakage*).

---

## 2. Struktur Akun & Tier Status

Sistem program loyalitas membagi anggota ke dalam empat tingkatan status keanggotaan:

### 2.1. Classic / Blue (Anggota Dasar)
- Syarat Masuk: Pendaftaran akun baru tanpa prasyarat terbang.
- Hak Istimewa: Akrual miles dasar (1x TPM), newsletter promosi awal.
- Masa Berlaku: Permanen selama akun aktif melakukan transaksi minimal sekali dalam 36 bulan.

### 2.2. Silver (Anggota Berkala)
- Syarat Masuk: 15 segmen penerbangan memenuhi syarat (*eligible flight sectors*) ATAU akumulasi 15.000 tier miles dalam 1 tahun kalender.
- Hak Istimewa:
  - Ekstra kuota bagasi cuma-cuma 5 kg.
  - Prioritas daftar tunggu reservasi (*waitlist priority*).
  - Bonus tier miles +25% pada setiap penerbangan.

### 2.3. Gold (Anggota Kerap Terbang)
- Syarat Masuk: 30 segmen penerbangan ATAU akumulasi 35.000 tier miles dalam 1 tahun kalender.
- Hak Istimewa:
  - Akses gratis ke Executive Airport Lounge bersama 1 orang pendamping.
  - Prioritas penanganan bagasi (*priority baggage tag*).
  - Prioritas check-in di loket Business Class.
  - Ekstra kuota bagasi cuma-cuma 15 kg.
  - Bonus tier miles +50%.

### 2.4. Platinum (Tingkat Elit Tertinggi)
- Syarat Masuk: 50 segmen penerbangan ATAU akumulasi 65.000 tier miles dalam 1 tahun kalender.
- Hak Istimewa:
  - Seluruh hak istimewa Gold.
  - Akses ke First Class Lounge dan fasilitas pengawalan bandara (*airport concierge*).
  - Jaminan reservasi kursi kelas ekonomi hingga 24 jam sebelum keberangkatan (*guaranteed seat availability*).
  - Ekstra kuota bagasi cuma-cuma 20 kg.
  - Bebas biaya perubahan jadwal tiket (*fee waiver*).
  - Bonus tier miles +100%.

---

## 3. Buku Besar Finansial Loyalti (Loyalty Ledger Engine)

Setiap penerbitan, penukaran, kedaluwarsa, atau penyesuaian poin dicatat dalam buku besar berpasangan (*double-entry loyalty subledger*) untuk menjamin integritas finansial:

### 3.1. Jenis Transaksi Loyalti
1. `FLIGHT_ACCRUAL`: Penambahan poin dari penerbangan yang telah diselesaikan (*flown status*).
2. `PARTNER_ACCRUAL`: Penambahan poin dari konversi poin kartu kredit bank mitra.
3. `TIER_BONUS_ACCRUAL`: Poin tambahan hasil pengali status keanggotaan elit.
4. `FLIGHT_REDEMPTION`: Pengurangan poin untuk penebusan tiket penghargaan (*award ticket*).
5. `UPGRADE_REDEMPTION`: Pengurangan poin untuk naik kelas dari Ekonomi ke Bisnis.
6. `POINTS_EXPIRY`: Penghapusan poin yang telah melewati batas usia aktif (*breakage recognition*).
7. `ADMIN_ADJUSTMENT`: Koreksi manual oleh manajer layanan pelanggan dengan persetujuan ganda (*four-eyes principle*).

### 3.2. Valuasi Nilai Wajar Poin (Fair Value per Point / FVPP)
Sesuai standar akuntansi keuangan internasional (IFRS 15 - *Revenue from Contracts with Customers*), nilai wajar poin harus dihitung berdasarkan harga penebusan rata-rata diskon:
$$\text{FVPP} = \frac{\sum \text{Nilai Nominal Tiket Penghargaan yang Ditebus}}{\sum \text{Jumlah Poin yang Digunakan}}$$

Saat poin diterbitkan, pendapatan sebesar $(\text{Poin Terbit} \times \text{FVPP})$ ditangguhkan ke akun liabilitas `2300-AVI-DEFERRED-LOYALTY-REVENUE`. Pendapatan baru diakui ke akun `4100-AVI-REVENUE-PASSENGER` saat tiket penghargaan diterbangkan.

---

## 4. Mekanisme Kedaluwarsa & Pengakuan Breakage

Poin yang tidak pernah ditebus oleh anggota FFP setelah periode tertentu diakui sebagai pendapatan maskapai (*breakage revenue*).
- Kebijakan Masa Berlaku: Poin berlaku selama 36 bulan kalender sejak tanggal transaksi akrual.
- Algoritma Prediksi Breakage: Sistem mengevaluasi tingkat aktivitas akun. Jika akun berstatus pasif selama 24 bulan berturut-turut, sistem mengirimkan notifikasi peringatan (*inactivity reminder*) sebelum poin hangus secara permanen.
- Eksekusi Otomatis: Command `avi:expire-holds` dan subrutin loyalti mengeksekusi penghapusan saldo poin kedaluwarsa secara batch dan menerbitkan jurnal penyesuaian akuntansi.


---

## 5. Kemitraan Konversi Poin Lintas Ekosistem (Cross-Industry Point Exchange)

### 5.1. Paradigma Pertukaran Nilai Lintas Industri
Program loyalitas aviasi modern tidak berdiri sendiri, melainkan bertindak sebagai jangkar (*anchor loyalty platform*) untuk ekosistem gaya hidup (*lifestyle & travel ecosystem*). Poin miles dapat dipertukarkan dengan mitra eksternal:
- **Perbankan & Kartu Kredit**: Konversi poin reward kartu kredit menjadi miles aviasi dengan rasio konversi tertentu (misal 5 reward points = 1 airline mile).
- **Jaringan Hotel & Resor**: Pertukaran dua arah poin loyalitas hotel menjadi miles aviasi, atau sebaliknya.
- **Retail & E-Commerce**: Penebusan miles untuk pembelian tiket bioskop, voucer belanja supermarket, atau saldo e-wallet.

### 5.2. Kontrak Pertukaran Poin & Penjaminan Solvabilitas
Untuk mencegah inflasi poin dan menjaga solvabilitas keuangan:
1. Rasio pertukaran (*exchange rate matrix*) ditentukan secara dinamis berdasarkan nilai tukar wajar per poin (*Fair Value per Point* / FVPP).
2. Setiap transaksi pertukaran antar-sistem mewajibkan pencatatan *real-time settlement escrow* untuk menjamin bahwa kewajiban poin yang diterbitkan diimbangi oleh deposit tunai mitra di rekening penampung.
3. Batas maksimum penukaran harian (*daily transaction caps*) diberlakukan untuk melindungi anggota dari ancaman pengambilalihan akun (*account takeover fraud*).
