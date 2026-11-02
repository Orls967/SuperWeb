# Dokumen Desain Teknis: Lini 15 — Media & Kreatif (Med MVP)

## 1. Arsitektur Komponen & Modularitas

```
                       ┌─────────────────────────┐
                       │  modules/Med/Contracts  │
                       │    IpLicensingPort      │
                       └────────────▲────────────┘
                                    │
┌───────────────────────────────────┼──────────────────────────────────┐
│  modules/Med                      │                                  │
│  ┌─────────────────────────────┐  │   ┌───────────────────────────┐  │
│  │   StudioBookingService      ├──┘   │   IpRightsRegistryService │  │
│  │ (Slot Claims & Pessimistic) │      │  (Bps Shares & Effective) │  │
│  └──────────────┬──────────────┘      └─────────────┬─────────────┘  │
│                 │                                   │                │
│  ┌──────────────▼──────────────┐      ┌─────────────▼─────────────┐  │
│  │    IpLicensingService       │      │    RoyaltyWaterfallService│  │
│  │   (B0″.6 IntervalIndex)     │      │     (B0″.7 Waterfall)     │  │
│  └──────────────┬──────────────┘      └─────────────┬─────────────┘  │
│                 │                                   │                │
│  ┌──────────────▼──────────────┐      ┌─────────────▼─────────────┐  │
│  │   AdImpressionService       │      │      MedAuditCommand      │  │
│  │  (Dedup & Fraud Velocity)   │      │   (Two-Source Integrity)  │  │
│  └─────────────────────────────┘      └───────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────┘
```

## 2. Pemanfaatan Algoritma Numerik (BAGIAN 0″)

### 2.1 Deteksi Tumpang Tindih Lisensi (`IntervalIndex` — B0″.6)
Pemberian lisensi konten mengacu pada domain ruang-waktu: `(ip_id, rights_type, territory)` pada interval `[start_date, end_date)`.
- Menggunakan `Modules\Shared\Domain\Numerics\IntervalIndex` untuk mengindeks seluruh interval lisensi aktif.
- Aturan:
  1. Jika lisensi yang diajukan adalah `is_exclusive = true`, pencarian `IntervalIndex::queryOverlap()` pada `(ip_id, rights_type, territory)` harus menghasilkan himpunan kosong. Jika ada lisensi apa pun yang bertumpang tindih, permintaan DITOLAK dengan `LicenseOverlapException`.
  2. Jika lisensi yang diajukan adalah `is_exclusive = false`, pencarian tidak boleh bertumpang tindih dengan lisensi apa pun yang `is_exclusive = true`.

### 2.2 Penyelesaian Royalti Berjenjang (`Waterfall` — B0″.7)
Distribusi royalti multi-pihak diproses melalui `Modules\Shared\Domain\Numerics\Waterfall`:
- **Tingkat 1**: Biaya distribusi platform (misalnya 15% / 1.500 bps).
- **Tingkat 2**: Recoupment advance / jaminan minimum yang belum terlunasi (`recoupment_deducted`).
- **Tingkat 3**: Net Distributable Royalty Pool dibagikan kepada pemegang hak sesuai proporsi `share_bps` yang berlaku pada periode tersebut.
- **Invarian Pembulatan**: Menggunakan algoritma penyerapan sisa pembulatan sen/rupiah pada baris penerima terakhir (`remainder absorption`) agar:
  $$\sum \text{payout\_minor} = \text{net\_distributable\_minor}$$

---

## 3. Alur Bisnis Utama

### 3.1 Studio Booking & Slot Claim
1. Setiap studio memiliki slot diskrit 30 menit.
2. Pemesanan studio menuntut klaim slot atomik:
   - `SELECT * FROM med_studio_slot_claims WHERE studio_id = ? AND slot_start = ? FOR UPDATE`.
   - Menghindari race condition antara dua pemesan yang bersaing di milidetik yang sama.
3. Status pembayaran:
   - `HELD`: Slot diblokir sementara (hold 15 menit).
   - `CONFIRMED`: Pembayaran DP/hold berhasil.
   - `CHECKED_IN`: Klien hadir di studio, saldo ditangkap (capture).
   - `CANCELLED`: Pembatalan sebelum batas waktu cut-off melepaskan slot kembali ke pasar.
   - `NO_SHOW`: Klien tidak hadir, biaya penalti no-show (bps) dikenakan.

### 3.2 Registri IP & Pembagian Hak (Rights Share)
1. Setiap aset IP memiliki kode unik dan pemilik entitas grup.
2. Hak cipta dipecah per jenis hak (`MASTER`, `PUBLISHING`, `SYNC`, `MERCHANDISE`).
3. Setiap jenis hak memiliki tabel pembagian porsi hak `med_ip_rights_shares`:
   - Dinyatakan dalam basis points (bps, 1..10000).
   - `Σ share_bps` harus tepat 10.000 bps.
   - Menyimpan `effective_date`. Pembagian hak baru tidak mengubah royalti untuk pendapatan yang dihasilkan sebelum tanggal efektif tersebut.

### 3.3 Penagihan Iklan Programatik & Anti-Fraud
1. Setiap penempatan iklan (web, app, OOH digital screen) memiliki harga dasar (floor CPM).
2. Kampanye iklan menolak tawaran di bawah floor CPM.
3. Impresi masuk ke tabel fakta append-only `med_ad_impressions`:
   - Hash impresi dicek untuk mencegah duplikasi.
   - Deteksi fraud velocity: pengiriman lebih dari 10 impresi per detik dari identitas perangkat yang sama ditandai sebagai fraud (`is_fraud = true`).
4. Tagihan final kampanye dihitung hanya dari impresi yang terverifikasi (`is_verified = true` dan `is_fraud = false`).

---

## 4. Skema Database Tambahan

Tabel-tabel baru / yang diperkuat di Lini 15 dengan prefiks `med_`:
1. `med_studio_slot_claims`: tabel klaim slot 30 menit unik per studio.
2. `med_ip_rights_shares`: tabel fakta porsi hak cipta append-only dengan tanggal berlaku.
3. `med_license_revenues`: pencatatan pendapatan lisensi per periode penagihan.
4. `med_royalty_statements`: ringkasan statement royalti per lisensi dan periode.
5. `med_royalty_statement_lines`: baris rincian pembayaran royalti ke masing-masing pemegang hak.
6. `med_ip_advance_balances`: pelacakan saldo advance yang belum di-recoup per kontrak.
7. `med_ad_placements`: master inventaris iklan dengan floor CPM.
8. `med_ad_impressions`: tabel fakta impresi iklan append-only dengan hash dedup.

---

## 5. Strategi Audit Dua-Sumber (`med:audit`)
Audit command `med:audit` menjamin ketiadaan korupsi data:
1. Memastikan setiap rupiah royalti yang diterbitkan cocok dengan formula waterfall atas pendapatan lisensi yang sah.
2. Memeriksa ketiadaan lisensi eksklusif yang saling tumpang tindih.
3. Memvalidasi bahwa setiap aset IP memiliki pembagian hak yang tepat 10.000 bps pada setiap tanggal berlaku.
4. Memvalidasi bahwa total impresi yang ditagihkan ke pengiklan cocok dengan jumlah impresi bersih yang terverifikasi di tabel fakta impresi.
5. Memastikan tidak ada rekaman ganda pada slot waktu studio.
6. Mempertahankan kegagalan fixture korupsi `MedAuditCorruptionFixture`.
