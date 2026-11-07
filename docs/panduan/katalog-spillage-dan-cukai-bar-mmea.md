# Spesifikasi Teknis: Katalog Spillage, Toleransi Bar & Cukai Impor MMEA (PMK 160/2023)

**Domain:** Venue & Entertainment (`modules/Venue/`, sub-item 90.2 & 90.3)  
**Terkait Modul:** Trade Finance (`modules/Trade/`), WMS Logistics, Resto F&B  
**Otoritas Acuan:** PMK No. 160/PMK.010/2023 tentang Tarif Cukai Etil Alkohol, Minuman yang Mengandung Etil Alkohol, dan Konsentrat yang Mengandung Etil Alkohol; UU No. 39 Tahun 2007; PP No. 49 Tahun 2022.  
**Versi Dokumen:** 1.0.0-PROD  
**Klasifikasi:** Rahasia Operasional & Standar Akuntansi Perusahaan  

---

## 1. Latar Belakang & Ruang Lingkup Operasi

Beach club dan klub malam premium SuperWeb mengoperasikan armada bar terdistribusi (Main Beach Bar, VIP Sunset Deck Bar, VIP Poolside Pavilion Bar, dan Nightclub Bunker Bar) dengan perputaran spirit premium, wine vintage, champagne, dan cocktail artisanal bernilai tinggi.

Pengelolaan rantai pasok dan inventaris bar malam memiliki dua risiko operasional dan kepatuhan finansial paling kritis:
1. **Penyusutan Fisik (*Spillage, Over-Pouring & Breakage*):** Kehilangan cairan spirit akibat tumpahan bartender, ketidaktelitian penuangan (*free-pouring*), evaporasi, atau pencurian terselubung.
2. **Kepatuhan Kepabeanan & Cukai MMEA (*Minuman Mengandung Etil Alkohol*):** Kewajiban perhitungan akurat Bea Masuk, Cukai MMEA spesifik tarif per liter sesuai golongan alkohol, PPh Pasal 22 Impor, dan PPN Impor 11% yang harus dikapitalisasi ke dalam Harga Pokok Penjualan (*Landed Cost*) botol spirit impor sebelum disalurkan ke mini-warehouse bar venue.

Dokumen spesifikasi ini menetapkan matriks toleransi takaran penuangan, ambang spillage sensor nirkabel (*smart pourer IoT*), formula matematis kalkulasi landed cost PMK 160/2023, serta mekanisme penguncian loker botol (*bottle locker vault*).

---

## 2. Klasifikasi Spirit MMEA & Matriks Tarif Kepabeanan Indonesia

Sesuai PMK 160/PMK.010/2023 dan Buku Tarif Kepabeanan Indonesia (BTKI 2022), seluruh stok spirit impor dan domestik diklasifikasikan ke dalam 3 golongan:

| Golongan | Kadar Alkohol (ABV) | Kategori Produk | Tarif Cukai Domestik (per Liter) | Tarif Cukai Impor (per Liter) | Bea Masuk (MFN) |
|---|---|---|---|---|---|
| **Golongan A** | $\le 5\%$ | Bir Premium, Hard Seltzer, Cider | Rp 16.500 | Rp 16.500 | 90% |
| **Golongan B** | $> 5\% \text{ s/d } 20\%$ | Anggur/Wine, Champagne, Sake, Vermouth | Rp 44.000 | Rp 53.000 | 90% |
| **Golongan C** | $> 20\% \text{ s/d } 55\%$ | Vodka, Whiskey, Gin, Rum, Tequila, Cognac | Rp 101.000 | Rp 148.000 | 150% |

### 2.1 Formula Matematis Total Landed Cost Botol Impor

Untuk setiap pengadaan consignment botol spirit impor yang dieksekusi via `ImportLandedCostQuoter` (`modules/Trade/Contracts/ImportLandedCostQuoter.php`):

Diberikan variabel masukan:
- $N \in \mathbb{N}^+$: Kuantitas botol impor.
- $V \in \mathbb{R}^+$: Volume per botol dalam mililiter ($700\text{ ml} = 0,7\text{ L}$; $750\text{ ml} = 0,75\text{ L}$; $1.000\text{ ml} = 1,0\text{ L}$).
- $V_{\text{total\_liters}} = \frac{N \times V}{1.000}$: Total volume cairan dalam liter.
- $P_{\text{CIF\_USD}} \in \mathbb{R}^+$: Nilai Cost, Insurance, and Freight per botol dalam USD.
- $R_{\text{NDBM}} \in \mathbb{R}^+$: Nilai Dasar Penghitungan Bea Masuk (Kurs Pajak Kemenkeu per USD).

