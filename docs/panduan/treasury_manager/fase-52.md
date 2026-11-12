# Panduan Pengguna — Manajer Treasury & Likuiditas Grup (Treasury Manager)

**Modul**: `Modules\Intercompany` (Fase 52)  
**Sasaran Pengguna**: Head of Corporate Treasury, Cash Manager, Treasury Operations Analyst  
**Tanggal Efektif**: 2026-11-12

---

## 1. Ikhtisar Operasional Treasury Konglomerasi

Manajer Treasury Grup bertanggung jawab mengelola likuiditas terpusat, meminimalkan biaya bunga pinjaman bank eksternal, mengoptimalkan saldo kas menganggur (*idle cash*), dan mengeksekusi penyelesaian kliring antar-entitas secara efisien.

Modul Intercompany Fase 52 menyediakan dua pilar utama treasury:
1. **Penyapuan Likuiditas Kas Terpusat (Cash Pool Liquidity Sweeper)**: Mengotomatiskan struktur rekening saldo nol (*Zero-Balance Account / ZBA*) harian.
2. **Matriks Netting Multilateral**: Mengurangi arus kas kawat antar-bank hingga lebih dari 60% melalui pembatalan utang sirkular dan pencocokan posisi bersih.

---

## 2. Operasional Cash Pooling Harian (`group:cash-pool-sweep`)

### Mekanisme Rekening Saldo Nol (ZBA)
Setiap entitas anak memiliki rekening operasional dengan saldo penyangga minimum (*minimum operating buffer*, misal Rp 100.000.000).
Pada akhir hari kerja (pukul 17:00 WIB), sistem menjalankan penyisiran likuiditas:
```bash
php artisan group:cash-pool-sweep --pool=CPOOL-MAIN
```

### Alur Kerja Penyapuan Kas:
1. **Sweep-In Surplus Kas**:
   Jika saldo rekening anak perusahaan melebihi buffer operasional, kelebihannya otomatis ditarik ke rekening induk (*Central Header Account*). Anak perusahaan mencatat piutang simpanan pool (*pool deposit receivable*) dan menerima akrual bunga simpanan harian.
2. **Fund-Out Defisit Kas**:
   Jika saldo rekening anak perusahaan berada di bawah buffer akibat pengeluaran operasional, rekening induk otomatis mentransfer dana penutup defisit. Anak perusahaan mencatat utang pinjaman pool (*pool loan payable*) dan dikenakan bunga pinjaman harian.
3. **Akrual Bunga & Pemotongan PPh 23**:
   Sistem secara otomatis menghitung bunga simpanan (misal tarif 5,50% / 550 bps) dan bunga pinjaman (misal tarif 7,50% / 750 bps) secara harian, memotong PPh 23 (15%), dan membentuk jurnal buku besar tanpa pembulatan desimal.

---

## 3. Eksekusi Netting Multilateral & Kliring Bank

Netting multilateral dilakukan setiap akhir pekan atau akhir bulan untuk menyelesaikan akumulasi faktur perdagangan dan biaya jasa bersama:
1. **Analisis Matriks Kewajiban Bruto**:
   Buka menu **Konsolidasi Grup & IC $\to$ Matriks Intercompany**:
   - Sistem menampilkan seluruh kewajiban bruto antar-pasangan entitas.
   - Algoritma pembatalan siklus (*cycle cancellation*) secara otomatis melenyapkan utang sirkular ($A \to B \to C \to A$).
2. **Eksekusi Kliring Kas Minimal**:
   Jalankan perintah rekonsiliasi netting:
   ```bash
   php artisan group:matrix-reconcile
   ```
   Sistem menerbitkan instruksi transfer kawat bank minimal:
   - Entitas dengan posisi bersih negatif (*Net Debtors*) hanya perlu mentransfer satu nominal ke entitas dengan posisi bersih positif (*Net Creditors*).
   - Invarian nol terverifikasi: total dana yang dibayarkan oleh seluruh debitur bersih tepat sama dengan total dana yang diterima oleh seluruh kreditur bersih.

