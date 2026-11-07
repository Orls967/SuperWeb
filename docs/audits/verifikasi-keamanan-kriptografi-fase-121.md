# Laporan Verifikasi Keamanan Kriptografi & Segel Digital: Fase 121

## 1. Ikhtisar Keamanan Kriptografi

Sistem Hilirisasi Smelter & Metals Trading Desk Fase 121 mengelola aset berharga tinggi (*high-value physical and digital commodity assets*) yang mencakup cadangan logam mulia dan logam industri (NPI/FeNi) bernilai puluhan miliar rupiah serta kontrak lindung nilai derivatif internasional. Untuk mencegah pemalsuan kuantitas produk, perubahan ilegal hasil uji laboratorium, atau penjaminan ganda (*double pledging*) resi gudang, sistem menerapkan arsitektur kriptografi deterministik berbasis HMAC dan SHA-256.

```
+---------------------------------------------------------------------------------------------------+
|                        CRYPTOGRAPHIC SEAL VERIFICATION MATRIX                                     |
+---------------------------------------------------------------------------------------------------+
|   Entitas Bisnis              | Skema Kriptografi     | Payload Hash                              |
+-------------------------------+-----------------------+-------------------------------------------+
| 1. Batch Run Tanur Smelter    | HMAC-SHA256           | RUN:{run_code}:{ore_feed_wmt}             |
| 2. Uji Mutu Lab Spektrometri  | HMAC-SHA256           | ASSAY:{assay_num}:{Ni%}:{Fe%}:{meetsLME}  |
| 3. Resi Gudang ISWARE Bappebti| Digital Seal SHA-256  | {weight}:{unit_price}:{gross}:{haircut}   |
| 4. Kontrak Lindung Nilai LME  | HMAC-SHA256           | CTR:{contract_num}:{quantity}:{price}     |
| 5. Solver Termodinamika M10   | Hash Digest SHA-256   | {metal}:{slag}:{power}:{emissions}:{eff}  |
| 6. Valuator MTM LME M11       | Hash Digest SHA-256   | {type}:{tons}:{price}:{pnl_usd}:{pnl_idr} |
| 7. Model Haircut SCF M12      | Hash Digest SHA-256   | {weight}:{price}:{gross}:{haircut}:{limit}|
+---------------------------------------------------------------------------------------------------+
```

---

## 2. Analisis Vektor Serangan & Mekanisme Pertahanan

### A. Serangan Pemalsuan Data Uji Laboratorium Mutu (Assay Tampering)
- **Skenario Serangan**: Pelaku mencoba mengubah kadar nikel dari $9.5\%$ menjadi $12.5\%$ langsung pada basis data SQL agar balok ingot memenuhi syarat penerbitan Resi Gudang Bappebti.
- **Mekanisme Pertahanan**:
  - Kolom `hmac_signature` pada tabel `min_smelter_quality_assays` dihasilkan menggunakan kunci rahasia aplikasi (`app.key`).
  - Sebelum resi gudang diterbitkan (`IssueMetalsWarehouseReceiptAction`), sistem memvalidasi ulang tanda tangan HMAC.
  - Perubahan sekecil apapun pada kolom `primary_metal_grade_pct` akan menyebabkan `hash_equals()` gagal dan membatalkan transaksi seketika dengan galat `ERR-SRG-003`.

### B. Serangan Penjaminan Ganda Resi Gudang (Double Pledging Attack)
- **Skenario Serangan**: Perusahaan mencoba menjaminkan resi gudang yang sama ke dua bank mitra SCF yang berbeda untuk memperoleh fasilitas kredit ganda.
- **Mekanisme Pertahanan**:
  - Status resi gudang dikawal oleh state machine ketat `WarehouseReceiptCollateralStateMachine`.
  - Transisi dari `REGISTERED_BAPPEBTI` ke `PLEDGED_SCF` hanya dapat dilakukan satu kali.
  - Saat berstatus `PLEDGED_SCF`, resi gudang terkunci secara permanen dan hanya dapat dialihkan ke `PARTIAL_RELEASE` atau `FULLY_RELEASED`.
  - Upaya penjaminan ulang akan ditolak oleh *guard condition* dengan eksepsi `InvalidMiningStateTransitionException`.

### C. Serangan Manipulasi Kuotasi Mark-to-Market (Price Feed Poisoning)
- **Skenario Serangan**: Pelaku memasukkan kuotasi harga LME palsu untuk menghindari margin call atau menciptakan laba fiktif.
- **Mekanisme Pertahanan**:
  - Data kuotasi divalidasi oleh verifikator matematis `LmeMetalMarkToMarketVerifier`.
  - Verifikator memeriksa batas toleransi arbitrase cash-and-carry dan menguji konsistensi arah laba/rugi terhadap jenis posisi SHORT/LONG.
  - Seluruh komponen kalkulasi PnL dienkapsulasi ke dalam hash SHA-256 yang divalidasi sebelum jurnal pembukuan buku besar diterbitkan.

---

## 3. Hasil Pengujian Audit Integritas Kriptografi

Pengujian otomatis rantai kustodi dieksekusi melalui aksi `AuditSmelterChainOfCustodyAction` pada seluruh data seeder dan batch produksi:
- Total Siklus Batch Diuji: 16 Siklus
- Validasi HMAC Tanur Smelter: 100% Valid
- Validasi HMAC Hasil Uji Lab Assay: 100% Valid
- Validasi Segel Digital Resi Gudang ISWARE: 100% Valid
- Status Rantai Kustodi Keseluruhan: `is_chain_intact = TRUE` (Tidak ditemukan celah atau anomali kriptografi).
