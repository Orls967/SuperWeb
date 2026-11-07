# Pedoman Penanganan Biaya Insidental & Pergantian Tata Graha (Housekeeping Turnover)

## 1. Maksud & Ruang Lingkup Pedoman
Dokumen ini mengatur prosedur pembebanan pos biaya insidental kamar (*Incidentals Charge Posting*), inspeksi inventori minibar saat pergantian tamu (*Housekeeping Room Turnover*), dan prosedur otorisasi penjaminan deposit pada kartu kredit tamu (*Credit Card Pre-Authorization*).

```
+----------------------------------------------------------------------------------+
|                   ALUR KERJA PERGANTIAN TATA GRAHA & MINIBAR                     |
+----------------------------------------------------------------------------------+
|  [Tamu Memulai Checkout] --> Petugas Lantai Menerima Alert Pemeriksaan Kamar     |
|  [Pemeriksaan Minibar]   --> Input Kuantitas Konsumsi Melalui Aplikasi Tablet    |
|  [Posting Otomatis]      --> Baris Transaksi Masuk ke Sub-Folio Personal Tamu    |
|  [Penyelesaian Pembayaran]-> Kamar Ditandai 'VACANT_DIRTY' Siap Pembersihan Bersih|
+----------------------------------------------------------------------------------+
```

---

## 2. Prosedur Pra-Otorisasi Deposit (Pre-Auth Deposit Policy)
1. **Nilai Deposit Standar:** Sebesar biaya menginap seluruh malam ditambah deposit insidental Rp 500.000 per malam kamar.
2. **Kamar Korporat (Direct Bill):** Bila biaya kamar telah dijamin oleh akun City Ledger perusahaan, pra-otorisasi kartu kredit tamu hanya diambil sebesar Rp 500.000 per malam untuk mencakup konsumsi pribadi.
3. **Pelepasan Pra-Otorisasi:** Sisa dana pra-otorisasi yang tidak terpakai wajib dilepaskan (*Hold Released*) dalam waktu maksimal $1 \times 24$ jam kerja perbankan pasca penutupan folio.

---

## 3. Protokol Kehilangan & Kerusakan Inventori Kamar (Linen & Fixtures Loss)
Bila dalam inspeksi ditemukan linen bernoda permanen atau perlengkapan kamar hilang:
- Housekeeping Supervisor mendokumentasikan foto bukti dan mengunggahnya ke log tugas kamar (`htl_housekeeping_tasks`).
- Front desk membebankan tarif penggantian sesuai daftar standar kerugian properti:
  - Handuk Mandi Premium: Rp 150.000
  - Kimono Sutra Mandi: Rp 450.000
  - Pengering Rambut Ionik: Rp 850.000
  - Remote Televisi Pintar: Rp 250.000
- Staf wajib menjelaskan rincian biaya ini secara sopan dan transparan kepada tamu sebelum memproses transaksi debit.
