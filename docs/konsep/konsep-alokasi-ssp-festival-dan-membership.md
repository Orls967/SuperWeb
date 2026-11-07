# Konsep Standar Akuntansi IFRS 15 / PSAK 72 Alokasi SSP Festival & Arsitektur Membership Tiering (Fase 90)

## 1. Landasan Normatif & Prinsip Akuntansi IFRS 15 / PSAK 72
Dalam industri hiburan dan festival berskala multi-hari, penyelenggara sering kali menjual paket bundling gabungan (*staycation festival package*) yang mencakup beberapa barang dan jasa yang disediakan oleh entitas hukum terpisah:
1. Tiket Masuk Acara & Gelang RFID Cashless (disediakan oleh Penyelenggara Venue).
2. Akomodasi Kamar Hotel / Tenda Glamping Mewah (disediakan oleh Hotel Rekanan via Kontrak Aditif G7-K16 `RoomBlockReserver`).
3. Kredit Makanan & Minuman di Tenant Resto (disediakan oleh Mitra F&B via Kontrak G7-K17 `OutletSaleRecorder`).
4. Layanan Transportasi Antar-Jemput VIP Shuttle Bus (disediakan oleh Mitra Logistik).

Sesuai ketentuan **IFRS 15 / PSAK 72: Pendapatan dari Kontrak dengan Pelanggan**, jika sebuah kontrak penjualan mengandung lebih dari satu kewajiban pelaksanaan (*multiple performance obligations*), entitas penjual wajib mengalokasikan harga transaksi (*transaction price*) ke setiap kewajiban pelaksanaan berdasarkan perbandingan **Harga Jual Berdiri Sendiri (*Standalone Selling Price* / SSP)** relatif dari masing-masing barang atau jasa pada tanggal permulaan kontrak.

---

## 2. Algoritma V6: BundleSspAllocator (Hamilton-Hare Largest Remainder Method)

### 2.1 Masalah Pembulatan Moneter Integer
Ketika harga paket diskon dialokasikan ke masing-masing komponen berdasarkan proporsi rasional:

$$\text{Quota}_i = \frac{\text{Bundle Price} \times \text{SSP}_i}{\sum_{j=1}^n \text{SSP}_j}$$

Pembagian tersebut hampir selalu menghasilkan nilai pecahan (*fraksi cent / rupiah*). Di dalam sistem akuntansi buku besar yang menolak angka desimal/float (aturan keras G7-D32 dan arch:scan A3), pembulatan matematis biasa (`round`, `floor`, atau `ceil`) akan memicu deviasi pembulatan:
- Bila semua komponen dibulatkan ke bawah (`floor`), total alokasi akan lebih kecil dari harga paket yang dibayar konsumen ($\sum \text{Allocated}_i < \text{Bundle Price}$), menciptakan selisih pembukuan yang tidak seimbang (*unbalanced journal error*).
- Bila sebagian komponen dibulatkan ke atas (`ceil`), total alokasi dapat melebihi kas yang diterima ($\sum \text{Allocated}_i > \text{Bundle Price}$), menciptakan pendapatan semu yang melanggar prinsip kehati-hatian.

### 2.2 Formulasi Matematika Hamilton-Hare Tanpa Desimal
Untuk mengatasi anomali tersebut, algoritma **V6 `BundleSspAllocator`** mengimplementasikan metode kuota sisa terbesar (*Hamilton-Hare Largest Remainder Method*) secara integer penuh:

1. **Hitung Kuota Integer Dasar:**
   $$\text{BaseAlloc}_i = \left\lfloor \frac{\text{Bundle Price} \times \text{SSP}_i}{\text{Total SSP}} \right\rfloor$$
2. **Hitung Sisa Modulo Fraksional (Remainder):**
   $$\text{Remainder}_i = (\text{Bundle Price} \times \text{SSP}_i) \pmod{\text{Total SSP}}$$
3. **Hitung Residu Alokasi yang Tersisa:**
   $$\text{Residual} = \text{Bundle Price} - \sum_{i=1}^n \text{BaseAlloc}_i$$
   Di mana $0 \le \text{Residual} < n$ (di mana $n$ adalah jumlah komponen paket).
4. **Distribusi Residu Deterministik:**
   Seluruh komponen diurutkan menurun berdasarkan nilai `Remainder` (dengan tie-break: nilai SSP lebih besar, lalu urutan indeks asal). Sebanyak 1 Rupiah dialokasikan ke komponen teratas hingga seluruh `Residual` habis.
5. **Kalkulasi Potongan Platform & Netto Vendor:**
   $$\text{Platform Fee}_i = \left\lfloor \frac{\text{Allocated}_i \times \text{Platform Fee Bps}}{10.000} \right\rfloor$$
   $$\text{Vendor Net}_i = \text{Allocated}_i - \text{Platform Fee}_i$$

### 2.3 Pembuktian Invarian Konservasi Total
Algoritma V6 menjamin terpenuhinya dua invarian matematika secara absolut pada setiap eksekusi:
$$\sum_{i=1}^n \text{Allocated}_i = \text{Bundle Price}$$
$$\sum_{i=1}^n (\text{Vendor Net}_i + \text{Platform Fee}_i) = \text{Bundle Price}$$

Sertifikat komputasi `BundleSspCertificate` membawa hash HMAC-SHA256 dari seluruh rincian alokasi dan divalidasi oleh `BundleSspVerifier` sebelum transaksi dicatat ke basis data.

---

## 3. Buku Besar Akrual Paket Festival Multi-Vendor (D90-8)

