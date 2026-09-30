# Runbook Operasional & Panduan Troubleshooting — Superwebsite

Dokumen ini adalah panduan standar operasional prosedur (SOP) bagi tim Engineering, DevOps, dan Operasional Holding dalam memantau, memelihara, dan menangani insiden pada platform **Superwebsite**.

---

## 1. Pemantauan Rutin & Pemeriksaan Kesehatan Harian

### 1.1 Diagnosa Menyeluruh 7 Pilar Platform
Jalankan diagnosa otomatis satu pintu setiap pagi atau pasca deployment:
```bash
php artisan super:health-check
```
**Parameter Keberhasilan**:
- Seluruh 7 sub-sistem berstatus `<fg=green>✓ HEALTHY</>`:
  1. Koneksi Database Primary (latensi $< 50$ ms)
  2. Cache & In-Memory Store
  3. Izin Akses Direktori Storage (writable)
  4. Konsistensi Double-Entry Ledger (`bank:reconcile` 0 selisih)
  5. Integritas Hash-Chain Paspor Kendaraan (`core:verify-passports` rantai utuh)
  6. Audit Tagihan & Revenue Mall (`mall:audit-billing` 0 selisih)
  7. Operasional & Shift Kasir Resto (`resto:close-day --check` lolos)
- Terbit entri log audit di tabel `core_audit_logs` dengan aksi `system.health_check`.

### 1.2 Dashboard Observabilitas GUI
Akses melalui peramban: `http://127.0.0.1:8000/admin/health` (Login sebagai `admin@autoserve.test`).
Halaman ini menyajikan metrik latensi, status komponen visual, tombol pemindaian instan, dan 15 riwayat jejak rekam audit sistem terakhir.

---

## 2. Playbook Penanganan Insiden (Incident Response)

### Playbook A: Diskrepansi Saldo Buku Besar (Ledger Imbalance)
- **Gejala**: `bank:reconcile` menghasilkan kode keluar `1` atau melaporkan `DITEMUKAN DISKREPANSI`.
- **Dampak Kritis**: Ketidaksesuaian nilai uang di dompet pengguna atau selisih debet/kredit global.
- **Langkah Mitigasi**:
  1. Jalankan `php artisan bank:reconcile -v` untuk melihat detail akun yang bermasalah.
  2. Periksa apakah inkonsistensi berupa:
     - **Tipe 1: Cached Balance Mismatch** (`cached_balance != sum(entries)`): Terjadi jika ada update langsung pada kolom `cached_balance` tanpa melalui `LedgerService`.
       - *Solusi*: Rekalkulasi saldo akun:
         ```sql
         UPDATE bank_ledger_accounts 
         SET cached_balance = (SELECT COALESCE(SUM(amount), 0) FROM bank_ledger_entries WHERE account_id = bank_ledger_accounts.id)
         WHERE id = [ID_AKUN];
         ```
     - **Tipe 2: Global Asset Imbalance** ($\sum \text{Entries per Aset} \neq 0$): Terjadi transaksi satu sisi tanpa pasangan balancing.
       - *Solusi*: Telusuri `transaction_id` pada `bank_ledger_entries` yang tidak memiliki pasangan debet/kredit seimbang. Lakukan entri penyesuaian manual berimbang melalui `ManualAdjustmentAction`.
  3. Jalankan kembali `php artisan bank:reconcile` untuk memastikan selisih kembali `0`.

---

### Playbook B: Kerusakan Hash-Chain Paspor Kendaraan
- **Gejala**: `core:verify-passports` melaporkan `[✗] RUSAK pada sequence N`.
- **Dampak**: Paspor digital kendaraan gagal verifikasi kriptografis, riwayat servis tidak dapat dipercaya.
- **Langkah Mitigasi**:
  1. Cari UUID kendaraan yang terdampak dari output command.
  2. Periksa baris event pada tabel `core_vehicle_events`:
     ```sql
     SELECT id, sequence, type, prev_hash, hash, occurred_at 
     FROM core_vehicle_events 
     WHERE vehicle_id = [ID_VEHICLE] 
     ORDER BY sequence ASC;
     ```
  3. Cek log perubahan database atau audit log untuk menemukan modifikasi baris data ilegal. Karena tabel `core_vehicle_events` bersifat *append-only* (dilindungi model boot events), modifikasi biasanya terjadi akibat eksekusi query raw langsung.
  4. Pulihkan *payload* asli dari backup transaksi / audit log, lalu verifikasi ulang rantai hash hingga seluruh sequence kembali valid.

---

### Playbook C: Kegagalan Auto-Debit Tagihan Sewa Tenant Mall
- **Gejala**: Command harian `mall:auto-debit` gagal melunasi invoice tenant karena saldo dompet tidak mencukupi.
- **Dampak**: Invoice berstatus `OVERDUE` dan penerimaan kas holding tertunda.
- **Langkah Mitigasi**:
  1. Buka Portal Mandiri Tenant atau hubungi PIC tenant yang tercatat di `mall_tenants`.
  2. Minta tenant melakukan top-up dompet IDR melalui menu Dompet (`wallet.index`).
  3. Setelah saldo terisi, eksekusi pelunasan via portal tenant atau jalankan ulang:
     ```bash
     php artisan mall:auto-debit
     ```
  4. Jika invoice melewati tanggal jatuh tempo (`due_date`), jalankan:
     ```bash
     php artisan mall:apply-penalties
     ```
     Sistem akan menambahkan denda keterlambatan 2% flat secara otomatis.

