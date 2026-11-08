# Prosedur Operasional Standar: Penyelesaian Finansial & Kliring Tiket Codeshare (Codeshare Financial Settlement)

## 1. Definisi Pihak-Pihak dalam Transaksi Codeshare

Dalam setiap transaksi penjualan tiket penerbangan kerjasama codeshare:
1. **Operating Carrier (Penyelenggara Penerbangan)**:
   Maskapai penerbangan yang menyediakan fisik pesawat, bahan bakar avtur, pilot, awak kabin, dan bertanggung jawab penuh atas navigasi serta keselamatan penerbangan di udara.
2. **Marketing Carrier (Penjual Tiket Mitra)**:
   Maskapai penerbangan mitra yang menerbitkan nomor tiket, memasarkan kursi menggunakan nomor penerbangannya sendiri (e.g. nomor SQ5042 pada penerbangan fisik GA402), dan memungut pembayaran langsung dari penumpang di loket atau situs webnya.
3. **Validating / Plating Carrier**:
   Maskapai yang nomor tiket elektroniknya (3 digit pertama kode tiket IATA, misalnya 126 untuk Garuda Indonesia atau 618 untuk Singapore Airlines) tercantum pada dokumen manifes perjalanan.

---

## 2. Mekanisme Rekonsiliasi & Invoice Kliring Bulanan

### 2.1. Alur Siklus Penagihan
```
[ Marketing Carrier Menjual Tiket ]
                │
                ▼
[ Penumpang Terbang (Flown Coupon Event) ]
                │
                ▼
[ Operating Carrier Menerbitkan Billing Invoice ]
                │
                ▼
[ Evaluasi Kesepakatan di CodeshareSettlementSaga ]
                │
                ├───> [ Verifikasi Basis Poin Split ]
                ├───> [ Pencatatan Rekening Antar-Maskapai ]
                └───> [ Ekspor Berkas ICH Manifest CSV ]
```

### 2.2. Validasi Kupon Terbang (*Flown Lift Verification*)
Penagihan finansial hanya boleh dilakukan setelah Operating Carrier mengonfirmasi bahwa penumpang benar-benar telah menaiki pesawat (*lifted / flown coupon*). Jika penumpang membatalkan tiket (*refund*) atau tidak hadir (*no-show*), berlaku klausul perjanjian kerjasama:
- Pada *Free-Sale*: Hak penagihan Operating Carrier gugur; Marketing Carrier mengembalikan dana ke penumpang sesuai aturan tarif.
- Pada *Hard Block-Space*: Operating Carrier tetap menagih nilai sewa kursi yang disepakati (*guaranteed capacity payment*).

---

## 3. Akuntansi Subledger Transaksi Antar-Maskapai (Interline Clearing Accounts)

Transaksi dicatat ke pos jurnal akuntansi aviasi:
1. **Pengakuan Piutang Kliring oleh Operating Carrier**:
   - Debit: `1150-AVI-RECV-INTERLINE-CLEARING` (Piutang Kliring Maskapai Mitra)
   - Kredit: `4110-AVI-REV-CODESHARE-FLOWN` (Pendapatan Bagi Hasil Codeshare)
2. **Penyelesaian Pembayaran Bersih via IATA Clearing House (ICH)**:
   - Debit: `1010-AVI-BANK-ICH-SETTLEMENT` (Kas Masuk Penyelesaian ICH)
   - Kredit: `1150-AVI-RECV-INTERLINE-CLEARING` (Pelunasan Piutang Kliring)


---

## 4. Mekanisme Penanganan Sengketa Faktur Kliring (Invoice Dispute Resolution)

### 4.1. Alasan Sengketa Faktur yang Diakui
Dalam operasional kliring interline, penolakan atau sanggahan faktur (*billing dispute*) dapat timbul karena beberapa hal:
1. `MISMATCHED_PRORATE_FACTOR`: Selisih perhitungan nilai bagi hasil akibat perbedaan data Ticketed Point Mileage (TPM) atau versi tabel PMP yang digunakan.
2. `COUPON_NOT_FLOWN`: Kupon tiket ditagih oleh Operating Carrier, namun catatan gerbang keberangkatan (*departure control system* / DCS) menunjukkan status penumpang adalah *no-show* atau pembatalan.
3. `CURRENCY_EXCHANGE_DISCREPANCY`: Perbedaan kurs konversi mata uang antara tanggal transaksi (*date of transaction*) dan tanggal penyelesaian siklus ICH (*ICH settlement clearance date*).
4. `UNAUTHORIZED_CODESHARE_MAPPING`: Tiket diterbitkan menggunakan nomor penerbangan codeshare yang tidak aktif pada tanggal terbang.

### 4.2. Siklus Sanggahan 3 Tahap
- **Tahap 1 (Rejection Memo)**: Carrier yang ditagih menerbitkan memo penolakan dalam rentang 30 hari kalender sejak penerimaan berkas kliring ICH.
- **Tahap 2 (Billing Adjustment)**: Carrier penagih meninjau bukti telemetri penerbangan dan manifes penumpang. Jika terbukti valid, nota penyesuaian (*credit/debit note*) diterbitkan.
- **Tahap 3 (Arbitrase IATA)**: Jika sengketa tidak terselesaikan dalam rentang 90 hari, kasus dielevasi ke panel arbitrase komersial IATA untuk keputusan final yang mengikat.

---

## 5. Audit Kepatuhan & Ekspor Berkas Standard ICH

Sistem SuperWeb Aviation menyediakan subrutin ekspor otomatis berkas CSV kliring melalui kelas `CodeshareClearingCsvExporter`. Berkas ini diformat secara ketat agar dapat langsung diunggah ke portal ICH:
- Struktur kolom mencakup: `invoice_reference`, `billing_carrier`, `billed_carrier`, `ticket_number`, `coupon_number`, `origin`, `destination`, `flown_date`, `gross_amount`, `currency`, `prorated_net_amount`, `handling_fee`, `net_settlement`.
- Integritas data dijaga oleh checksum SHA-256 pada baris header dan footer berkas.


---

## 6. Integrasi dengan Modul Keuangan & Audit Kepatuhan Finansial

Seluruh transaksi penyelesaian codeshare dicatat dengan stempel waktu dan hash kriptografis untuk menjamin:
1. Tidak ada pengakuan ganda (*double recognition*) pada pendapatan penerbangan.
2. Seluruh pajak penerbangan lokal (PJP2U / Airport Tax dan PPN Tiket) dipisahkan secara otomatis sebelum bagi hasil bersih (*net prorate revenue*) dihitung.
3. Rekonsiliasi periodik dapat dilakukan menggunakan command `avi:codeshare-prorate` yang menghasilkan laporan disparitas tagihan.
