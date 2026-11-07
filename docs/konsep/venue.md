# Konsep & Spesifikasi Domain Venue & Entertainment

Dokumen ini merupakan sumber kebenaran konsep bisnis, invarian teknis, dan permodelan domain ekosistem tempat hiburan (*Venue & Entertainment*) pada platform Superweb, diawali dengan **Fase 89: Ticketing, Access Control, Usia & Venue Operations**.

---

## 1. Fase 89 — Ticketing, Access Control, Usia & Venue Operations

### 1.1 Masalah Bisnis & Latar Belakang
Operasional tempat hiburan berskala menengah hingga besar (beach club di Canggu/Seminyak Bali, kelab malam SCBD Jakarta, *rooftop lounge*, dan arena festival musik luar ruangan) memiliki dinamika transaksi cepat dan sensitivitas tinggi terhadap keselamatan pengunjung:
1. **Risiko Keselamatan Kerumunan (*Crowd Safety & Crush Prevention*)**:
   Lonjakan pengunjung yang datang serentak (*surge*) sering kali memicu penumpukan berbahaya pada lorong masuk atau area panggung utama. Penjaga gerbang memerlukan sistem pembatasan laju masuk (*inflow rate limiter*) berbasis *token bucket* serta ambang batas kapasitas efektif dengan *safety margin* $5\%$ di bawah batas izin kepolisian.
2. **Kebocoran Pendapatan & Pemalsuan Tiket (*Ticket Passback & Replay*)**:
   Praktik membagikan tiket yang sama (*passback*) melalui tangkapan layar ponsel kepada rekan di luar gerbang menyebabkan kerugian finansial masif. Diperlukan mekanisme penegakan status bolak-balik (`IN` $\to$ `OUT` $\to$ `IN`), pembatasan *re-entry* maksimal 2 kali, dan verifikasi *nonce* anti-replay.
3. **Kepatuhan Batas Usia Legal & Perlindungan Data Pribadi (UU PDP No. 27/2022)**:
   Tempat hiburan dengan minuman beralkohol wajib menegakkan batas usia minimum 21 tahun sesuai Perda dan UU Cukai. Namun UU PDP melarang pemaparan atau penyimpanan data mentah tanggal lahir (*date of birth*) di terminal petugas lapangan. Verifikasi harus dilakukan melalui modul identitas KYC tanpa memaparkan data sensitif (zero-knowledge boolean flag).
4. **Kepastian Akuntansi & Risiko Pembatalan Acara (*Unearned Revenue & Weather Risk*)**:
   Uang hasil penjualan tiket konser/acara belum boleh diakui sebagai laba (*revenue*) sebelum acara terlaksana (prinsip akrual IFRS 15). Jika terjadi cuaca buruk atau izin kepolisian dicabut mendadak, sistem harus membatalkan acara melalui *saga* kompensasi terdistribusi: membalik unearned revenue, memulihkan deposit meja VIP, dan mengajukan klaim ke asuransi tersemat (G7-K15).

---

### 1.2 Aktor Operasional & Peran RBAC
- **`venue_manager`**:
  - Bertanggung jawab atas kalender acara, penerbitan kuota tier tiket, pengaturan denah meja VIP dan *minimum spend*, serta penutupan resmi acara (*event closing*) yang memicu pengakuan pendapatan.
  - Berwenang mengeksekusi saga pembatalan darurat saat terjadi *force majeure*.
- **`venue_staff`**:
  - Petugas pintu gerbang yang memindai kode QR tiket masuk/keluar tamu via perangkat bergerak (resolusi lebar 375 px) satu tangan.
  - Memverifikasi indikator lampu hijau/merah hasil pemindaian tanpa mengakses data pribadi tamu.
- **`crowd_safety`**:
  - Petugas pengendali kerumunan yang memantau heatmap kepadatan visual tiap zona, mengatur laju admisi per menit via *governor*, dan berwenang memicu penguncian gerbang (*lockdown*) atau jalur evakuasi darurat.
