# Runbook Operasional: Konsolidasi Intercompany & Audit Grup

**Kode Runbook**: `RB-IC-5201`  
**Target Modul**: `Modules\Intercompany` (`ic_`)  
**Tingkat Keparahan**: P1 (Gangguan pada proses fast-close keuangan atau audit pajak)  

---

## 1. Jadwal Command Otomatis (Cron Jobs)

| Waktu Eksekusi | Perintah Artisan | Deskripsi & Tujuan |
|---|---|---|
| Setiap hari 23:00 | `php artisan ic:accrue-interest` | Menghitung dan membukukan akrual harian/bulanan bunga pinjaman intercompany beserta potongan PPh 23/26. |
| Tanggal 28-31 23:30 | `php artisan ic:eliminate-period --period=YYYY-MM` | Mencocokkan saldo timbal balik bilateral dan memposting voucher jurnal eliminasi konsolidasi. |
| Tanggal 1 01:00 | `php artisan ic:allocate-costs --period=YYYY-MM` | Menjalankan perhitungan pembagian biaya terpusat (*cost sharing*) dan menerbitkan tagihan cermin ke anak perusahaan. |
| Akhir Kuartal 02:00 | `php artisan ic:tp-trueup --fiscal-year=YYYY` | Mengevaluasi deviasi margin tahun berjalan terhadap benchmark IQR OECD dan menghitung penyesuaian true-up. |
| Setiap hari 04:00 | `php artisan group:audit` | Memverifikasi seluruh invarian eliminasi konsolidasi dan saldo buku besar intra-grup. |

---

## 2. Prosedur Penyelesaian Diskrepansi Rekonsiliasi Bilateral (Insiden IC-REC-01)

### Gejala
`php artisan ic:eliminate-period` atau `php artisan group:audit` gagal dengan pesan:
```
[ERROR] Bilateral reconciliation discrepancy detected between SUB_A and SUB_B: delta = 4500000 IDR.
```

### Langkah Diagnosa
1. Identifikasi pasangan entitas yang bermasalah:
   ```bash
   php artisan ic:reconcile-pair --seller=SUB_A --buyer=SUB_B
   ```
2. Periksa apakah terdapat transaksi cermin yang statusnya terhenti di `POSTED_SELLER` dan belum berhasil mencapai `POSTED_BUYER`:
   ```sql
   SELECT id, transaction_code, amount_minor, status FROM ic_transactions 
   WHERE selling_entity_id = (SELECT id FROM ic_entities WHERE entity_code = 'SUB_A')
     AND buying_entity_id = (SELECT id FROM ic_entities WHERE entity_code = 'SUB_B')
     AND status != 'POSTED_BUYER';
   ```
3. Periksa apakah terdapat pengiriman barang dalam perjalanan (*goods-in-transit*) pada akhir bulan:
   ```sql
   SELECT * FROM ic_stock_transfers WHERE status = 'SHIPPED' AND received_at IS NULL;
   ```

### Langkah Remediasi
1. Jika transaksi terhenti karena galat jaringan/transaksi terputus saat posting sisi pembeli:
   ```bash
   php artisan ic:retry-buyer-posting --transaction-code=IC-TX-XXXXX
   ```
2. Jika selisih murni akibat waktu pengiriman barang (*timing difference*):
   - Sistem akan menandai transaksi sebagai `IN_TRANSIT`.
   - Jalankan proses eliminasi dengan toleransi pos transit yang disetujui:
     ```bash
     php artisan ic:eliminate-period --period=2026-10 --allow-in-transit
     ```
3. Verifikasi ulang dengan `php artisan group:audit` hingga mengembalikan status `0 discrepancy`.

---

## 3. Penanganan Pelanggaran Rentang Transfer Pricing (Insiden IC-TP-02)

### Gejala
Operator menerima penolakan saat mengajukan transaksi intercompany:
```
[ERROR] Margin 350 bps is below arm's length range [600 bps - 1400 bps] for category ELECTRONICS (PMK 172/2023).
```

### Langkah Penanganan
1. Periksa aturan transfer pricing aktif untuk kategori produk tersebut:
   ```sql
   SELECT * FROM ic_transfer_pricing_rules WHERE product_category = 'ELECTRONICS' AND is_active = 1;
   ```
2. Diskusikan dengan Group Tax Controller:
   - **Opsi A (Penyesuaian Harga Transaksi)**: Naikkan harga transfer ke titik Median (mis. 1.000 bps = 10% markup) agar transaksi langsung memenuhi prinsip arm's length.
   - **Opsi B (Pemberian Diskon Komersial Khusus)**: Jika terdapat alasan komersial yang sah (mis. penetrasi pasar baru), lampirkan dokumen pendukung studi transfer pricing dan ajukan waiver melalui *ApprovalEngine*.
3. Setelah disetujui, kirim ulang transaksi via UI atau perbarui draf transaksi.

---

## 4. Prosedur Pembatalan Transaksi Cermin (*Emergency Rollback*)

Jika transaksi yang telah terposting ke kedua buku entitas ternyata salah nominal atau salah entitas:
1. Jangan pernah menghapus baris basis data secara manual (`DELETE FROM ...` dilarang keras).
2. Gunakan perintah pembatalan dua arah terkoordinasi:
   ```bash
   php artisan ic:reverse-transaction --transaction-code=IC-TX-XXXXX --reason="Koreksi salah input faktur ganda"
   ```
3. Sistem akan:
   - Memvalidasi bahwa transaksi belum dieliminasi dalam laporan konsolidasi final.
   - Memposting jurnal pembalik persis di buku penjual (Kredit Piutang, Debit Revenue).
   - Memposting jurnal pembalik persis di buku pembeli (Kredit Expense/Stok, Debit Hutang).
   - Memindahkan status transaksi menjadi `REVERSED` dan mencatat jejak audit operator ke `ic_transaction_histories`.

