# Spesifikasi Teknis Valuasi Eksposur Sponsor & KPI Gerbang Turnstile (Fase 90)

## 1. Ikhtisar Arsitektur & Latar Belakang Kemitraan Merek
Kemitraan sponsor (*brand activation & sponsorship*) merupakan pilar monetisasi penting dalam penyelenggaraan acara festival musik, festival pesisir, dan aktivasi musiman di beach club berskala enterprise. Sponsor korporat tidak lagi menerima laporan eksposur perkiraan (*rough estimation*), melainkan menuntut pembuktian kinerja berbasis data telemetri aktual (*proof-of-performance telemetry*).

Modul Venue mengintegrasikan data lalu lintas gerbang turnstile dua arah (yang diverifikasi pada Fase 89 via anti-passback dan token bucket zone governor) dengan log rotasi penayangan layar LED panggung untuk menghasilkan valuasi eksposur terbobot (*weighted impressions*) secara real-time melalui algoritma bersertifikat **V8 `SponsorExposureValuator`**.

---

## 2. Algoritma V8: SponsorExposureValuator

### 2.1 Formula Matematis Impresi Terbobot (*Weighted Impressions*)
Setiap zona hiburan di dalam venue memiliki karakteristik dwell time (lama waktu tinggal rata-rata pengunjung) dan tingkat atensi visual yang berbeda terhadap materi promosi merek:

$$\text{Zone Impression}_z = \left\lfloor \frac{\text{Footfall}_z \times \text{Dwell Weight Bps}_z}{10.000} \right\rfloor \times \frac{\text{Screen Share Bps}_z}{10.000}$$
$$\text{Total Delivered Impressions} = \sum_{z \in \text{Zones}} \text{Zone Impression}_z$$

Di mana:
- **Footfall ($z$):** Jumlah pemindaian pengunjung masuk terverifikasi pada gerbang turnstile zona $z$.
- **Dwell Weight Bps ($z$):** Bobot durasi tinggal pengunjung dalam basis poin (di mana 10.000 bps = 1.0x bobot dasar):
  - Area Panggung Utama (*Main Stage Crowd*): Dwell weight 10.000 bps (1.0x).
  - Pesisir Pantai (*Beachfront Deck*): Dwell weight 6.000 bps (0.6x, pergerakan pengunjung lebih dinamis).
  - Meja VIP Lounge (*VIP Cabana Lounge*): Dwell weight 25.000 bps (2.5x, pengunjung menetap selama 4–6 jam dengan visibilitas tinggi).
- **Screen Share Bps ($z$):** Rasio slot rotasi penayangan konten video/logo sponsor pada videotron/layar LED zona tersebut dalam rentang 1 jam (misal 2.000 bps = 20% slot waktu tayang).

### 2.2 Valuasi Eksposur Moneter Ekuivalen (Equivalent CPM Value)
Untuk memberikan laporan perbandingan terhadap belanja iklan digital (*programmatic ad comparison*), total impresi terbobot dikonversi ke nilai moneter berdasarkan tarif *Cost Per Mille* (CPM) yang disepakati:

$$\text{Exposure Value IDR} = \left\lfloor \frac{\text{Total Delivered Impressions}}{1.000} \right\rfloor \times \text{CPM Rate IDR}$$

### 2.3 Evaluasi Pencapaian KPI & Rasio Pembayaran Termin (*Milestone Payout Ratio*)
Kontrak sponsor distrukturkan ke dalam beberapa termin pembayaran milestone (misalnya uang muka tanda tangan kontrak, termin pra-acara, dan pelunasan pasca-acara). Pencairan termin pelunasan pasca-acara secara kontraktual bergantung pada persentase pencapaian target impresi yang dijanjikan:

$$\text{Achievement Bps} = \left\lfloor \frac{\text{Total Delivered Impressions} \times 10.000}{\text{Contract Target Impressions}} \right\rfloor$$

Aturan penentuan rasio pencairan (*Payout Ratio*):
1. **Pencapaian Penuh ($\text{Achievement Bps} \ge 10.000$ / $\ge 100\%$):**
   Sponsor mencairkan 100% anggaran termin milestone tanpa potongan.
   $$\text{Payout Ratio Bps} = 10.000$$
