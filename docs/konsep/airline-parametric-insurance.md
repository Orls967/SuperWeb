# Arsitektur Asuransi Perjalanan Parametrik untuk Aviasi Komersial (Parametric Flight Insurance)

## 1. Paradigma Asuransi Parametrik vs Asuransi Konvensional

Asuransi perjalanan konvensional (*indemnity-based insurance*) memiliki sejumlah kelemahan operasional yang merugikan baik bagi konsumen maupun bagi perusahaan asuransi (*underwriter*):
1. **Beban Pembuktian di Pihak Penumpang**: Penumpang harus meminta surat keterangan keterlambatan (*flight delay certificate*) dari konter layanan maskapai di bandara yang seringkali antre panjang.
2. **Friksi Pengajuan Klaim**: Penumpang wajib mengisi formulir klaim berlembar-lembar, mengunggah bukti paspor, boarding pass, dan kwitansi pengeluaran.
3. **Biaya Pemrosesan Klaim Tinggi (*High Loss Adjustment Expense*)**: Pihak asuransi harus mempekerjakan tim penilai (*loss adjusters*) untuk memverifikasi keabsahan setiap klaim bernilai kecil (micro-claims).
4. **Waktu Pencairan Berbelit**: Pembayaran ganti rugi seringkali membutuhkan waktu berminggu-minggu setelah perjalanan berakhir.

**Asuransi Parametrik (*Parametric Travel Insurance*)** mentransformasikan seluruh paradigma ini melalui otomasi berbasis data obyektif:
- Polis tidak mengganti kerugian aktual yang dialami, melainkan membayarkan santunan dalam jumlah yang telah ditetapkan (*pre-agreed sum insured*) segera setelah **pemicu obyektif (*objective trigger event*)** terlampaui.
- Pemicu tidak dapat dimanipulasi oleh salah satu pihak karena bersumber dari data telemetri publik dan otoritas resmi (*oracle / independent data source*).
- Klaim dicairkan secara instan (*instant zero-touch payout*) ke rekening bank atau dompet digital (*e-wallet*) penumpang tanpa perlu pelaporan klaim manual.

---

## 2. Definisi Pemicu Parametrik & Sumber Data Telemetri (Data Oracle)

### 2.1. Taksonomi Pemicu Terverifikasi
Kontrak G7-K29 `Modules\Insurance\Contracts\ParametricTriggerRecorder` mendefinisikan pemicu parametrik deterministik:
1. `DELAY_OVER_60MIN`: Keterlambatan keberangkatan atau kedatangan $\ge 60$ menit (Santunan voucher mikro).
2. `DELAY_OVER_120MIN`: Keterlambatan $\ge 120$ menit (Santunan tier menengah, e.g., Rp250.000,-).
3. `DELAY_OVER_180MIN`: Keterlambatan $\ge 180$ menit (Santunan tier tinggi, e.g., Rp500.000,-).
4. `FLIGHT_CANCELLED`: Pembatalan penerbangan resmi sebelum atau sesudah check-in (Santunan penuh, e.g., Rp1.000.000,-).
5. `VOLCANIC_ERUPTION_CLOSURE`: Penutupan bandara akibat abu vulkanik berstatus VONA Red (Santunan darurat penginapan & transportasi evakuasi).
6. `FLIGHT_DIVERTED`: Pengalihan pendaratan ke bandara alternatif (*diversion* / e.g. tujuan DPS dialihkan mendarat di SUB).

### 2.2. Sumber Data Oracle Independen
Data telemetri yang dijadikan acuan hukum mencakup:
- SITA / ACARS Flight Tracking Messages (OFF, OUT, IN, ON times).
- Sensor ADS-B (Automatic Dependent Surveillance-Broadcast) independen.
- NOTAM resmi yang diterbitkan oleh AirNav Indonesia dan Kementerian Perhubungan.
- Integrasi API sistem operasional bandara (Airport Operational Database - AODB).

---

## 3. Alur Eksekusi Terdistribusi (Automated Settlement Flow)

```
[ Sensor Radar / NOTAM ]
           │
           ▼
[ Deteksi Pemicu di AviationIrropsService ]
           │
           ▼
[ Verifikasi Polis Penumpang Aktif ]
           │
           ▼
[ Panggilan Kontrak G7-K29: recordTrigger() ]
           │
           ├───> [ Pencatatan Ledger Asuransi: Beban Klaim ]
           ├───> [ Pembayaran Dompet Digital Instan ]
           └───> [ Pengiriman Notifikasi WhatsApp / SMS ]
```

1. **Deteksi Real-Time**: Ketika `AviationIrropsService` memproses pembatalan atau keterlambatan penerbangan berdurasi melampaui batas polis, sistem mencari seluruh manifes tiket yang memiliki add-on polis asuransi terikat.
2. **Pencatatan Idempotent**: Setiap panggilan ke `ParametricTriggerRecorder::recordTrigger` menggunakan kunci idempoten gabungan:
   $$\text{IdempotencyKey} = \text{hash}(\text{PolicyNumber} \parallel \text{FlightNumber} \parallel \text{TriggerType} \parallel \text{Date})$$
   Kunci ini mencegah terjadinya pencairan ganda (*double payout*) sekalipun sistem mengalami *retry* jaringan.
3. **Pencatatan Entri Akuntansi**: Transaksi asuransi parametrik dicatat ke subledger modul asuransi dan modul keuangan dengan nomor referensi audit terverifikasi.


---

## 4. Model Aktuaria Penentuan Premi & Cadangan Klaim

### 4.1. Probabilitas Keterlambatan Riil Berdasarkan Musim
Penentuan premi asuransi keterlambatan parametrik memanfaatkan data historis performa ketepatan waktu (*On-Time Performance* / OTP) rute bersangkutan:
$$\text{Premium} = \mathbb{E}[\text{Payout}] \times (1 + \lambda) + \text{AdminExpense}$$
$$\mathbb{E}[\text{Payout}] = \sum_{c \in \text{Triggers}} P(T \ge t_c) \times \text{Payout}_c$$

Di mana:
- $P(T \ge t_c)$: Probabilitas empiris keterlambatan melebihi ambang $t_c$ (misalnya 120 menit).
- $\lambda$: Faktor *risk loading* aktuaria (umumnya $15\% - 25\%$) untuk mengantisipasi ketidakpastian cuaca ekstrem.
- $\text{Payout}_c$: Besaran santunan otomatis per kejadian.

### 4.2. Manajemen Solvabilitas Dana Cadangan Klaim
Sistem mencadangkan dana klaim terlikuidasi (*Liquid Claims Reserve Fund*) di akun penampung modul keuangan yang dipantau setiap hari. Jika terjadi peristiwa bencana alam berskala besar (seperti erupsi VONA Red), sistem memicu protokol reasuransi stop-loss untuk melindungi modal kerja maskapai dan perusahaan asuransi mitra.

---

## 5. Ringkasan Kepatuhan & Integrasi Smart Contract

Pencatatan klaim pada kontrak G7-K29 `ParametricTriggerRecorder` mengamankan setiap transaksi dengan atribut berikut:
- Tanda tangan kriptografis penerbit telemetri (*oracle signature*).
- Timestamp pencatatan bersumber dari waktu jaringan terdesentralisasi.
- Log transaksi bersifat *append-only* dan terhubung dengan laporan rekonsiliasi audit kepatuhan regulasi asuransi Otoritas Jasa Keuangan (OJK).
