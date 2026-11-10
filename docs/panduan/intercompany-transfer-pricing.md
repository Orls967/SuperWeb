# Panduan Operasional: Intercompany, Transfer Pricing & Konsolidasi Grup

**Sasaran Pembaca**: Staf Akuntansi, Pengawas Pajak Grup (*Group Tax Controller*), Manajer Perbendaharaan, Auditor Internal  
**Modul**: `Modules\Intercompany` (`ic_`)  

---

## 1. Pendaftaran Entitas Legal Grup
Setiap transaksi intra-kelompok usaha hanya dapat dilakukan antar-entitas terdaftar dalam master `ic_entities`:
1. Navigasi ke menu **Intercompany > Master Entitas**.
2. Masukkan kode entitas unik (mis. `HOLDING`, `SUB_A_MFG`, `SUB_B_RETAIL`).
3. Tentukan yurisdiksi negara (ID, SG, MY), mata uang fungsional (IDR, USD, SGD), dan nomor pokok wajib pajak (NPWP/TIN).
4. Masukkan persentase kepemilikan induk dalam permil integer (misal: 100.000 untuk anak perusahaan penuh 100%, 75.000 untuk anak perusahaan 75% dengan 25% non-controlling interest).
5. Simpan entitas. Akun buku besar otomatis terhubung melalui `IntercompanyAccounts`.

---

## 2. Pembuatan Transaksi Cermin (*Mirror Transactions*)
Ketika satu entitas menjual barang atau jasa ke entitas afiliasi:
1. Operator di entitas penjual membuat draf faktur intercompany via UI atau API `/intercompany/transactions/create`.
2. Input parameter wajib:
   - `selling_entity_id`: Entitas penjual.
   - `buying_entity_id`: Entitas pembeli (tidak boleh sama dengan penjual).
   - `product_category`: Kategori barang/jasa untuk penentuan aturan transfer pricing.
   - `cost_idr`: Beban pokok perolehan/produksi barang.
   - `transfer_price_idr`: Harga jual yang diajukan ke pembeli.
   - `description`: Deskripsi transaksi bisnis.
3. Mesin otomatis memeriksa kesesuaian margin terhadap rentang interkuartil PMK 172/2023:
   - Jika margin berada dalam rentang wajar, status bergeser ke `ARMS_LENGTH_VALIDATED`.
   - Jika di luar rentang, sistem menampilkan peringatan deviasi dan menyarankan harga wajar berbasis Median.
4. Setelah disetujui, sistem secara atomik memposting faktur penjualan di buku penjual dan tagihan pembelian di buku pembeli (`POSTED_SELLER` $\to$ `POSTED_BUYER`).

---

## 3. Pengelolaan Pinjaman Intercompany & Pemotongan Pajak Bunga (PPh 23/26)
Untuk mengalirkan likuiditas surplus holding ke anak perusahaan:
1. Masuk ke **Intercompany > Pinjaman Antar-Entitas**.
2. Buat perjanjian pinjaman baru (*Intercompany Loan Agreement*):
   - Entitas Pemberi Pinjaman (*Lender*) dan Penerima Pinjaman (*Borrower*).
   - Nilai Pokok Pinjaman (*Principal*).
   - Suku Bunga Dasar dan Spread Arm's Length dalam basis points (misal: 600 bps bunga dasar + 50 bps spread = 650 bps / 6,50% per tahun).
   - Tarif Pajak Pemotongan: 1.500 bps (15,00% PPh 23) untuk domestik atau 2.000 bps (20,00% PPh 26) untuk luar negeri.
   - Tenor dan frekuensi pembayaran (misal 12 bulan).
3. Klik **Generate Skedul Amortisasi**. Algoritma D6 otomatis menghasilkan baris jadwal pembayaran pokok, bunga kotor, pajak potong, dan kas bersih.
4. Klik **Cairkan Pinjaman**. Sistem memposting pengeluaran kas di pemberi pinjaman dan penerimaan kas di peminjam secara simetris.
5. Pembayaran Bunga Bulanan:
   - Saat jatuh tempo, jalankan `php artisan ic:accrue-interest`.
   - Sistem membukukan beban bunga di peminjam, pendapatan bunga di pemberi pinjaman, dan kewajiban setor PPh 23 pada akun pajak pemotongan.

---

## 4. Alokasi Biaya Bersama (*Cost Sharing / Management Fee*)
Untuk membebankan biaya layanan terpusat (TI, HR, Legal, Manajemen):
1. Buat Perjanjian Pembagian Biaya (*Cost Sharing Agreement*) dengan menetapkan basis alokasi (rasio jumlah karyawan / *headcount* atau rasio omzet / *revenue*).
2. Di akhir bulan, jalankan aksi alokasi biaya:
   ```bash
   php artisan ic:allocate-costs --period=2026-10
   ```
3. Sistem menghitung rasio alokasi permil per anak perusahaan, menambahkan markup wajar (misal 5%), dan memposting tagihan intercompany secara otomatis.

---

## 5. Penutupan Buku Konsolidasi & Eliminasi Bilateral (Akhir Bulan)
Pada saat penutupan buku (*monthly financial close*):
1. Pastikan seluruh transaksi intercompany telah berstatus `POSTED_BUYER`.
2. Jalankan perintah pencocokan resiprokal dan jurnal eliminasi bilateral:
   ```bash
   php artisan ic:eliminate-period --period=2026-10
   ```
3. Algoritma D5 mencocokkan saldo Piutang vs Hutang dan Penjualan vs Beban Pokok.
4. Jika $\Delta = 0$, jurnal eliminasi konsolidasi otomatis dibukukan (`ELIMINATED`).
5. Jika ada selisih akibat barang/dana dalam perjalanan, sistem mencatat status `IN_TRANSIT` dan memasukkan ke antrean verifikasi operator.
6. Eliminasi laba persediaan belum terealisasi (*unrealized inventory profit*) dieksekusi secara otomatis jika terdapat saldo stok akhir hasil pembelian intra-grup.

---

## 6. Audit Konsolidasi Dua Sumber (`group:audit`)
Untuk memverifikasi integritas matematika eliminasi dan saldo intercompany:
```bash
php artisan group:audit
```
Command memverifikasi 5 invarian keras:
1. Tidak ada transaksi intra-entitas dengan pihak yang sama ($A \ne B$).
2. Total pembayaran kembali pokok pinjaman tidak melebihi pokok awal ($\text{Repaid} \le \text{Principal}$).
3. Total kepemilikan saham induk ditambah NCI tepat 100% (100.000 permil).
4. Seluruh jurnal eliminasi konsolidasi memiliki $\Sigma \text{Debit} = \Sigma \text{Kredit}$.
5. Tidak ada saldo floating point di seluruh subledger transaksi intercompany.

