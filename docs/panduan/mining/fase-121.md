# Panduan Operasional Pengguna & Pengembang: Smelter & Metals Trading Desk (Fase 121)

## 1. Ikhtisar Panduan

Dokumen ini memandu peran operasional smelter (*Chief Metallurgist*, *Smelter Furnace Operator*, *Lab Quality Chemist*), staf kepatuhan lingkungan (*Environmental & Carbon Officer*), serta *Metals Trading Desk Trader* dalam mengoperasikan modul hilirisasi mineral Fase 121.

---

## 2. Prosedur Pengoperasian Tanur Peleburan Smelter RKEF

### A. Penjadwalan Rencana Run Tanur (Batch Run Scheduling)
1. Buka antarmuka navigasi **Pertambangan > Smelter & Hilirisasi** (`/fase121/smelter`).
2. Klik tombol **Rencana Run Tanur** untuk membuka dialog modal penjadwalan.
3. Masukkan parameter teknis umpan bijih:
   - **Pilih Unit Tanur**: Misal *Submerged Arc Electric Furnace Unit 1 (RKEF 45 MVA)*.
   - **Umpan Basah (Ore Feed WMT)**: Kuantitas bijih saprolit basah hasil tambang (misal 140.0 ton).
   - **Kadar Air (% Moisture)**: Hasil uji sampel laboratorium geologi (biasanya 30% - 35%).
   - **Kadar Ni & Fe (%)**: Hasil uji XRF sampling stockpile (misal 1.85% Ni, 18.20% Fe).
   - **Reduktor Batubara / Kokas**: Kuantitas agen pereduksi karbon (misal 15.0 ton).
   - **Flux Silika & Batu Kapur**: Kuantitas penyeimbang terak (misal 8.5 ton SiO2).
4. Klik **Simpan & Jadwalkan Run**. Status batch awal adalah `DRAFT`.

Contoh payload API:
```bash
curl -X POST https://app.example.com/fase121/smelter/runs \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{
    "furnace_id": "9d901b22-8d77-471f-b52b-fc6d7f02b001",
    "ore_feed_wmt": 140.0,
    "ore_moisture_pct": 32.0,
    "ore_ni_grade_pct": 1.88,
    "ore_fe_grade_pct": 18.20,
    "coal_reductant_tons": 15.0,
    "silica_flux_tons": 8.5,
    "tapping_temperature_c": 1520.0,
    "operator_in_charge": "Chief Metallurgist Bambang"
  }'
```

Response JSON:
```json
{
  "success": true,
  "data": {
    "id": "119e4567-e89b-12d3-a456-426614174000",
    "run_code": "RUN-FURN01-20261107-123456",
    "ore_feed_wmt": 140.0,
    "ore_feed_dmt": 95.2,
    "status": "DRAFT"
  },
  "message": "Batch tanur smelter berhasil dijadwalkan."
}
```

### B. Eksekusi Neraca Massa & Energi Pyrometallurgical
1. Dari tabel siklus batch tanur, klik tombol kalkulator biru pada baris batch yang ingin dihitung.
2. Sistem akan memicu algoritma `SmelterPyrometallurgicalEnergyBalanceSolver` (M10):
   - Menghitung umpan kering (Ore Feed DMT) dari kadar air.
   - Mengalkulasi perolehan logam leburan (NPI output tons) dan kadar Ni di produk leburan.
   - Menghitung generasi terak cair (*slag tapped output*) dan rasio basicity $(\text{MgO} + \text{CaO}) / \text{SiO}_2$.
   - Mengevaluasi konsumsi daya listrik spesifik (MWh/ton NPI) dan efisiensi termal tanur.
   - Menghitung emisi GRK Scope 1 (dari reduktor batubara) dan Scope 2 (dari daya listrik transmisi).
3. Bila efisiensi termal berada di dalam rentang 40% - 96%, sistem menetapkan status evaluasi `VERIFIED`.

Contoh pemanggilan API neraca energi:
```bash
curl -X POST https://app.example.com/fase121/smelter/balance \
  -H "Content-Type: application/json" \
  -d '{
    "run_id": "119e4567-e89b-12d3-a456-426614174000",
    "power_consumption_mwh": 48.0
  }'
```

### C. Penuangan Logam & Terak (Tapping & Product Capitalization)
1. Setelah reaksi reduksi pirometalurgi selesai dan temperatur leburan mencapai ambang batas minimum 1.400°C:
2. Kirim permintaan pencatatan tapping melalui endpoint `POST /fase121/smelter/tapping` dengan menyertakan tonase aktual logam dan terak.
3. Sistem secara otomatis:
   - Memajukan state machine batch tanur: `SMELTING -> TAPPING -> COMPLETED`.
   - Melakukan kapitalisasi persediaan produk logam ke akun buku besar `min:metals_inventory:IDR` berlawanan dengan `min:joint_production_cost_clearing:IDR`.
   - Memposting beban konsumsi daya listrik ke akun `expense:min:smelter_electricity:IDR`.

---

## 3. Prosedur Uji Laboratorium Mutu Spektrometri & Sertifikasi Mutu

1. Ambil sampel cairan logam leburan pada saat proses *tapping* menggunakan sendok sampel kuarsa.
2. Lakukan pendinginan cepat (*water quenching*) dan preparasi briket sampel untuk analisis spektrometri emisi optik (ICP-OES) atau sinar-X (XRF).
3. Catat hasil analisis pada endpoint `POST /fase121/smelter/assay`:
   - Kadar nikel primer (% Ni): Target komersial $\ge 10.0\%$ (NPI grade) atau $\ge 20.0\%$ (FeNi).
   - Kadar besi (% Fe), karbon (% C), silikon (% Si), fosfor (% P), dan belerang (% S).
