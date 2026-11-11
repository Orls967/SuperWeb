# Panduan Pengguna: Finance Controller — Tata Kelola Finansial Grup & Tutup Buku (Fase 54)

## 1. Peran & Tanggung Jawab
Finance Controller memegang kewenangan tertinggi dalam pengelolaan pagu anggaran multi-entitas, verifikasi kepatuhan Segregation of Duties (SoD), rekonsiliasi fiskal bulanan, dan eksekusi checklist tutup buku fiskal konglomerasi.

## 2. Alur Operasional Harian & Bulanan

### A. Pengawasan Anggaran & Komitmen (Budget & Encumbrance)
1. Akses menu **Tata Kelola Finansial $\to$ Anggaran** (`/enterprise-finance/budgets`).
2. Periksa hierarki alokasi anggaran: Holding $\to$ Anak Perusahaan $\to$ Direktorat $\to$ Departemen.
3. Pantau saldo pagu secara real-time yang terbagi menjadi:
   - **Pagu Disetujui (Allocated)**: Plafon anggaran tahunan.
   - **Komitmen Tertahan (Encumbered / Reserved)**: Pagu yang dicadangkan untuk Purchase Order terbuka via `BudgetCommitmentPort`.
   - **Realisasi Aktual (Spent / Liquidated)**: Faktur yang telah diakui dalam buku besar.
   - **Sisa Bebas (Available)**: Pagu bersih yang dapat dibelanjakan.
4. Apabila pagu tersisa $< 10\%$, sistem secara otomatis menerbitkan notifikasi peringatan ambang batas.

### B. Evaluasi Konflik SoD & Otorisasi Dispensasi
1. Buka menu **Tata Kelola $\to$ Matriks SoD** (`/enterprise-finance/sod`).
2. Tinjau daftar pelanggaran konflik peran aktif.
3. Dalam kondisi darurat operasional di mana seorang operator memerlukan akses ganda sementara (misal: penggantian cuti):
   - Klik tombol **Ajukan Dispensasi SoD**.
   - Masukkan ID Pengguna, Kode Aturan SoD (misal: `SOD-02`), Justifikasi Bisnis, dan Batas Waktu Kedaluwarsa.
   - Sistem mencatat dispensasi ke `ef_sod_exceptions` dan membatasi masa berlaku maksimal 30 hari.

### C. Prosedur Eksekusi Tutup Buku Fiskal Bulanan (Period Close)
1. Buka menu **Penutupan Buku** (`/enterprise-finance/close`).
2. Pilih Entitas dan Periode Fiskal (format `YYYY-MM`).
3. Jalankan **Verifikasi Checklist Tutup Buku**:
   - Rekonsiliasi Subledger AR, AP, Asset, Inventory terhadap Buku Besar wajib menghasilkan selisih 0.
   - Penyusutan aset tetap bulanan telah tereksekusi.
   - Tidak ada jurnal gantung yang belum disetujui.
4. Klik **Tutup Buku & Kunci Periode**:
   - Sistem secara otomatis memposting jurnal penutupan akun pendapatan dan beban ke `ef:income_summary:{entitas}:IDR`.
   - Saldo bersih dialihkan ke `ef:retained_earnings:{entitas}:IDR`.
   - Periode dikunci permanen (`ef_financial_period_locks`). Mutasi susulan ke periode ini akan ditolak sistem.

### D. Rekonsiliasi Fiskal & Setoran PPN
1. Buka menu **Pajak & Rekonsiliasi PPN** (`/enterprise-finance/tax`).
2. Bandingkan PPN Keluaran (`ef:vat_output_payable`) dengan PPN Masukan (`ef:vat_input_receivable`).
3. Klik **Hitung Setoran Kurang Bayar**:
   - Sistem memvalidasi nomor faktur gapless simulasi.
   - Sistem memposting setoran PPN ke kas negara (`ef:vat_settlement_clearing:{entitas}:IDR`).
   - Ekspor draf SPT Masa format resmi.

