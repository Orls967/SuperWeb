# Panduan Sales & MICE Manager — Fase 92: Perhotelan Enterprise

## 1. Ikhtisar Peran & Tanggung Jawab
Manajer Penjualan (Sales Manager) dan MICE Coordinator di ekosistem perhotelan bertanggung jawab atas pengelolaan portofolio akun korporat (*Corporate City Ledger*), negosiasi kontrak jamuan skala besar (*Banquet Event Order / BEO*), penyusunan paket destinasi lintas operator (*Destination Packages*), serta pemanfaatan data profil tamu (*Loyalty Passport & Churn Radar*) guna memaksimalkan *RevPAR* dan *TrevPAR*.

```
+----------------------------------------------------------------------------------+
|                   SALES & MICE OPERATIONS WORKFLOW (FASE 92)                     |
+----------------------------------------------------------------------------------+
|  [Kontrak Korporat]   --> Pagu Kredit AR  --> Sub-Folio Direct Billing           |
|  [Banquet BEO]        --> Recipe BOM      --> Margin Guard 45% -> Eksekusi Dapur |
|  [Paket Wisata SSP]   --> Escrow Rekening --> Milestone Check-in / Gate Scan     |
|  [Retensi Tamu H9]    --> Churn Alert     --> Win-Back Voucher & Free Upgrade    |
+----------------------------------------------------------------------------------+
```

---

## 2. Pengelolaan Akun Korporat & City Ledger (Direct Billing)
Penerbitan fasilitas penagihan langsung (*Direct Billing*) kepada instansi pemerintah, BUMN, dan korporat swasta diatur oleh ketentuan kredit yang ketat:

### 2.1. Persyaratan Pengajuan Pagu Kredit
1. Perusahaan wajib menyerahkan Salinan NPWP, NIB (Nomor Induk Berusaha), dan Surat Pengesahan Kemenkumham.
2. Form *Credit Application* telah ditandatangani oleh *Financial Controller* atau Direktur Keuangan perusahaan rekanan.
3. Plafon kredit standar yang dapat disetujui:
   - **Kategori Tier 1 (Blue Chip / BUMN):** Plafon hingga Rp 250.000.000 (Termin 30 Hari kalender).
   - **Kategori Tier 2 (Nasional Terverifikasi):** Plafon hingga Rp 100.000.000 (Termin 14 Hari kalender).
   - **Kategori Tier 3 (Start-up / UKM):** Plafon hingga Rp 30.000.000 (Termin 7 Hari kalender).

### 2.2. Mekanisme Sub-Folio Multi-Payer
Ketika tamu korporat menginap (*Executive Stay*):
- Biaya sewa kamar (*Room Charge*) dan pajak daerah (*PBJT*) otomatis dialokasikan ke **Sub-Folio B (Corporate Account)**.
- Biaya insidental seperti minibar pribadi, pemesanan minuman alkohol di bar, dan laundry ekspres dialokasikan ke **Sub-Folio A (Personal Folio Tamu)**.
- Bila pagu kredit korporat telah terserap 100%, sistem secara otomatis menolak pembebanan baru ke akun korporat dan mengalihkan seluruh pos tagihan ke penjaminan kartu kredit personal tamu (*Credit Limit Enforcement*).

---

## 3. Penyusunan Banquet Event Order (BEO) & Margin Guard
Dalam mengorganisasi acara konferensi, seminar, pameran, atau pesta pernikahan (*wedding reception*), Sales Manager wajib mematuhi protokol kalkulasi margin:

### 3.1. Rincian Bill of Materials (BOM) Dapur
Setiap menu set atau buffet memiliki rincian bahan baku terstandarisasi yang terhubung ke modul Logistik & Dapur Resto (K17):
- Komponen Bahan Makanan Mentah (Butchery daging sapi wagyu, unggas, hasil laut segar, sayuran hidroponik).
- Komponen Logistik Khusus (Bahan impor, keju artisan, mentega Prancis).
- Jam Tenaga Kerja Tambahan (*HCM Shift Bounty* / K18) untuk *banquet captain* dan juru masak paruh waktu.

### 3.2. Prosedur Eskalasi Margin Defisit
Bila margin kotor yang diproyeksikan berada di bawah ambang minimum **40.0%**:
1. Sistem menerbitkan alert *Margin Violation Warning*.
2. Pengajuan penawaran harga terkunci (*Sales Proposal Locked*).
3. Hanya General Manager (GM) dan Director of Finance yang memiliki hak otorisasi untuk memberikan dispensasi margin khusus melalui menu otorisasi bertingkat.

---

## 4. Paket Destinasi Multi-Vendor & Escrow Settlement
Sales Manager bertindak sebagai kurator paket perjalanan terintegrasi yang menggabungkan fasilitas resort hotel dengan atraksi lokal:

```
+---------------------------------------------------------------------------------+
|               STRUKTUR PEMBAGIAN SSP PAKET BALI 3D2N VIP                        |
+--------------------+---------------------+-----------------+--------------------+
| Operator Komponen  | Jenis Layanan       | Nominal SSP IDR | Persentase Hak     |
+--------------------+---------------------+-----------------+--------------------+
| Superweb Resort    | Suite 3D2N + Sarapan| Rp  7.500.000   | 60.0%              |
| Uluwatu Amphitheater| VIP Sunset Show K16 | Rp  3.000.000   | 24.0%              |
| Trans Bali Express | Alphard Airport Pick| Rp  2.000.000   | 16.0%              |
+--------------------+---------------------+-----------------+--------------------+
| TOTAL PAKET TAMU   | All-Inclusive Pass  | Rp 12.500.000   | 100.0%             |
+--------------------+---------------------+-----------------+--------------------+
```

### 4.1. Milestone Pelepasan Dana Escrow
- **Milestone 1 (Check-in Hotel):** 60% dana dilepaskan dari akun escrow ke kas hotel.
- **Milestone 2 (Pemindaian Tiket Venue):** 24% dana dilepaskan ke vendor venue setelah konfirmasi kehadiran di gerbang.
- **Milestone 3 (Penyelesaian Transportasi):** 16% dana dilepaskan ke operator armada transfer saat kendaraan kembali ke pangkalan.

---

## 5. Protokol Retensi Tamu & Penyelamatan Churn (H9)
Melalui Menara Kontrol Retensi Tamu (*Churn Control Tower*):
1. Tamu dengan probabilitas churn $\ge 65\%$ (*High Risk*) yang memiliki riwayat nilai transaksi tinggi wajib dihubungi oleh Sales Executive dalam waktu 48 jam.
2. Paket penawaran penyelamatan (*Win-Back Campaign*):
   - Voucher diskon menginap 30% untuk periode *low-season*.
   - Fasilitas peningkatan kategori kamar gratis ke *Ocean View Suite*.
   - Layanan antar-jemput bandara tanpa biaya tambahan.
