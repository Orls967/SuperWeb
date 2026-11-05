# Definition of Ready (DoR) — Fase 165: Syariah Operations, Compliance & Integrasi 30 Lini

## Lingkup Pekerjaan
1. **165.1 Restrukturisasi Murabahah/Ijarah & Rescheduling**:
   - Skema permohonan restrukturisasi pembiayaan syariah bermasalah: perpanjangan jangka waktu (*rescheduling*), penataan kembali persyaratan (*reconditioning*), tanpa penambahan margin atau bunga riba baru.
   - Algoritma B9: `RestructuringSchedule` (perhitungan jadwal angsuran baru dengan amortisasi linear, grace period, dan haircut margin).
2. **165.2 Klasifikasi NPF (Non-Performing Financing) Syariah Kolektibilitas 1-5**:
   - Klasifikasi kolektibilitas 1 (Lancar: DPD 0), 2 (Dalam Perhatian Khusus: DPD 1-90), 3 (Kurang Lancar: DPD 91-120), 4 (Diragukan: DPD 121-180), 5 (Macet: DPD >180) sesuai regulasi POJK Syariah.
   - Algoritma B10: `NpfClassifier` (penentuan rasio NPF gross/net, bobot CKPN/PPAP, dan sertifikat verifikasi SHA-256).
3. **165.3 Integrasi Pembayaran Universal Syariah untuk 30 Lini Bisnis**:
   - Pembuatan Kontrak G7-K7: `Modules\Syariah\Contracts\SyariahWalletPayment` untuk dikonsumsi 30 lini bisnis.
   - Pemrosesan dompet digital syariah (*Shariah wallet float*) dengan pencatatan dual-entry ledger aman.
4. **165.4 Filter Transaksi Haram Lintas 30 Lini**:
   - Validasi ketat kategori merchant dan status sertifikasi halal BPJPH/MUI.
   - Pemblokiran instan transaksi pada merchant berkategori non-halal (alkohol, perjudian/taruhan, tempat hiburan malam, bunga pinjaman konvensional).
5. **165.5 Command Center Operasional Syariah & Audit DPS**:
   - Dasbor pemantauan real-time status kepatuhan syariah, audit trail operasional, review periodik Dewan Pengawas Syariah (DPS).
6. **165.6 Reporting NPF & Compliance Dashboard**:
   - Pelaporan agregat NPF bulanan, rasio kecukupan modal cadangan, status sertifikasi merchant, dan ekspor laporan regulator.

## Kriteria Penerimaan (Acceptance Criteria)
- Seluruh 6 sub-item memiliki model, migrasi, service, route, dan view responsif 375px.
- Acceptance test $\le 5$ skenario mencakup seluruh alur utama.
- Vektor algoritma B9 dan B10 teruji dengan sertifikat kriptografis SHA-256.
- Seeder deterministik T1 `SyariahOperationsT1Seeder` mengeksekusi $\ge 1.000$ data terintegrasi.
- Sabotase terdeteksi oleh `php artisan syb:audit --corrupt-fixture=SybOperationsCorruptionFixture`.
- Net baris kode produksi substantif $\ge 4.000$ baris.