Perhitungan langkah demi langkah:
1. **Nilai Pabean (CIF IDR):**
   $$\text{CIF}_{\text{total}} = N \times P_{\text{CIF\_USD}} \times R_{\text{NDBM}}$$
2. **Bea Masuk (BM):**
   $$\text{Tarif}_{\text{BM}} = 150\% \quad (\text{untuk Golongan C Spirit})$$
   $$\text{BM} = \lfloor \text{CIF}_{\text{total}} \times \text{Tarif}_{\text{BM}} \rfloor$$
3. **Cukai Spesifik MMEA (UU Cukai & PMK 160/2023):**
   $$\text{Tarif}_{\text{cukai\_liter}} = \text{Rp } 148.000 \quad (\text{untuk Golongan C Impor})$$
   $$\text{Cukai}_{\text{total}} = \lfloor V_{\text{total\_liters}} \times \text{Tarif}_{\text{cukai\_liter}} \rfloor$$
4. **Nilai Impor (Dasar Pengenaan Pajak):**
   $$\text{Nilai Impor} = \text{CIF}_{\text{total}} + \text{BM} + \text{Cukai}_{\text{total}}$$
5. **Pajak Penghasilan Pasal 22 Impor (PPh 22):**
   $$\text{Tarif}_{\text{PPh22}} = 2,5\% \text{ (Pemegang API-U/AEO)} \quad \text{atau} \quad 7,5\% \text{ (Non-API)}$$
   $$\text{PPh}_{22} = \lfloor \text{Nilai Impor} \times \text{Tarif}_{\text{PPh22}} \rfloor$$
6. **Pajak Pertambahan Nilai Impor (PPN Impor):**
   $$\text{Tarif}_{\text{PPN}} = 11\%$$
   $$\text{PPN} = \lfloor \text{Nilai Impor} \times \text{Tarif}_{\text{PPN}} \rfloor$$
7. **Total Biaya Pengadaan Komprehensif (Landed Cost Total):**
   $$\text{LandedCost}_{\text{total}} = \text{Nilai Impor} + \text{PPh}_{22} + \text{PPN}$$
   $$\text{CostPerBottle}_{\text{IDR}} = \left\lfloor \frac{\text{LandedCost}_{\text{total}}}{N} \right\rfloor$$

---

## 3. Matriks Standar Resep & Ambang Spillage Bar

### 3.1 Porsi Standar Penuangan Bar (*Standard Pour Measurements*)

Untuk menjaga margin kotor (*Gross Margin*) minuman $\ge 78\%$, setiap stasiun bartender diwajibkan menggunakan jigger kalibrasi stainless steel atau spout telemetri elektronik Bluetooth:

| Satuan Penuangan | Volume Efektif | Toleransi Diizinkan | Kategori Penggunaan |
|---|---|---|---|
| **Single Shot** | $30\text{ ml}$ (1.0 fl oz) | $\pm 1,5\text{ ml}$ ($\pm 5\%$) | Straight pour, highball mixer standar |
| **Double Shot** | $60\text{ ml}$ (2.0 fl oz) | $\pm 2,0\text{ ml}$ ($\pm 3,3\%$) | On the rocks, neat premium order |
| **Cocktail Core Pour** | $45\text{ ml}$ (1.5 fl oz) | $\pm 1,5\text{ ml}$ ($\pm 3,3\%$) | Signature cocktail spirit base (Mojito, Margarita) |
| **Artisanal Modifier** | $15\text{ ml}$ (0.5 fl oz) | $\pm 1,0\text{ ml}$ ($\pm 6,6\%$) | Liqueur, absinthe dash, triple sec |
| **Wine Standard Glass** | $150\text{ ml}$ (5.0 fl oz) | $\pm 5,0\text{ ml}$ ($\pm 3,3\%$) | By-the-glass pour (5 gelas per botol $750\text{ ml}$) |
| **Champagne Flute** | $120\text{ ml}$ (4.0 fl oz) | $\pm 4,0\text{ ml}$ ($\pm 3,3\%$) | Toasting pour (6 gelas per botol $750\text{ ml}$) |

### 3.2 Batas Toleransi Spillage & Penalti Ketidaksesuaian

Pada penutupan shift bar (`BarShiftLog`), selisih fisik antara stok awal ditambah replenishment dikurangi volume terjual pada POS (`ven_bar_inventory_items`) dihitung:

