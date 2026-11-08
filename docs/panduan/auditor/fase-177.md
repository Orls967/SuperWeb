# Panduan Auditor & Tata Kelola Keuangan Aviasi (Fase 177)

Panduan ini ditujukan bagi Auditor Internal (`auditor`) dan Akuntan Aviasi dalam melakukan verifikasi integritas operasional, kepatuhan batas beban armada, serta rekonsiliasi buku besar dua sumber (*Two-Source Reconciliation*).

---

## 1. Verifikasi Rekonsiliasi Dua Sumber (Two-Source Accounting)

Modul Aviasi menerapkan prinsip pemisahan pencatatan mutasi transaksi operasional dan mutasi jurnal buku besar perbankan:
1. **Pendapatan Tiket (Ticket Revenue):**
   - Mutasi fisik: Jumlah agregat tiket dengan status `flown` pada tabel `avi_tickets`.
   - Mutasi finansial: Kredit pada akun buku besar `avi:ticket_revenue:IDR`.
   - Selisih antara mutasi fisik dan finansial wajib nol rupiah pada saat tutup buku.
2. **Kewajiban Pengembalian Dana (Refund Payable):**
   - Mutasi fisik: Tabel `avi_refunds` mencatat nilai pengembalian dana bersih (*net refund*) yang dihitung oleh algoritma A4.
   - Mutasi finansial: Kredit pada akun buku besar `avi:refund_payable:IDR`.
   - Rekonsiliasi otomatis diverifikasi setiap jam menggunakan perintah `php artisan avi:audit`.

---

## 2. Pengecekan Integritas Kargo & Rantai Kustodi

Untuk setiap kargo udara yang diangkut:
- Auditor dapat memeriksa keabsahan rantai hash kustodi (*custody hash chain*) yang dihasilkan oleh modul Logistics (K6).
- Hash genesis dihitung saat penerimaan awal di pergudangan bandara dan ditautkan dengan hash penyerahan ke petugas kargo pesawat.
- Tidak boleh ada catatan kargo berstatus `loaded` atau `in_flight` dengan kolom `custody_hash_chain` kosong atau tidak valid.

---

## 3. Ekspor Data Laporan Keuangan & Audit

Auditor dapat mengunduh rekonsiliasi lengkap melalui menu laporan `/aviation/reports` atau via perintah konsol:
```bash
# Menghasilkan rekap rekonsiliasi pendapatan, refund, dan pajak
php artisan avi:manifest GA-404 --type=both
```
Seluruh file CSV yang diekspor menggunakan format standar dengan tanda tangan UTF-8 BOM untuk memudahkan audit menggunakan aplikasi spreadsheet.