---

## 4. Pengelolaan Fasilitas Pinjaman Antar-Entitas (IC Loans)

1. **Penerbitan Pinjaman Baru**:
   Setiap pinjaman intra-grup wajib didukung oleh perjanjian tertulis (*loan agreement*) dengan nomor kontrak unik, tingkat bunga wajar (*arm's length interest rate*), dan jadwal angsuran pokok.
2. **Akrual Bunga Bulanan**:
   Pada tanggal jatuh tempo bunga, jalankan:
   ```bash
   php artisan group:ic-interest --period=YYYY-MM
   ```
   Perintah ini:
   - Menghitung bunga kotor berdasarkan pokok pinjaman dan tingkat suku bunga basis points.
   - Memotong PPh 23 sebesar 15,00% (1.500 bps).
   - Memosting jurnal pengakuan biaya bunga pada peminjam, pendapatan bunga pada pemberi pinjaman, dan utang PPh 23 ke kas negara.

---

## 5. Analisis Sensitivitas Suku Bunga & Manajemen Risiko Likuiditas

Sebagai pengelola kas grup, manajer treasury memantau sensitivitas beban bunga terhadap perubahan suku bunga acuan:
1. **Stress-Testing Lonjakan Suku Bunga**:
   Sistem mensimulasikan dampak kenaikan suku bunga acuan (misal $+100\text{ bps}$ s.d. $+250\text{ bps}$) terhadap total beban bunga pinjaman mengambang (*floating rate loans*) intra-grup.
2. **Pengendalian Batas Rasio Utang terhadap Modal (Debt-to-Equity Ratio / DER)**:
   Sesuai Peraturan Menteri Keuangan No. 169/PMK.010/2015, rasio utang terhadap modal untuk keperluan perpajakan dibatasi maksimal $4 : 1$. Sistem secara otomatis memberikan peringatan dini (*early warning alert*) jika penambahan pinjaman intra-grup mendorong rasio DER anak perusahaan melampaui $3,5 : 1$.
3. **Penyangga Likuiditas Minimum (Liquidity Buffer Allocation)**:
   Setiap entitas anak dievaluasi profil arus kas mingguannya. Saldo penyangga disesuaikan secara dinamis menjelang periode pembayaran gaji (*payroll peak*) atau pembayaran pajak masa untuk mencegah penarikan overdraft darurat.

---

## 6. Daftar Istilah Penting Treasury Intercompany

- **ZBA (Zero-Balance Account)**: Rekening bank operasional yang setiap akhir hari saldonya disapu ke nol atau ke batas saldo penyangga minimum melalui transfer otomatis ke rekening induk.
- **Multilateral Netting**: Proses penyelesaian utang-piutang antar-banyak pihak di mana seluruh kewajiban bruto dikonsolidasi menjadi satu posisi bersih per entitas.
- **Cash Pool Sweeping**: Transfer fisik kas nyata antar-rekening bank untuk mengonsolidasikan likuiditas grup.
- **Notional Pooling**: Penggabungan saldo rekening secara virtual untuk keperluan perhitungan bunga bank tanpa transfer fisik dana.
- **Prime Rate**: Suku bunga acuan kredit korporasi terendah yang digunakan sebagai dasar penentuan suku bunga wajar pinjaman afiliasi.
- **Hedging**: Transaksi lindung nilai untuk memitigasi risiko volatilitas valuta asing atau suku bunga atas kewajiban intra-grup.
- **Intercompany Overdraft**: Saldo negatif rekening operasional anak perusahaan yang secara otomatis didanai dari kas terpusat induk.
- **Concentration Account**: Rekening penampungan utama di mana seluruh saldo anak perusahaan dikumpulkan untuk efisiensi penempatan instrumen pasar uang.
- **Settlement Cut-Off**: Batas waktu harian kliring transaksi perbankan untuk eksekusi transfer dana intra-grup.