$$\Delta V_{\text{shift}} = (V_{\text{buka}} + V_{\text{terima}} - V_{\text{tutup}}) - V_{\text{terjual\_POS}}$$
$$\text{SpillageRate}_{\text{shift}} = \frac{\Delta V_{\text{shift}}}{V_{\text{terjual\_POS}}} \times 100\%$$

Ambang Batas Status Shift:
1. **Normal (Hijau):** $\text{SpillageRate} \le 3,00\%$. Kerugian cairan diakui sebagai beban wajar operasional bar (`ven:bar_spillage_loss:IDR`).
2. **Perhatian (Kuning):** $3,00\% < \text{SpillageRate} \le 5,00\%$. Bartender in-charge menerima peringatan telemetri sistem; wajib dilakukan re-kalibrasi jigger.
3. **Kritis / Investigasi Kehilangan (Merah):** $\text{SpillageRate} > 5,00\%$.
   - Shift log otomatis berstatus `flagged_for_audit`.
   - Action `AuditBarShiftStockAction` membekukan alokasi bonus shift.
   - Kelebihan selisih di atas $3,00\%$ dialihkan pembebanannya ke piutang ganti rugi staf atau klaim asuransi kehilangan internal.

---

## 4. SOP Bottle Locker Vault (Penyimpanan Botol VIP Konsinyasi Tamu)

Bagi tamu VIP sofa/cabana yang memesan paket botol utuh (*bottle service*) namun tidak menghabiskannya saat penutupan acara malam:

1. **Penerbitan Segel Digital & Barcode Tamper-Evident:**
   - Sisa cairan dalam botol diukur dengan milimeter gauge digital.
   - Botol disegel dengan lak segel fisik berkode QR unik (`locker_token`).
   - Sistem mencatat bobot tare, volume sisa ($V_{\text{sisa}}$ dalam ml), dan timestamp kedaluwarsa ($T + 90\text{ hari}$).
2. **Pencatatan Status Loker (`AssignBottleLockerAction`):**
   - Model `BarInventoryItem` menandai status `in_locker`.
   - Kepemilikan botol dialihkan sementara dari subledger bar aktif ke subledger penitipan tamu (*safe custody*).
3. **Klaim Pengambilan (*Retrieval*):**
   - Tamu wajib menunjukkan QR pas masuk pada aplikasi portal guest membership (`members/profile`).
   - Bartender memindai QR code botol dan memvalidasi keaslian segel anti-rusak.
4. **Kedaluwarsa & Forfeiture Penitipan:**
   - Jika dalam 90 hari kalender botol tidak diambil, botol berstatus `forfeited`.
   - Botol dialihkan menjadi house pour atau dimusnahkan secara legal dengan berita acara disposal.

---

## 5. Integrasi Jurnal Akrual Double-Entry Subledger Bar

Setiap mutasi inventaris bar menghasilkan entri jurnal berpasangan pada akun spesifik Venue (`VenueAccounts.php`):

### 5.1 Penerimaan Impor Spirit dengan Bea & Cukai:
- **Debit:** `ven:bar_inventory:IDR` (Nilai total Landed Cost CIF + BM + Cukai + PPh22 + PPN)
- **Credit:** `ven:bar_cukai_payable:IDR` (Kewajiban pembayaran cukai MMEA Kemenkeu)
- **Credit:** `ven:trade_payable:IDR` (Hutang dagang prinsipal spirit luar negeri CIF)
- **Credit:** `ven:import_duty_payable:IDR` (Hutang bea masuk kepabeanan)

### 5.2 Pengakuan Penjualan Botol & Cocktail (POS Settled):
- **Debit:** `ven:cashless_clearing:IDR` (Kas/piutang pembayaran tamu)
- **Credit:** `ven:bar_revenue:IDR` (Pendapatan kotor bar)
- **Debit:** `ven:bar_cogs:IDR` (Harga Pokok Penjualan inventaris keluar)
- **Credit:** `ven:bar_inventory:IDR` (Pengurangan stok bar fisik)

### 5.3 Pengakuan Spillage & Kerusakan Fisik:
- **Debit:** `ven:bar_spillage_loss:IDR` (Biaya kerugian susut bar)
- **Credit:** `ven:bar_inventory:IDR` (Penyesuaian pengurangan stok inventaris)

Dokumentasi ini menjadi rujukan operasional mengikat bagi Venue Manager, Bar Captain, dan Tim Keuangan SuperWeb.
