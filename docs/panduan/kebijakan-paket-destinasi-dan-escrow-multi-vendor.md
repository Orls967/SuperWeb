# Kebijakan Paket Destinasi Wisata & Rekening Escrow Multi-Vendor

## 1. Latar Belakang & Tujuan
Paket Destinasi Wisata Terpadu (*Integrated Destination Travel Packages*) menggabungkan layanan dari berbagai badan usaha independen yang mencakup fasilitas akomodasi perhotelan, tiket pertunjukan atau atraksi (*Venue*), serta armada penjemputan dan tur darat (*Logistics & Transport*).

Kebijakan ini menjamin bahwa hak pendapatan setiap entitas usaha dihitung secara adil berdasarkan proporsi Standalone Selling Price (SSP) sesuai kaidah IFRS 15 / PSAK 72, dan dana konsumen dilindungi dalam rekening penampungan aman (*Escrow Account*) sampai layanan berhasil diberikan.

---

## 2. Prinsip Alokasi Pendapatan Relatif (Relative SSP Allocation)
Ketika harga paket diskon ditawarkan ke publik, nilai potongan harga tidak dibebankan secara sepihak ke salah satu operator, melainkan dialokasikan secara proporsional berdasarkan rasio harga jual tunggal masing-masing pihak:

$$SSP\_Ratio_i = \frac{SSP_i}{\sum_{k=1}^{n} SSP_k}$$

$$Allocated\_Share_i = \text{Net\_Bundle\_Price} \times SSP\_Ratio_i$$

### Ilustrasi Kasus Paket Wisata Bahari 3D2N:
- Standalone Selling Price Hotel Resort (Suite Room): Rp 9.000.000 (60.0%)
- Standalone Selling Price Venue Marine Park (VIP Dive): Rp 3.750.000 (25.0%)
- Standalone Selling Price Logistics (Airport Transfer): Rp 2.250.000 (15.0%)
- **Total Nilai Mandiri (Sum of SSP):** Rp 15.000.000
- **Harga Paket Promo yang Dijual ke Tamu:** Rp 12.000.000 (Diskon Rp 3.000.000)

**Hasil Alokasi Hak Bersih:**
- Hak Hotel Resort: $60.0\% \times \text{Rp } 12.000.000 = \text{Rp } 7.200.000$
- Hak Venue Marine Park: $25.0\% \times \text{Rp } 12.000.000 = \text{Rp } 3.000.000$
- Hak Mitra Transportasi: $15.0\% \times \text{Rp } 12.000.000 = \text{Rp } 1.800.000$

---

## 3. Tata Kelola Rekening Escrow & Pelepasan Bertahap
Semua dana pembayaran konsumen yang masuk disimpan pada akun terisolasi:
`htl:escrow:destination_package:IDR`

```
+----------------------------------------------------------------------------------+
|                    ALUR PELEPASAN DANA REKENING ESCROW                           |
+----------------------------------------------------------------------------------+
|  [Pembayaran Tamu]       --> 100% Terkunci di Rekening Escrow                    |
|  [Milestone 1: Check-in] --> Rilis Porsi Hotel (Kredit kas hotel)                |
|  [Milestone 2: Gate In]  --> Rilis Porsi Venue (Kredit piutang tiket venue K16)  |
|  [Milestone 3: Drop Off] --> Rilis Porsi Transportasi (Kredit kas logistik)      |
+----------------------------------------------------------------------------------+
```

---

## 4. Penanganan Pembatalan & Sengketa (Dispute Resolution)
1. **Pembatalan Lebih Dari 7 Hari Sebelum Jadwal:**
   - Tamu berhak menerima pengembalian dana 90% dari rekening penampungan escrow.
   - Biaya administrasi pemrosesan 10% dipotong untuk menutupi biaya payment gateway dan operasional sistem.
2. **Pembatalan Kurang Dari 7 Hari Sebelum Jadwal:**
   - Dana tidak dapat dikembalikan penuh. Sebesar 50% dana escrow dilepaskan ke hotel sebagai biaya ganti rugi pemblokiran kamar (*Late Cancellation Fee*).
   - Sisa 50% dikembalikan kepada konsumen.
3. **Kejadian Kahar (Force Majeure):**
   - Erupsi gunung berapi, badai laut tropis, atau bencana alam yang menyebabkan penerbangan dibatalkan memicu pengembalian dana penuh (*100% Refund*) langsung dari akun escrow tanpa potongan.