- **`compliance_officer`**:
  - Petugas kepatuhan hukum yang memvalidasi nomor izin keramaian gapless dari instansi kepolisian/pemda, memantau batas kapasitas legal, dan memastikan polis asuransi pembatalan acara aktif.
- **`customer`**:
  - Pengunjung yang membeli tiket melalui portal web, melakukan reservasi meja VIP dengan deposit escrow, serta mengakses kode QR tiket dinamis dari dompet digital mandiri.

---

### 1.3 Konsep Inti & Glosarium
- **Non-Fungible Digital Ticket**: Tiket digital berbasis hash kanonik `CanonicalHashChain` yang mengikat nomor seri gapless, ID event, ID pembeli, harga bayar, dan hash tiket sebelumnya.
- **Anti-Passback (APB)**: Aturan kontrol akses yang melarang sebuah tiket digunakan untuk masuk jika status tiket tersebut telah tercatat `IN` di dalam area. Tiket hanya dapat masuk kembali jika telah tercatat `OUT` dan kuota *re-entry* belum habis.
- **Token Bucket Admission**: Kendali laju aliran masuk pengunjung per menit untuk meredam gelombang kedatangan kerumunan di gerbang zona.
- **Minimum Spend**: Nilai komitmen belanja minimum yang disepakati pelanggan saat memesan meja VIP. Selisih antara minimum spend dan belanja riil menjadi pendapatan penalti/shortfall bagi venue.
- **Unearned Revenue Liability**: Akun kewajiban akuntansi (`ven:ticket_unearned:IDR` dan `ven:vip_unearned_rev:IDR`) tempat penampungan dana tiket/deposit hingga acara dinyatakan selesai.

---

### 1.4 Invarian Teknis Utama Fase 89
Setiap invarian berikut ditegakkan secara permanen di kode produksi:
1. **INV-89-01 (Pengakuan Pendapatan Akrual)**:
   Dana hasil penjualan tiket dan paket VIP wajib masuk ke akun kewajiban pendapatan diterima di muka (`ven:ticket_unearned:IDR` dan `ven:vip_unearned_rev:IDR`). Pengakuan ke akun pendapatan `ven:ticket_revenue:IDR` dan `ven:table_revenue:IDR` hanya boleh terjadi setelah status event mencapai `closed`.
   *Penegakan kode:* `Modules\Venue\Application\Actions\CloseEntertainmentEventAction::execute`
2. **INV-89-02 (Nomor Seri Tiket & Izin Gapless)**:
   Setiap tiket dan izin keramaian yang diterbitkan wajib memiliki nomor urut sekuensial tanpa jeda (*gapless*) yang dijamin oleh `DocumentNumberingInterface`.
   *Penegakan kode:* `Modules\Venue\Application\Actions\IssueTicketWithGaplessHashAction::execute`
3. **INV-89-03 (Pemberlakuan Anti-Passback Akses Dua Arah)**:
   Pindaian tiket dengan arah `IN` wajib ditolak apabila status pindaian tiket saat ini berada di dalam venue (`scanned_in`). Tiket hanya dapat masuk kembali setelah berstatus `scanned_out` dan jumlah re-entry $\le 2$.
   *Penegakan kode:* `Modules\Shared\Domain\Algorithms\Access\AntiPassbackGate::evaluateScan`
4. **INV-89-04 (Pencegahan Replay Nonce)**:
   Setiap pindaian gerbang menyertakan token kriptografis `nonce`. Pindaian ulang dengan `nonce` yang pernah tercatat wajib ditolak seketika (*anti-replay attack*).
   *Penegakan kode:* `Modules\Venue\Domain\Algorithms\GateScanNonceValidator::validate`