---

### Playbook D: Selisih Kas Laci Resto (Cash Drawer Discrepancy)
- **Gejala**: Kasir menginput uang tunai riil saat tutup shift yang berbeda dari kalkulasi sistem POS.
- **Dampak**: Muncul entri varians kas di buku besar.
- **Langkah Penanganan**:
  1. Manajer outlet memeriksa fisik uang kas di laci kasir outlet terkait.
  2. Sistem secara otomatis mencatat selisih tersebut ke akun `expense:resto:cash_variance:IDR` dan menyesuaikan saldo akun kas `cash:drawer:{outlet}:IDR`.
  3. Jalankan verifikasi penutupan harian:
     ```bash
     php artisan resto:close-day --check --outlet=[KODE_OUTLET]
     ```
  4. Verifikasi bahwa angka ringkasan harian di `resto_daily_summaries` tetap seimbang dengan ledger.

---

### Playbook E: Volatilitas Kripto & Likuidasi Pinjaman HODL-to-Drive
- **Gejala**: Terjadi penurunan drastis harga jaminan kripto (BTC/ETH/SOL) menyebabkan rasio pinjaman terhadap nilai jaminan (LTV) melonjak.
- **Dampak**: Risiko kerugian pembiayaan pada neraca holding.
- **Langkah Operasional**:
  1. Scheduler `finance:monitor-risk` memantau LTV setiap 5 menit:
     - **LTV $\ge 75\%$ (Margin Call)**: Notifikasi peringatan otomatis dikirim ke customer agar menambah jaminan atau mencicil pokok utang.
     - **LTV $\ge 85\%$ (Likuidasi Otomatis)**: Kolateral kripto otomatis dieksekusi jual di harga pasar, dana hasil penjualan didebet untuk menutup sisa pokok utang dan denda, dan sisa dana dikembalikan ke dompet customer.
  2. Verifikasi status pinjaman di menu Pembiayaan:
     ```sql
     SELECT id, loan_number, status, current_ltv FROM fin_loans WHERE status IN ('ACTIVE', 'DEFAULTED');
     ```

---

## 3. Jadwal Scheduler Otomatis (Production Crontab)

Pastikan satu baris crontab berikut aktif di server produksi:

```bash
* * * * * cd /var/www/autoserve && php artisan schedule:run >> /dev/null 2>&1
```

### Matriks Jadwal Tugas Terjadwal (Scheduled Tasks)

| Waktu Eksekusi | Artisan Command | Deskripsi Pekerjaan |
|---|---|---|
| Setiap 5 Menit | `crypto:tick` | Update harga pasar simulasi aset kripto |
| Setiap 5 Menit | `finance:monitor-risk` | Evaluasi risiko LTV, margin call & likuidasi |
| Setiap Jam | `store:cancel-stale-orders` | Batalkan reservasi belanja yang kedaluwarsa |
| Setiap Jam | `payment:release-expired-holds` | Rilis dana hold escrow yang melewati batas waktu |
| Harian 06:00 | `mall:renew-parking-members` | Perpanjangan otomatis langganan parkir |
| Harian 07:00 | `mall:generate-pm` | Terbitkan work order pemeliharaan gedung |
| Harian 08:00 | `resto:check-stock` | Pantau stok kritis bahan baku & draf PO |
| Harian 23:59 | `resto:close-day --check` | Tutup harian resto, buang waste & ringkasan |
| Bulanan Tgl 1, 00:01 | `mall:generate-invoices` | Terbitkan tagihan sewa & utilitas bulanan |
| Bulanan Tgl 1, 01:00 | `resto:post-royalty` | Hitung & posting royalti waralaba holding |
| Bulanan Tgl 5, 09:00 | `mall:auto-debit` | Eksekusi auto-debit tagihan tenant dari dompet |
| Bulanan Tgl 11, 00:01 | `mall:apply-penalties` | Terapkan denda 2% bagi tagihan terlambat |
| Mingguan Senin, 08:00 | `mall:settle-vouchers` | Cairkan klaim voucher belanja tenant |
| Mingguan Minggu, 23:59 | `mall:expire-vouchers` | Bukukan voucher kedaluwarsa ke breakage |

---

## 4. Prosedur Pencadangan & Pemulihan (Backup & Disaster Recovery)

### 4.1 Pencadangan Data (Backup)
```bash
# Backup SQLite Database
sqlite3 database/database.sqlite ".backup 'backups/db_$(date +%Y%m%d_%H%M%S).sqlite'"

# Backup Storage Dokumen & Bukti Bayar
tar -czvf backups/storage_$(date +%Y%m%d_%H%M%S).tar.gz storage/app/public
```

### 4.2 Pemulihan Data (Restore)
1. Hentikan web server sementara (maintenance mode):
   ```bash
   php artisan down --secret="holding-emergency"
   ```
2. Pulihkan file database dari salinan cadangan terakhir:
   ```bash
   cp backups/db_terpilih.sqlite database/database.sqlite
   ```
3. Jalankan audit kesehatan platform lengkap:
   ```bash
   php artisan super:health-check
   ```
4. Jika seluruh 7 pilar lolos verifikasi, aktifkan kembali server:
   ```bash
   php artisan up
   ```
