# Panduan Operasional Kasir & Rekonsiliasi Harian Bank Rumah Sakit

## 1. Alur Kerja Loket Kasir Rumah Sakit (Cashier Operations Playbook)

Kasir rumah sakit bertindak sebagai titik temu akhir (*final touchpoint*) dalam siklus pendapatan *Order-to-Cash* (O2C). Kasir bertanggung jawab menerima pelunasan biaya perawatan dari pasien umum mandiri, porsi selisih biaya (*patient copay / coinsurance*), uang jaminan muka rawat inap (*deposit escrow*), serta menerbitkan kuitansi pelunasan resmi bermaterai.

```
+---------------------------------------------------------------------------------------------------+
|                                     ALUR TRANSAKSI KASIR RUMAH SAKIT                              |
+---------------------------------------------------------------------------------------------------+

   +------------------------+
   | Pasien Membawa Lembar  |
   | Tagihan / Discharge    |-----> Kasir memasukkan Nomor Rekam Medis (Medical Record Number / MRN)
   +------------------------+
                |
                v
   +------------------------+
   | Verifikasi Status      |-----> Periksa BillingEpisode:
   | Episode Tagihan        |       - Status wajib: VERIFIED_CODER atau CLAIM_SUBMITTED
   |                        |       - Pastikan porsi BPJS & Asuransi Swasta telah dialokasikan
   +------------------------+
                |
                v
   +------------------------+
   | Pilihan Metode         |-----> 1. Tunai Fisik (Cash Drawer)
   | Pembayaran Pasien      |       2. Kartu Debit / Kredit (Mesin EDC Bank)
   |                        |       3. QRIS Statis / Dinamis (National Standard)
   |                        |       4. Pemotongan Saldo Deposit Uang Muka (Escrow Offset)
   +------------------------+
                |
                v
   +------------------------+
   | Validasi Pembayaran &  |-----> Eksekusi Transaksi Kasir:
   | Settlement Sistem      |       - Nilai pembayaran melunasi sisa tagihan akhir
   |                        |       - Status BillingEpisode berubah menjadi: SETTLED
   +------------------------+
                |
                v
   +------------------------+
   | Pencetakan Kuitansi &  |-----> Cetak Kuitansi Resmi Bermeterai & Surat Bebas Tagihan
   | Surat Keterangan Lunas |       Pasien menyerahkan bukti lunas ke perawat untuk pelepasan gelang
   +------------------------+
```

---

## 2. Pengelolaan Titipan Uang Muka Pasien (Deposit Escrow Management)

Pada saat pasien dinyatakan harus rawat inap atau dijadwalkan operasi elektif, rumah sakit mewajibkan pembayaran deposit uang muka rawat:
1. **Penerimaan Uang Muka**:
   - Dicatat pada akun kewajiban lancar `HospitalAccounts::PATIENT_DEPOSITS_ESCROW` (Akun `2020`).
   - Kasir mencetak Bukti Titipan Uang Muka bernomor seri resmi.
2. **Pemotongan Saldo Deposit Saat Pulang**:
   - Ketika proses pemulangan pasien berlangsung, layanan waterfall secara otomatis mendebet akun `2020` dan mengkredit akun piutang pelayanan pasien `1110`.
3. **Penyelesaian Selisih Uang Muka**:
   - **Kasus Kurang Bayar**: Pasien melunasi kekurangannya melalui metode tunai, EDC, atau QRIS.
   - **Kasus Lebih Bayar**: Kasir mengembalikan sisa uang muka kepada pasien (*refund*) secara tunai atau transfer balik ke rekening asal pasien dalam kurun 1 x 24 jam dengan melampirkan formulir pengembalian deposit yang ditandatangani Manajer Keuangan.

---

## 3. Rekonsiliasi Harian Kasir & Mutasi Perbankan (Daily Cash Reconciliation)

Setiap pergantian shift kerja kasir (Shift Pagi: 07.00 - 14.00, Shift Sore: 14.00 - 21.00, Shift Malam: 21.00 - 07.00), kasir wajib melakukan penutupan kas laci (*cash drawer balancing*):

### Formulir Berita Acara Penutupan Kas Harian (Cash Count Sheet)
| Denominasi Uang Kertas / Logam | Jumlah Lembar / Keping | Subtotal Nilai Rupiah |
| :--- | :---: | :--- |
| **Rp 100.000** | 150 | Rp 15.000.000 |
| **Rp 50.000** | 80 | Rp 4.000.000 |
| **Rp 20.000** | 50 | Rp 1.000.000 |
| **Rp 10.000** | 30 | Rp 300.000 |
| **Rp 5.000** | 20 | Rp 100.000 |
| **Rp 2.000 / Koin** | 50 | Rp 100.000 |
| **Total Kas Fisik di Laci** | - | **Rp 20.500.000** |
| **Total Transaksi Mesin EDC** | - | **Rp 45.200.000** |
| **Total Transaksi QRIS** | - | **Rp 12.800.000** |
| **Total Pemotongan Deposit** | - | **Rp 15.000.000** |
| **TOTAL PENERIMAAN SHIFT** | - | **Rp 93.500.000** |
| **Total Menurut Sistem Komputer**| - | **Rp 93.500.000** |
| **SELISIH (DISCREPANCY)** | - | **Rp 0 (BALANCE / PAS)** |

### Prosedur Penyelesaian Jika Terjadi Selisih Kasir
- Jika fisik uang lebih banyak daripada catatan sistem (*cash overage*), selisih dibukukan ke akun pendapatan lain-lain sementara sambil menunggu penelusuran transaksi yang belum terinput.
- Jika fisik uang lebih sedikit (*cash shortage*), kasir bertugas wajib menandatangani Berita Acara Selisih Kasir dan mengganti kekurangan tersebut sesuai kesepakatan integritas kerja.

---

## 4. Prosedur Pembatalan Transaksi (Void & Refund Governance)

Untuk mencegah kecurangan (*fraud*) penggelapan kas:
- Kasir loket **tidak memiliki hak akses** untuk melakukan pembatalan transaksi (*void*) atau pengembalian dana (*refund*) secara mandiri.
- Setiap pengajuan pembatalan wajib melalui alur persetujuan (*approval chain*):
  1. Kasir mengajukan permintaan *Void/Refund Request* melalui sistem dengan menyertakan alasan tertulis dan pindaian nota tagihan asli.
  2. Supervisor Keuangan memeriksa rekam audit kronologis (*audit trail*) dan bukti fisik pembayaran.
  3. Manajer Keuangan memberikan otorisasi menggunakan kode sandi supervisor / biometrik.
  4. Sistem memposting jurnal balik otomatis (*reversal journal entry*) pada buku besar `HospitalAccounts` dan mengembalikan status `BillingEpisode` ke status sebelumnya.