5. **INV-89-05 (Penyaringan Batas Usia Legal Tanpa PII)**:
   Tamu yang belum memenuhi batas usia legal venue ($\ge 21$ tahun untuk beach club/kelab malam) wajib ditolak pada gerbang masuk. Informasi yang diproses dan ditampilkan pada layar 375 px hanya status lulus verifikasi, tanpa tanggal lahir.
   *Penegakan kode:* `Modules\Venue\Application\Actions\ScanTicketGateWithAntiPassbackAndAgeCheckAction::execute`
6. **INV-89-06 (Penegakan Margin Keselamatan Kapasitas Zona)**:
   Admisi masuk ke zona venue dibatasi oleh kapasitas efektif yang dihitung dengan *safety margin* $5\%$ di bawah kapasitas legal izin: $\lfloor \text{legal} \times 0,95 \rfloor$. Jika okupansi mencapai kapasitas efektif, gerbang otomatis menolak admisi.
   *Penegakan kode:* `Modules\Venue\Domain\Algorithms\ZoneCapacityGovernor::calculateEffectiveCapacity`
7. **INV-89-07 (Laju Aliran Masuk Token Bucket)**:
   Jumlah pengunjung yang diizinkan masuk ke zona dalam satu jendela menit dibatasi oleh kapasitas token bucket ($R_{\text{rate}}$ per menit) guna mencegah penumpukan di pintu masuk.
   *Penegakan kode:* `Modules\Venue\Domain\Algorithms\ZoneCapacityGovernor::admitBatch`
8. **INV-89-08 (Penyelesaian Minimum Spend & No-Show Forfeiture)**:
   Pada reservasi meja VIP, tagihan akhir dihitung sebesar $\max(\text{belanja}, \text{minimum spend})$. Jika terjadi *no-show*, deposit escrow dipotong sebesar $50\%$ sebagai denda pembatalan (`ven:vip_noshow_rev:IDR`) dan sisa $50\%$ dikembalikan ke pelanggan.
   *Penegakan kode:* `Modules\Venue\Domain\Algorithms\MinimumSpendSettlement::calculateSettlement`
9. **INV-89-09 (Kompensasi Saga Pembatalan Terdistribusi)**:
   Pembatalan acara akibat cuaca ekstrem atau pencabutan izin wajib membatalkan seluruh tiket, mengembalikan kewajiban unearned tiket dan pajak PBJT, merilis deposit meja VIP, serta mengajukan klaim polis asuransi kerugian pembatalan via kontrak G7-K15.
   *Penegakan kode:* `Modules\Venue\Application\Sagas\EventCancellationSaga::execute`
10. **INV-89-10 (Audit Dua Sumber Sempurna ven:audit)**:
    Akumulasi saldo `ven:ticket_unearned:IDR` di buku besar wajib sama persis dengan total nominal tiket berstatus `issued` pada acara yang belum ditutup (`closed`).
    *Penegakan kode:* `Modules\Venue\Console\Commands\AuditVenueCommand::handle`

---