4. Sistem memverifikasi kesesuaian dengan batas toleransi pengotor (*impurity tolerance*) standar LME.
5. Bila memenuhi spesifikasi, sistem menerbitkan sertifikat mutu laboratorium bertanda tangan digital *HMAC cryptographic seal* yang tidak dapat dimanipulasi.

Contoh payload sertifikasi assay:
```bash
curl -X POST https://app.example.com/fase121/smelter/assay \
  -H "Content-Type: application/json" \
  -d '{
    "run_id": "119e4567-e89b-12d3-a456-426614174000",
    "primary_metal_grade_pct": 13.50,
    "iron_fe_pct": 80.20,
    "carbon_c_pct": 2.10,
    "silicon_si_pct": 1.75,
    "phosphorus_p_pct": 0.022,
    "sulfur_s_pct": 0.030,
    "chief_chemist": "Dr. Hendra (Chief Smelter Chemist)"
  }'
```

---

## 4. Penatausahaan Sistem Resi Gudang (SRG) & Fasilitas SCF Bank

1. Buka halaman **Pertambangan > Resi Gudang Logam** (`/fase121/warehouse-receipt`).
2. Klik tombol **Terbitkan Resi Gudang Digital**:
   - Tautkan dengan nomor pengujian laboratorium mutu (*assay number*).
   - Masukkan berat tersertifikasi timbangan jembatan (*certified metric tons*).
   - Tentukan lokasi rak penyimpanan brankas aman (*storage rack location*).
3. Sistem menghitung haircut risiko kolateral dinamis (`WarehouseReceiptDigitalCollateralHaircutModel` M12):
   - Haircut volatilitas harga LME 30 hari.
   - Haircut durasi simpan gudang terakreditasi.
   - Plafond pembiayaan maksimum *Supply Chain Financing* ($\text{LTV} \le 80\%$).
4. Klik **Registrasi Bappebti / ISWARE** untuk mencatatkan resi gudang secara resmi ke sistem pusat registrasi PT Kliring Berjangka Indonesia.
5. Klik **Jaminkan SCF Bank** untuk mengikat hak fidusia jaminan dan mencairkan pinjaman modal kerja ke rekening giro perusahaan tambang.
6. Untuk melepaskan agunan, lunasi fasilitas SCF melalui tombol **Lepas Agunan** yang akan memposting pelunasan pinjaman ke buku besar dan membuka segel jaminan resi gudang.

Contoh payload penjaminan SCF:
```bash
curl -X POST https://app.example.com/fase121/warehouse-receipt/pledge-scf \
  -H "Content-Type: application/json" \
  -d '{
    "receipt_id": "229e4567-e89b-12d3-a456-426614174001",
    "bank_name": "PT Bank Mandiri (Persero) Tbk",
    "loan_amount_idr": 45000000000
  }'
```

---

## 5. Prosedur Trading Desk Logam & Mark-to-Market LME

1. Buka antarmuka **Pertambangan > Metals Trading Desk** (`/fase121/trading`).
2. **Pembuatan Kontrak Baru**:
   - Klik **Kontrak Baru** untuk mencatat kesepakatan penjualan fisik atau lindung nilai berjangka.
   - Tentukan pihak pembeli (*counterparty*), kuantitas metrik ton, dan harga kesepakatan dalam sen dolar per ton.
3. **Penguncian Harga LME**:
   - Klik ikon gembok pada kontrak untuk mengunci harga acuan settlement resmi LME Cash.
   - Status kontrak beralih dari `DRAFT` menjadi `PRICE_LOCKED`.
4. **Valuasi Harian Mark-to-Market**:
   - Jalankan perintah CLI `php artisan mining:mtm-metals-trading` atau klik ikon grafik MTM pada tabel kontrak.
   - Sistem membandingkan harga masuk (*entry price*) dengan kuotasi penyelesaian harian LME.
   - Menghitung laba/rugi yang belum direalisasi (*unrealized PnL*) dalam mata uang USD dan IDR.
   - Memposting penyesuaian harian ke buku besar akun `min:metals_trading_gain:IDR` atau `min:metals_trading_loss:IDR`.
5. **Penanganan Margin Call**:
   - Bila kerugian posisi derivatif melampaui margin penyangga (*maintenance margin* 8%), sistem mengaktifkan status `MARGIN_CALLED`.
   - Staf keuangan wajib melakukan setoran dana margin variasi (*variation margin*) ke rekening broker kliring sebelum pukul 15.00 waktu setempat.

---

## 6. Pemanfaatan Produk Sampingan Terak Slag & Ekonomi Sirkular

1. Buka halaman **Pertambangan > Produk Sampingan Smelter** (`/fase121/byproduct`).
2. Catat pengeluaran terak nikel padat (*granulated slag*) melalui menu pengiriman agregat:
   - Masukkan tonase pengiriman dan pihak pembeli industri semen atau kontraktor infrastruktur jalan.
   - Masukkan nomor manifest lingkungan SIMPEL KLHK.
3. Sistem secara otomatis memposting pendapatan penjualan terak ke akun `min:byproduct_sales_revenue:IDR` dan mengurangi stok persediaan terak di stockpile smelter.
4. Fitur daur ulang (`POST /fase121/byproduct/recycle`) memungkinkan pemanfaatan internal terak sebagai *subbase road base* jalan tambang atau bahan batako semen ramah lingkungan (*eco-bricks*).
