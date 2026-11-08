# ADR-0114: Arsitektur MICE, Banquet BEO, dan Engine Penjualan Acara Global

## Status
Diterima (Accepted)

## Konteks
Penyelenggaraan acara skala besar (MICE — *Meetings, Incentives, Conferences, and Exhibitions*) serta pesta pernikahan mewah (*luxury weddings*) menyumbang antara 35% hingga 55% dari total pendapatan kotor properti hotel bintang 4 dan 5. Namun, proses operasional dan finansial untuk segmen ini memiliki kompleksitas yang jauh lebih tinggi dibandingkan reservasi kamar individu biasa (Transient FIT):
1. **Ketidakpastian Okupansi Kamar Blok (*Room Block Wastage*):** Penyelenggara acara sering kali memesan ratusan kamar secara tentatif, namun terlambat mengumpulkan nama peserta (*rooming list*). Jika kamar ditahan terlalu lama hingga hari H, hotel menanggung risiko kerugian inventaris kamar kosong (*spoilage*). Sebaliknya, jika dilepas terlalu cepat, klien korporat akan kecewa.
2. **Volatilitas Biaya Jamuan (*Banquet Cost Creep*):** Perhitungan biaya makanan dan minuman (*food and beverage BOM*) sering meleset akibat fluktuasi harga bahan baku segar, tingkat susut pemotongan (*trimming loss*), dan varians penuangan minuman beralkohol (*pouring shrinkage*). Tanpa kontrol margin ketat, hotel dapat merugi pada acara berskala ratusan juta rupiah.
3. **Risiko Gagal Bayar Kontrak Besar:** Tagihan MICE dan pernikahan sering melibatkan nilai ratusan juta hingga miliaran rupiah. Penjadwalan termin pembayaran (*milestone schedules*) yang longgar sering menyebabkan kredit macet (*uncollectible receivables*) saat acara telah usai.
4. **Koordinasi Multi-Disiplin yang Terfragmentasi:** Tim Banquet, Kitchen/Chef, Housekeeping, AV Engineering, dan Front Desk sering bekerja dengan dokumen kertas terpisah yang tidak sinkron secara *real-time*.

## Keputusan Arsitektur

### 1. Pola Mesin Pelepasan Kamar Blok Bertingkat (Staged Block Allotment Engine)
Menerapkan algoritma bersertifikat `BlockAllotmentReleaser` yang dijalankan secara terjadwal setiap hari pada siklus *Night Audit* atau secara on-demand:
- **Cutoff Tier 1 (H-30):** Memeriksa persentase *pickup* kamar. Bila kuota terisi masih di bawah ambang komitmen minimum, sistem menghitung sisa kamar un-picked dan melepaskan porsi persentase yang disepakati (default 25%) kembali ke ketersediaan publik (*Central Reservation System/CRS*).
- **Cutoff Tier 2 (H-14):** Melepaskan kuota tambahan (default 50% dari sisa yang belum terisi).
- **Cutoff Tier 3 (H-7):** Melepaskan 100% sisa kamar blok yang belum diisi data tamu definitif.
- Setiap pelepasan menghasilkan sertifikat matematis `BlockAllotmentReleaseCertificate` yang mencatat jumlah kamar yang dikembalikan, kuota tersisa, dan hash integritas transaksi.

### 2. Banquet Event Order (BEO) & Recipe BOM Explosion Engine
Menerapkan algoritma bersertifikat `BanquetBomExploder`:
- Menerima struktur menu banquet (buffet/set-menu), jumlah tamu (*guaranteed pax* + safety margin 5%), dan komponen resep per menu.
- Menghitung kebutuhan bahan mentah gramatur, memperhitungkan faktor kehilangan hasil olahan (*cooking yield coefficient*), varians penuangan minuman botol (*pour shrinkage percentage*), dan mengkalkulasi kebutuhan jam tenaga kerja juru masak dan staf banquet.
- Mengirimkan instruksi pemotongan batch stok bahan baku langsung ke modul Resto dan Procurement secara atomik.

### 3. Guardrail Margin Kotor Event (Event Margin Calculator & Guardrail)
Menerapkan algoritma bersertifikat `EventMarginCalculator`:
- Menghitung seluruh pos pendapatan: sewa ruang ballroom, pendapatan F&B banquet, sewa peralatan AV & lighting, penjualan utilitas booth expo, dan komisi kamar blok.
- Menghitung seluruh pos pengeluaran langsung: biaya bahan makanan mentah (Food COGS), biaya minuman (Beverage COGS), upah harian pekerja kasual (Banquet Casual Labor), sewa alat dari vendor pihak ketiga, dan biaya listrik utilitas tinggi.
- Margin kotor dihitung dengan rumus:
  $$\text{Gross Margin} = \frac{\text{Total Revenue} - \text{Total Direct Costs}}{\text{Total Revenue}} \times 100\%$$
- **Guardrail Rule:** Jika $\text{Gross Margin} < 40\%$, sistem melarang penerbitan quotation final dan memblokir penandatanganan SPK kecuali terdapat otorisasi override dari General Manager dengan audit trail alasan bisnis.

### 4. Milestone Schedule Builder untuk Kontrak Wedding & Konferensi
Menerapkan algoritma bersertifikat `MilestoneScheduleBuilder`:
- Membagi total nilai kontrak ke dalam 4 termin standar: 20% Deposit, 30% Termin I, 30% Termin II, dan 20% Pelunasan.
- Menyesuaikan tanggal jatuh tempo termin secara dinamis berdasarkan sisa waktu antara penandatanganan dan tanggal acara.
- Mengunci status acara agar tidak dapat masuk ke tahap eksekusi operasional (*production stage*) sebelum termin minimum H-7 (100% pelunasan) terlunasi atau dijamin oleh garansi bank korporat terverifikasi.

## Konsekuensi

### Positif:
- **Optimalisasi RevPAR dan Okupansi:** Pelepasan bertingkat kamar blok mencegah kamar kosong yang tidak terjual pada hari-hari menjelang kedatangan delegasi.
- **Kepastian Profitabilitas:** Penegakan guardrail 40% margin kotor menjamin bahwa setiap acara yang diselenggarakan hotel menghasilkan laba operasi bersih yang sehat.
- **Disiplin Arus Kas:** Jadwal termin berjenjang memitigasi risiko piutang tak tertagih dari klien korporat maupun perorangan.
- **Konsistensi Operasional Kitchen:** Lembar kerja BEO otomatis mengurangi pemborosan makanan (*food waste*) hingga lebih dari 20% melalui standardisasi porsi dan estimasi gramatur terkalibrasi.

### Mitigasi Risiko:
- **Kekecewaan Klien atas Pelepasan Kamar:** Sistem mengirimkan notifikasi peringatan otomatis pada H-35, H-16, dan H-9 kepada Event Organizer sebelum eksekusi pemotongan kuota dilakukan.
- **Kenaikan Biaya Tak Terduga Saat Hari H:** Folio BEO mendukung penambahan lini biaya tak terduga (*incidentals & excess pax charge*) yang dapat diselesaikan pada saat penutupan master folio pasca-acara.
