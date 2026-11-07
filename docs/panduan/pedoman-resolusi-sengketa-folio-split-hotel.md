# Pedoman Operasional — Resolusi Sengketa & Chargeback Pemisahan Folio

## 1. Definisi & Ruang Lingkup Masalah
Dalam operasional penagihan kamar hotel skala enterprise, perbedaan persepsi antara tamu individual, pemesan korporat, dan pengelola kasir sering menimbulkan sengketa tagihan (*Folio Billing Disputes*). 

Sengketa tagihan yang tidak ditangani secara terstruktur dapat menghambat proses check-out tamu, melanggar *Zero-Balance Checkout Principle*, dan merusak reputasi kerjasama korporat.

```
+----------------------------------------------------------------------------------+
|                   ALUR KERJA PENANGANAN SENGKETA TAGIHAN                         |
+----------------------------------------------------------------------------------+
|  [Klaim Sengketa Tamu]   --> Isolasi Item ke Sub-Folio 'DISPUTED_HOLD'          |
|  [Saldo Tamu Menjadi 0]  --> Tamu Diperkenankan Check-out Tepat Waktu            |
|  [Investigasi Internal]  --> Audit Slip Fisik POS / Rekaman Panggilan Telepon    |
|  [Keputusan Resolusi]    --> Beban Korporat (AR) / Allowance GM / Tagih Tamu     |
+----------------------------------------------------------------------------------+
```

---

## 2. Tipologi Sengketa Tagihan Umum

### 2.1. Penolakan Konsumsi Minibar & Mini Bar Discrepancy
- Tamu menyatakan tidak pernah mengonsumsi makanan ringan atau minuman kaleng di lemari pendingin kamar.
- Staf front desk mengisolasi nilai minibar tersebut ke sub-folio dispute dan menginstruksikan staf housekeeping lantai untuk melakukan pengecekan fisik ulang segel barang.
- Bila segel barang masih utuh, nilai tagihan dihapuskan (*Voided Line*). Bila segel rusak namun tamu tetap menolak, nilai dialihkan ke akun biaya representasi hotel (*GM Courtesy Allowance*).

### 2.2. Salah Posting Nomor Kamar dari Outlet F&B
- Pelayan outlet restoran salah menginput nomor kamar saat tamu lain memesan minuman di bar tepi kolam.
- Kasir memeriksa tanda tangan pada slip restoran fisik.
- Bila tanda tangan tidak cocok dengan spesimen tanda tangan registrasi tamu, transaksi segera dipindahkan ke nomor kamar yang sebenarnya atau ke akun clearing kasir restoran.

### 2.3. Perbedaan Interpretasi Ketentuan Kontrak Korporat
- Perusahaan penjamin menyatakan bahwa fasilitas makan siang tidak termasuk dalam paket dinas yang disetujui (*Ex-Contract Charge*).
- Manajer Penjualan memverifikasi salinan resmi *Letter of Authorization (LoA)*.
- Bila LoA memang tidak mencakup makan siang, bagian penagihan mengirimkan pemberitahuan resmi kepada tamu bersangkutan untuk menyelesaikan pembayaran secara langsung dalam tenggat 7 hari kerja.

---

## 3. Batas Wewenang Penghapusan Tagihan (Authorization Matrix)
Penghapusan atau penyesuaian debet pada folio yang disengketakan diatur oleh batas wewenang berjenjang:

```
+----------------------------------+-----------------------+-----------------------+
| Jabatan Otorisator               | Batas Maksimal (IDR)  | Keterangan Otorisasi  |
+----------------------------------+-----------------------+-----------------------+
| Front Desk Duty Manager          | Hingga Rp   500.000   | Minibar / Laundry Fee |
| Front Office Manager (FOM)       | Hingga Rp 2.000.000   | Room Service / Spa    |
| Financial Controller (FC)        | Hingga Rp 10.000.000  | Penyesuaian Tarif     |
| General Manager (GM)             | Tanpa Batas Nominal   | Pembatalan Kontrak B2B|
+----------------------------------+-----------------------+-----------------------+
```