### 3.1 Siklus Hidup Pembukuan Pendapatan Diterima di Muka
Penerimaan kas dari penjualan paket festival mula-mula diakui sebagai kewajiban pendapatan diterima di muka (*unearned liability*) dan baru diamortisasi ke pendapatan definitif atau hutang vendor saat masing-masing komponen dinikmati/ditukarkan oleh tamu:

```
[1. Pembelian Paket oleh Tamu]
Debit:  ven:ticket_clearing:IDR       (Kas/Payment Gateway)      Rp 2.999.999
Kredit: ven:bundle_unearned:IDR       (Kewajiban Belum Terpenuhi) Rp 2.999.999

[2. Tamu Check-in Kamar Hotel Mitra (Komponen Glamping K16 Ditukarkan)]
Debit:  ven:bundle_unearned:IDR       (Pembalikan Sebagian Kewajiban) Rp 1.125.000
Kredit: ven:organizer_payable:IDR     (Hutang Bersih Mitra Hotel)   Rp 1.068.750
Kredit: ven:platform_fee_revenue:IDR  (Pendapatan Komisi 5% Venue)  Rp    56.250

[3. Tamu Menukarkan Saldo F&B di Tenant Resto Pesisir]
Debit:  ven:bundle_unearned:IDR       (Pembalikan Sebagian Kewajiban) Rp   300.000
Kredit: ven:organizer_payable:IDR     (Hutang Bersih Mitra Resto)   Rp   285.000
Kredit: ven:platform_fee_revenue:IDR  (Pendapatan Komisi 5% Venue)  Rp    15.000

[4. Penutupan Acara Festival (Komponen Tiket Sendiri Diakui)]
Debit:  ven:bundle_unearned:IDR       (Pembalikan Sisa Kewajiban)   Rp 1.574.999
Kredit: ven:ticket_revenue:IDR        (Pendapatan Tiket Definitif)  Rp 1.574.999
```

---

## 4. Arsitektur Evaluasi Keanggotaan (Membership Tier Engine V7)

### 4.1 Definisi Tingkat Keanggotaan & Kriteria Kualifikasi
Beach club dan kelab malam menerapkan skema tiering eksklusif untuk mendorong retensi dan loyalitas tamu berdaya beli tinggi:

1. **Sun (Tingkat Dasar):**
   - Kualifikasi: Terdaftar secara sah di portal tamu (*default tier*).
   - Hak Istimewa: Poin loyalitas standar 1.0x, akses reservasi meja daring umum.
2. **Moon (Tingkat Menengah):**
   - Kualifikasi: Total belanja 12 bulan bergulir $\ge$ Rp 20.000.000 **ATAU** frekuensi kunjungan terverifikasi turnstile $\ge 12$ kali.
   - Hak Istimewa: Diskon tagihan 15%, multiplier poin loyalitas 1.5x, prioritas reservasi meja sunset pool deck.
3. **Infinity (Tingkat Tertinggi / Founders Club):**
   - Kualifikasi: Total belanja 12 bulan bergulir $\ge$ Rp 75.000.000 **DAN** frekuensi kunjungan terverifikasi turnstile $\ge 24$ kali.
   - Hak Istimewa: Diskon tagihan 20%, multiplier poin loyalitas 2.0x, akses *VIP fast track line bypass* tanpa antre di seluruh turnstile, dan hak kepemilikan kompartemen loker botol pribadi (*bottle storage locker*).

### 4.2 Perlindungan Penurunan Tingkat (Downgrade Grace Period)
Untuk melindungi hubungan baik dengan pelanggan premium (*guest sentiment protection*), penurunan tingkat (*downgrade*) tidak boleh terjadi secara mendadak di tengah tahun berjalan:
- **Prinsip Evaluasi Ulang Tahunan (*Anniversary Evaluation*):** Status keanggotaan hanya dievaluasi ulang pada tanggal ulang tahun keanggotaan (*anniversary date*).
- **Aturan Masa Tenggang 80% (*80% Threshold Grace Period*):** Jika pada tanggal evaluasi ulang seorang anggota tier `infinity` belum mencapai target Rp 75.000.000 namun telah membelanjakan minimal 80% dari batas ambang (yakni $\ge$ Rp 60.000.000), sistem secara otomatis memberikan status `grace` selama 30 hari kalender.
- **Batas Maksimal Penurunan (*Single-Step Downgrade Policy*):** Bila masa tenggang 30 hari berakhir tanpa pemenuhan target, tier anggota hanya diturunkan maksimal 1 tingkat ke bawah (`infinity` turun ke `moon`, `moon` turun ke `sun`). Anggota tidak pernah dijatuhkan langsung dari tier `infinity` ke `sun`.

### 4.3 Tata Kelola Loker Botol Penyimpanan VIP (*Bottle Storage Locker*)
- Kompartemen loker botol penyimpanan VIP di area lounge bersifat terbatas dan dialokasikan secara unik (*one-to-one mapping*).
- Sistem menegakkan aturan validasi ketat: nomor loker botol hanya boleh diterbitkan jika status tier anggota terverifikasi sebagai `infinity`.
- Setiap botol yang dititipkan dicatat kode SKU, sisa volume cairan, tanggal penitipan, dan segel tamper-evident. Jika status keanggotaan ditangguhkan (*suspended* atau *downgraded*), tamu diberikan tenggat waktu 14 hari untuk mengambil atau mengonsumsi botol yang tersisa sebelum loker dialokasikan ke anggota lain.