### 1.5 Aturan Bisnis & Sumber Regulasi
1. **Pajak Barang dan Jasa Tertentu (PBJT) — UU No. 1/2022 tentang HKPD**:
   - Berdasarkan Pasal 58 ayat (2) UU HKPD, tarif PBJT untuk jasa kesenian dan hiburan pada diskotik, karaoke, kelab malam, bar, dan uap/spa ditetapkan paling rendah $40\%$ dan paling tinggi $75\%$.
   - Pada simulasi ekosistem, tarif PBJT bar/kelab malam disetel pada $40\%$, sedangkan untuk konser/festival umum disetel pada tarif standar $10\%$.
   - Pajak dicatat sebagai kewajiban penampungan pemda pada akun `ven:entertainment_tax_payable:IDR` saat transaksi kas masuk.
   - Sumber: [DDTC News Regulasi PBJT](https://news.ddtc.co.id/mengapa-tarif-pajak-hiburan-dipatok-minimal-40-ini-kata-kemenkeu-1799901) & [JDIH Kemenkeu UU HKPD](https://kaltim.bpk.go.id/wp-content/uploads/2024/08/2024-01-17-Kemenkeu-Pemda-Boleh-Beri-Keringanan-Tarif-Pajak-Hiburan.pdf) (diakses 2026-11-07).
2. **Perlindungan Data Pribadi Biometrik & Usia — UU No. 27/2022**:
   - Pasal 4 ayat (2) UU PDP mengklasifikasikan data biometrik dan data anak sebagai data pribadi yang bersifat spesifik.
   - Pemrosesan data verifikasi usia wajib menerapkan prinsip minimisasi data: terminal gerbang lapangan tidak menyimpan rekaman KTP/paspor atau tanggal lahir mentah penonton.
   - Sumber: [UU No. 27 Tahun 2022 tentang PDP](https://pasal.id/peraturan/uu/uu-no-27-tahun-2022) (diakses 2026-11-07).
3. **Cukai Minuman Mengandung Etil Alkohol (MMEA) — PMK No. 160/2023**:
   - Golongan A (kadar $\le 5\%$): Rp 16.500/liter (dalam negeri & impor).
   - Golongan B (kadar $> 5\% - 20\%$): Rp 42.500/liter (dalam negeri), Rp 53.000/liter (impor).
   - Golongan C (kadar $> 20\% - 55\%$): Rp 101.000/liter (dalam negeri), Rp 152.000/liter (impor).
   - Sumber: [PMK 160/2023 JDIH Kemenkeu](https://jdih.kemenkeu.go.id/download/c4bdb413-ff08-4c21-9418-ab6b756aea46/2023pmkeuangan160abs.pdf) (diakses 2026-11-07).

---

### 1.6 Matriks Kepekaan Eksternal (T-5)
Sistem membaca faktor lingkungan eksternal melalui tabel terisolasi berversi `ven_external_factors`:

| Faktor Eksternal | Kunci Parameter | Label Parameter | Aturan Respon Sistem & Ambang | Jeda Waktu | Sumber Acuan |
|---|---|---|---|---|---|
| **Peluang Hujan Pesisir** | `weather.rain_probability_bps` | `asumsi_simulasi` | Jika peluang hujan $\ge 6.000\text{ bps}$ ($60\%$), harga tiket terpotong $-10\%$. Jika cuaca badai ekstrem ($>8.500\text{ bps}$), zona outdoor ditutup otomatis dan dialihkan ke indoor. | Real-time (tiap 15 menit) | BMKG Maritim Bali / Sensor Pesisir |
| **Pencabutan Izin Kepolisian** | `compliance.permit_status` | `regulasi` | Jika izin kepolisian dicabut sebelum acara, status event beralih ke `cancelled` seketika. Memicu Saga Pembatalan: refund tiket $100\%$ dan klaim asuransi pembatalan G7-K15. | Seketika saat status izin berubah | Intelkam Kepolisian Daerah Bali/Metro |
| **Tarif Pajak Daerah (PBJT)** | `tax.pbjt_rate_bps` | `regulasi` | Perubahan tarif PBJT hanya berlaku untuk transaksi tiket yang diterbitkan setelah tanggal efektif berlakunya perda baru. Transaksi masa lalu tidak disesuaikan mundur. | Tanggal efektif Perda Pemda | Lembaran Daerah / JDIH |
| **Batas Usia Legal Venue** | `compliance.min_age_years` | `regulasi` | Nilai minimum 21 tahun untuk tempat hiburan dengan penjualan alkohol. Pengunjung dengan usia $< 21$ tahun ditolak masuk gerbang. | Statis per venue type | Perda Ketertiban Umum & Izin Usaha |
| **Margin Keselamatan Kerumunan** | `safety.zone_margin_bps` | `asumsi_simulasi` | Margin keselamatan $500\text{ bps}$ ($5\%$) diterapkan untuk menghitung kapasitas efektif dari kapasitas legal fisik zona. | Statis per event | Standar Manajemen Kerumunan FIFA/NFPA |

---

### 1.7 Katalog Kasus Pengujian & Seeder T1 (15 Kasus Spesifik)
1. **CAS-89-01 (Early Bird Cerah)**: Penjualan tiket Early Bird pada hari H-35 dengan cuaca cerah ($P_{\text{rain}} = 2.000\text{ bps}$) dan permintaan $<50\%$. Harga terhitung dasar tier dengan diskon early bird.
2. **CAS-89-02 (GA Lonjakan Permintaan)**: Penjualan tiket General Admission dengan rasio terjual $8.500\text{ bps}$ ($85\%$) dan peluang hujan $2.000\text{ bps}$. Harga dasar Rp 350.000 naik $+20\%$ menjadi Rp 420.000.
3. **CAS-89-03 (Early Bird Hujan Lebat Floor Hit)**: Tiket Early Bird Rp 250.000 dengan peluang hujan $7.000\text{ bps}$ turun menjadi Rp 225.000, namun ditahan di batas floor artis Rp 280.000.
4. **CAS-89-04 (Door Price Lonjakan Ceiling Hit)**: Tiket Door Rp 450.000 pada permintaan $9.000\text{ bps}$ naik menjadi Rp 540.000, namun dibatasi oleh batas ceiling Rp 500.000.
5. **CAS-89-05 (Admisi Usia Dewasa Valid)**: Tamu berusia 22 tahun memindai tiket valid pada pintu masuk kelab malam Atlas Canggu. Hasil: DITERIMA (Lampu Hijau, status tiket menjadi `scanned_in`).
6. **CAS-89-06 (Batas Kritis Tepat 21 Tahun)**: Tamu yang berulang tahun ke-21 tepat pada hari acara memindai tiket. Hasil: DITERIMA (Lampu Hijau, verifikasi batas umur lulus).
7. **CAS-89-07 (Penolakan Bawah Umur)**: Tamu berusia 20 tahun 364 hari mencoba memindai tiket masuk kelab malam. Hasil: DITOLAK (Lampu Merah, alasan usia belum mencukupi tanpa memaparkan tanggal lahir).
8. **CAS-89-08 (Penolakan Passback Ganda)**: Tamu yang telah berada di dalam venue memberikan tiketnya ke rekan di luar gerbang. Rekan memindai tiket dengan status `scanned_in`. Hasil: DITOLAK (Pelanggaran Anti-Passback).
9. **CAS-89-09 (Re-Entry Keluar Masuk Sah)**: Tamu memindai keluar (`scanned_out`) pada pukul 21:00, lalu kembali memindai masuk pada 21:10. Hasil: DITERIMA (Re-entry ke-1 dari batas maksimum 2 kali).
10. **CAS-89-10 (Penolakan Melebihi Batas Re-Entry)**: Tamu yang telah melakukan keluar-masuk sebanyak 2 kali mencoba masuk untuk ke-3 kalinya. Hasil: DITOLAK (Batas re-entry terlampaui).
11. **CAS-89-11 (Penolakan Replay Attack Nonce)**: Penyerang mencoba memindai ulang tangkapan layar kode QR yang sama persis dalam selang waktu 1 detik. Hasil: DITOLAK (Replay nonce terdeteksi).
12. **CAS-89-12 (Pencegahan Overkapasitas Zona Dance Floor)**: Zona dengan kapasitas legal 500 dan kapasitas efektif 475 orang telah terisi 475 orang. Tamu ke-476 mencoba masuk. Hasil: DITOLAK (Zona mencapai kapasitas maksimum).
13. **CAS-89-13 (Penyelesaian Meja VIP Surplus Belanja)**: Meja VIP minimum spend Rp 5.000.000, deposit Rp 2.000.000, belanja makanan/minuman Rp 6.300.000. Hasil: Deposit di-capture Rp 2.000.000, tagihan tambahan Rp 4.300.000 dibebankan.
14. **CAS-89-14 (Penyelesaian Meja VIP Shortfall Revenue)**: Meja VIP minimum spend Rp 5.000.000, deposit Rp 2.000.000, belanja Rp 3.200.000. Hasil: Tagihan Rp 5.000.000 (F&B Rp 3.200.000 + Shortfall Rp 1.800.000).
15. **CAS-89-15 (Kejutan Cuaca Buruk & Evakuasi Pantai)**: Badai pesisir mendadak menutup zona pantai. Tiket zona pantai dialihkan ke zona indoor oleh Venue Manager, atau dibatalkan sebagian dengan pengembalian dana prorata.

---

### 1.8 Arsitektur Kriptografi Tiket & Pencegahan Pemalsuan
Tiket digital venue dirancang dengan standar anti-pemalsuan tinggi menggunakan kombinasi *canonical hash-chaining* dan tanda tangan HMAC:
1. **Struktur Data Blok Tiket**:
   Setiap blok tiket menyimpan payload berurutan:
   $$\text{Block}_k = (\text{ticket\_number}, \text{event\_id}, \text{buyer\_user\_id}, \text{price\_paid}, \text{tax\_paid}, H_{k-1})$$
2. **Kalkulasi Hash Kanonik**:
   $$H_k = \text{SHA-256}(\text{Block}_k)$$
   Dengan nilai benih mula-mula $H_0 = \text{SHA-256}(\text{event\_code} \parallel \text{doors\_open\_at})$.
3. **Pemberian Tanda Tangan QR Dinamis**:
   Untuk mencegah penggunaan tangkapan layar statis, kode QR yang dirender pada portal tamu disegarkan setiap 30 detik dengan format:
   $$\text{QR} = \text{ticket\_uuid} \parallel \text{nonce} \parallel \text{timestamp} \parallel \text{HMAC-SHA256}(\dots, K_{\text{venue}})$$
   Terminal pemindai di gerbang memverifikasi tanda tangan ini dalam waktu $<5\text{ ms}$ sebelum mengevaluasi aturan bisnis anti-passback.

---

### 1.9 Model Skalabilitas T1 & Partisi Data Transaksional
Pada tingkat skala produksi penuh (Tier 2/T2):
- Basis data menampung 1.000 venue global dan 50 juta tiket per tahun.
- Tabel `ven_entertainment_tickets` dan `ven_gate_scans` dirancang untuk mendukung partisi berbasis jangkauan (*range partitioning*) berdasarkan `event_id` dan bulan pelaksanaan.
- Seluruh query pencarian tiket gerbang menggunakan kunci gabungan `['event_id', 'ticket_uuid']` yang dipetakan langsung ke partisi fisik yang aktif, menjamin latensi query tetap berada di bawah $2\text{ ms}$ pada volume konkurensi $10.000\text{ req/detik}$.

---

### 1.10 Penanganan Kegagalan Terdistribusi & Prinsip Idempotensi
Semua operasi mutasi finansial (penjualan tiket, pemesanan meja VIP, penutupan acara, pembatalan darurat) diwajibkan menyertakan header `Idempotency-Key`:
- Format kunci: `{domain}:{aksi}:{id_entitas}[:{sub_entitas}]`.
- Contoh: `ven:ticket_purchase:EVT-01:USR-1029`, `ven:table_hold:EVT-01:CABANA-01`.
- Jika request dengan kunci yang sama dan payload identik diterima kembali, sistem mengembalikan representasi hasil yang telah tersimpan tanpa memicu mutasi basis data ulang (*idempotent replay*).
- Jika request dengan kunci yang sama namun membawa nilai payload berbeda (misalnya harga atau nama tamu berbeda), sistem seketika melempar eksepsi `IdempotencyConflictException` (HTTP 409).
