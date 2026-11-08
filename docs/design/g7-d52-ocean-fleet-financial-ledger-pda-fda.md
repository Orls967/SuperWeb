# G7-D52: Ocean Fleet Financial Ledger, PDA & FDA Settlement

## 1. Konteks Keuangan & Akuntansi Pelayaran Samudra

Dalam industri pelayaran niaga, perputaran kas pelayaran (*voyage cash flow*) melibatkan transaksi multinasional dengan agen pelabuhan di berbagai yurisdiksi. Mekanisme pembiayaan kapal diatur melalui siklus akun pencairan pelabuhan (*Disbursement Accounts*):
1. **Proforma Disbursement Account (PDA)**: Estimasi anggaran biaya masuk pelabuhan yang diserahkan oleh agen lokal kepada pemilik kapal sebelum kapal tiba. Pemilik kapal mentransfer dana muka pelabuhan (*advance cash funds*).
2. **Final Disbursement Account (FDA)**: Laporan pertanggungjawaban riil biaya pelabuhan setelah kapal berangkat, disertai bukti faktur asli (*vouchers*) untuk jasa labuh, pandu, tunda, sampah maritim, dan izin karantina.

Keputusan arsitektur **G7-D52** menetapkan integrasi buku besar pembukuan ganda (*double-entry fleet ledger*) dengan validasi kriptografis SHA-256 untuk memastikan seluruh transaksi kas, akrual uang tambang, biaya bahan bakar, dan rekonsiliasi varians PDA/FDA tercatat secara transparan dan berimbang.

---

## 2. Struktur Bagan Akun Standar Armada Maritim

Tabel `prt_fleet_ledger_entries` mencatat setiap mutasi transaksi keuangan dengan kode akun standar berikut:

| Kode Akun Finansial | Deskripsi Nama Akun | Karakteristik Debit / Kredit | Pemicu Transaksi Operasional |
|---|---|---|---|
| `prt:receivable:freight` | Piutang Uang Tambang Kargo | Normal Debit | Diterbitkannya Konosemen (*Bill of Lading*) setelah pemuatan selesai. |
| `prt:revenue:freight` | Pendapatan Freight Pelayaran | Normal Kredit | Pengakuan pendapatan jasa angkutan laut sebanding persentase penyelesaian voyage. |
| `prt:expense:bunker` | Beban Konsumsi Bahan Bakar | Normal Debit | Pembelian bunker (*Bunkering Operation*) atau amortisasi pemakaian BBM. |
| `prt:payable:disbursements`| Hutang Agen / Pengeluaran Pelabuhan | Normal Kredit | Penerimaan tagihan estimasi PDA dari agen perkapalan lokal. |
| `prt:expense:port_dues` | Beban Biaya Jasa Pelabuhan | Normal Debit | Rekonsiliasi riil jasa labuh, tambat, pandu, dan tunda dermaga. |
| `prt:receivable:demurrage`| Piutang Denda Demurrage | Normal Debit | Tagihan kompensasi keterlambatan laytime kepada pihak penyewa. |
| `prt:payable:despatch` | Hutang Premi Despatch | Normal Kredit | Insentif efisiensi waktu pemuatan kargo yang harus dibayar ke charterer. |
| `prt:escrow:general_average`| Jaminan Darurat General Average | Normal Kredit | Setoran deposito tunai *Average Bond* sebelum pelepasan muatan kargo. |

---

## 3. Alur Rekonsiliasi Varians PDA vs FDA

```
+---------------------------------------------------------------+
| Langkah 1: Pengajuan & Pendanaan PDA                          |
| - Agen lokal mengirim estimasi biaya pelabuhan: $16.400       |
| - Pemilik kapal menyetujui anggaran PDA                       |
| - Jurnal: Debit Beban Pelabuhan $16.400, Kredit Hutang Agen   |
+-------------------------------+-------------------------------+
                                |
                                v
+---------------------------------------------------------------+
| Langkah 2: Eksekusi Operasional di Pelabuhan                  |
| - Kapal menggunakan jasa pandu 2 jam                          |
| - Kapal menggunakan 2 kapal tunda (TB Bima V & TB Arjuna III) |
| - Kapal sandar di dermaga selama 36 jam                       |
+-------------------------------+-------------------------------+
                                |
                                v
+---------------------------------------------------------------+
| Langkah 3: Penerimaan & Audit Dokumen FDA                     |
| - Agen mengunggah bukti faktur asli biaya riil: $15.850       |
| - Terjadi surplus pengembalian dana (*Refund Due*): $550      |
+-------------------------------+-------------------------------+
                                |
                                v
+---------------------------------------------------------------+
| Langkah 4: Penyelesaian Finansial (FDA Settlement)            |
| - Sistem mencatat jurnal penyesuaian varians akuntansi        |
| - Saldo bersih kas dikembalikan ke rekening pemilik kapal    |
+---------------------------------------------------------------+
```

---

## 4. Keamanan & Integritas Kriptografis Rantai Hash SHA-256

Setiap baris entri di `prt_fleet_ledger_entries` menghasilkan hash verifikasi:
$$\text{Entry Hash} = \text{SHA256}(\text{ID} \parallel \text{VoyageID} \parallel \text{VesselID} \parallel \text{Account} \parallel \text{Amount} \parallel \text{Type} \parallel \text{PrevHash})$$
Jika ada baris data yang dimanipulasi secara manual pada basis data, rantai hash akan putus dan artisan command `prt:fleet-audit` secara instan mengeluarkan kode kesalahan (exit code 1) serta membekukan modul keuangan armada.