2. **Pencapaian Sebagian yang Ditoleransi ($7.000 \le \text{Achievement Bps} < 10.000$ / $70\% \text{ s/d } 99\%$):**
   Penyelenggara berhak atas pembayaran pro-rata proporsional sesuai rasio capaian aktual untuk menghindari penalti total akibat kondisi luar biasa (*pro-rata settlement*).
   $$\text{Payout Ratio Bps} = \text{Achievement Bps}$$
3. **Capaian di Bawah Ambang Kritis ($\text{Achievement Bps} < 7.000$ / $< 70\%$):**
   Penyelenggara dianggap wanprestasi dalam pengiriman target eksposur (*under-delivery default*), sehingga pembayaran termin milestone ditahan penuh (0%) atau dialihkan ke kompensasi kredit acara berikutnya.
   $$\text{Payout Ratio Bps} = 0$$

$$\text{Final Milestone Payout IDR} = \left\lfloor \frac{\text{Milestone Budget IDR} \times \text{Payout Ratio Bps}}{10.000} \right\rfloor$$

---

## 3. Skema Data & Kontrak Integrasi

### 3.1 Skema Tabel Basis Data (Prefiks `ven_`)
```sql
CREATE TABLE ven_sponsor_deals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    sponsor_name VARCHAR(128) NOT NULL,
    brand_category VARCHAR(64) NOT NULL,
    contract_value_idr BIGINT NOT NULL,
    target_impressions BIGINT NOT NULL,
    delivered_impressions BIGINT NOT NULL DEFAULT 0,
    cpm_rate_idr BIGINT NOT NULL DEFAULT 50000,
    status VARCHAR(32) NOT NULL DEFAULT 'proposed',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (event_id) REFERENCES ven_entertainment_events(id) ON DELETE CASCADE,
    INDEX (event_id, status)
);

CREATE TABLE ven_sponsor_milestones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deal_id BIGINT UNSIGNED NOT NULL,
    milestone_name VARCHAR(128) NOT NULL,
    due_date DATE NOT NULL,
    target_impressions BIGINT NOT NULL,
    delivered_impressions BIGINT NOT NULL DEFAULT 0,
    payout_ratio_bps INT NOT NULL DEFAULT 0,
    milestone_budget_idr BIGINT NOT NULL,
    milestone_payout_idr BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (deal_id) REFERENCES ven_sponsor_deals(id) ON DELETE CASCADE,
    INDEX (deal_id, status)
);
```

### 3.2 Siklus Hidup Kesepakatan Sponsor (`SponsorDealStateMachine`)
Siklus hidup kontrak sponsor diatur melalui state machine eksplisit dengan 7 status transisi sah:
- `proposed`: Proposal sponsor diajukan oleh tim komersial venue.
- `signed`: Kontrak ditandatangani oleh kedua belah pihak dengan hak kategori eksklusif.
- `activated`: Materi promosi, branding gelang, dan penempatan backdrop aktif di lapangan.
- `milestone_due`: Waktu jatuh tempo evaluasi pengiriman eksposur tercapai.
- `invoiced`: Sertifikat komputasi V8 terbit dan faktur tagihan diterbitkan ke sponsor.
- `paid`: Pembayaran termin kas diterima dan piutang sponsor dilunasi di buku besar.
- `closed`: Seluruh kewajiban kontrak selesai dan acara dinyatakan tutup secara formal.
- `disputed`: Sengketa timbul akibat ketidaksesuaian penempatan materi promosi.

---

## 4. Jurnal Pembukuan Piutang & Pendapatan Sponsor
1. **Saat Tagihan Termin Milestone Diterbitkan (`invoiced`):**
   - Debit: `ven:sponsor_receivable:IDR` (Piutang Sponsor)
   - Kredit: `ven:sponsorship_revenue:IDR` (Pendapatan Sponsorship Definitif)
2. **Saat Pembayaran Diterima dari Sponsor:**
   - Debit: `ven:ticket_clearing:IDR` (Rekening Bank Penerimaan)
   - Kredit: `ven:sponsor_receivable:IDR` (Pelunasan Saldo Piutang)
3. **Rekonsiliasi Konsistensi Dua Sumber (`venue:audit-economy`):**
   Perintah audit memvalidasi bahwa $\sum \text{Milestone Invoiced} = \sum \text{Debit Piutang Sponsor}$ dan memastikan rasio payout yang diakui tidak pernah melampaui sertifikat algoritma V8 `SponsorExposureCertificate`.
