# Pedoman Integrasi Resto POS (K17) & HCM Gig Staffing (K18) di Modul Perhotelan

## 1. Ikhtisar Arsitektur Integrasi Lintas Modul
Fase 92 menghubungkan operasional inti hotel dengan dua modul eksternal strategis:
1. **Modul Restoran (K17 OutletSaleRecorder):** Menangani pemindahan tagihan makanan & minuman (*F&B Room Charge Posting*) dari kasir restoran atau room service langsung ke folio kamar tamu secara real-time dan idempoten.
2. **Modul HCM Human Capital Management (K18 ShiftBountyPublisher):** Memfasilitasi pengerahan tenaga kerja perbantuan (*Gig Staffing / Casual Banquet Staff*) saat volume jamuan rapat atau pernikahan berskala besar melampaui kapasitas karyawan tetap.

```
+----------------------------------------------------------------------------------+
|               ARSITEKTUR INTEGRASI LINTAS MODUL (K17 & K18)                      |
+----------------------------------------------------------------------------------+
|  [Outlet Kasir Resto] --> K17 Adapter --> HotelFolioLine (Kategori F&B)          |
|  [Pemesanan Banquet]  --> K18 Adapter --> HCM Shift Bounty (Staf Kasual BEO)     |
|  [Penyelesaian Acara] --> Verifikasi  --> Pembayaran Bounty & Jurnal Biaya Tenaga|
+----------------------------------------------------------------------------------+
```

---

## 2. Integrasi Restoran K17 (OutletSaleRecorder)

### 2.1. Protokol Idempotensi Pembebanan Kamar
Setiap transaksi posting room service membawa `Idempotency-Key` unik yang dibentuk dari format:
`POS-{outlet_id}-{bill_number}-{transaction_timestamp}`

Bila koneksi jaringan terputus saat kasir melakukan pengiriman tagihan, permintaan ulang (*retry*) dengan kunci idempotensi yang sama akan mengembalikan konfirmasi transaksi tanpa membukukan duplikasi debet pada folio tamu.

### 2.2. Validasi Batas Kredit & Status Check-in
Sebelum tagihan dibukukan ke kamar tamu:
1. Sistem memverifikasi bahwa status reservasi kamar saat ini adalah `CHECKED_IN`.
2. Sistem memeriksa apakah batas kredit deposit kamar atau plafon penjaminan korporat mencukupi nilai pemesanan restoran.
3. Bila kamar berstatus `CHECKED_OUT` atau deposit tidak mencukupi, sistem menolak transfer kamar dengan pesan *Insufficient Room Credit / Inactive Room Status*, mewajibkan tamu menyelesaikan pembayaran di kasir outlet secara tunai atau kartu.

---

## 3. Integrasi HCM K18 (ShiftBountyPublisher)

### 3.1. Penentuan Kebutuhan Tenaga Kerja Jamuan (Banquet Labor Ratio)
Rasio kebutuhan staf perjamuan dikalkulasikan secara otomatis berdasarkan tipe pelayanan:
- **Set Menu VIP (Fine Dining Plated):** 1 staf pramusaji melayani maksimal 10 orang tamu (1 Meja).
- **Buffet Prasmanan Standar:** 1 staf pramusaji melayani maksimal 25 orang tamu.
- **Cocktail Party / Standing Reception:** 1 staf pramusaji melayani maksimal 35 orang tamu.

### 3.2. Penerbitan Bounty Shift Tenaga Kerja
Bila jumlah tamu pada Banquet Event Order (BEO) mencapai 500 pax dengan tipe *Fine Dining*, sistem membutuhkan 50 tenaga pelayanan. Jika staf internal yang tersedia hanya 20 orang, sistem menerbitkan **30 Lowongan Shift Bounty** ke modul HCM:
- Judul Shift: *Banquet Service Associate — Grand Ballroom Gala Dinner*
- Durasi: 6 Jam Kerja Operasional.
- Nilai Bounty: Rp 250.000 per shift + jatah makan dan seragam.
- Kompensasi dicatat ke akun buku besar biaya tenaga kerja jamuan: `htl:expense:banquet_gig_labor:IDR`.
