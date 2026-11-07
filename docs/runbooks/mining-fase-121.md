# Runbook Operasional & Mitigasi Insiden: Smelter & Metals Trading Desk (Fase 121)

## 1. Ikhtisar Runbook

Runbook ini mendokumentasikan prosedur standar operasi (*Standard Operating Procedures* - SOP), langkah-langkah diagnostik darurat, dan mitigasi kegagalan pada tanur peleburan smelter RKEF, anomali uji mutu laboratorium spektrometri, lonjakan margin call bursa LME, dan penolakan registrasi resi gudang Bappebti.

```
+---------------------------------------------------------------------------------------------------+
|                        INCIDENT ESCALATION WORKFLOW (FASE 121)                                    |
+---------------------------------------------------------------------------------------------------+
|  [LEVEL 1: TELEMETRY ALERT] ──► [LEVEL 2: METALLURGIST] ──► [LEVEL 3: SHIFT CHIEF] ──► [E-STOP]  |
|   - Tapping Temp > 1650°C       - Check Slag Basicity       - Reduce Transformer Power - Abort Run|
|   - Cooling Water Press Drop    - Re-Assay Lab Sample       - Transfer Variation Margin - Evacuate|
+---------------------------------------------------------------------------------------------------+
```

---

## 2. Prosedur Darurat 1: Penghentian Darurat Tanur Smelter (Furnace Emergency Trip)

### Indikator Pemicu
1. Alarm temperatur leburan melampaui batas kritis ($T > 1.680^\circ\text{C}$).
2. Penurunan tekanan sirkulasi air pendingin dinding tanur (*shell water cooling leak* $< 2.5 \text{ bar}$).
3. Ketidakseimbangan busur listrik elektroda (*electrode break or phase imbalance* $> 30\%$).

### Prosedur Penanganan Langkah demi Langkah
1. **Langkah 1 (Isolasi Daya Listrik)**:
   - Tekan tombol *Emergency Stop* (E-Stop) pada panel SCADA ruang kendali utama tanur.
   - Buka pemutus sirkuit tegangan tinggi (*high-voltage circuit breaker*) 45 MVA transformator tanur.
2. **Langkah 2 (Eksekusi Aksi Sistem)**:
   - Jalankan endpoint abort atau perintah artisan:
     ```bash
     php artisan tinker --execute="app(\Modules\Mining\Application\Actions\AbortSmelterFurnaceRunAction::class)->execute(\Modules\Mining\Domain\Models\MiningSmelterFurnaceRun::where('run_code', 'RUN-XXX')->first(), 'Emergency Trip: Shell Cooling Water Pressure Drop');"
     ```
   - State machine beralih ke status `ABORTED` dan mengunci seluruh instruksi tapping otomatis.
3. **Langkah 3 (Pendinginan Terkendali & Pengosongan Darurat)**:
   - Jika leburan logam masih dalam kondisi cair, arahkan ke palung darurat (*emergency slag/metal pit*).
   - Catat kerusakan refraktori dan isolasi jalur umpan material baru.

---

## 3. Prosedur Darurat 2: Penanganan Peringatan Margin Call Trading Desk

### Indikator Pemicu
1. Status kontrak perdagangan beralih ke `MARGIN_CALLED`.
2. Posisi lindung nilai (*short hedge*) mengalami kerugian belum terealisasi akibat kenaikan harga spot/forward LME > 10% dalam 24 jam.
3. Ekuitas di rekening broker kliring berada di bawah batas margin pemeliharaan (*maintenance margin* 8%).

### Prosedur Penanganan Langkah demi Langkah
1. **Langkah 1 (Verifikasi Nilai Margin Variasi)**:
   - Jalankan verifikasi status posisi kontrak:
     ```bash
     php artisan mining:mtm-metals-trading CTR-GLENCORE-2026-01
     ```
   - Periksa nilai nominal `variation_margin_required_idr`.
2. **Langkah 2 (Transfer Dana Jaminan Likuid)**:
   - Koordinasi dengan Treasury / Keuangan untuk memindahkan dana tunai ke rekening penyedia kliring LME.
   - Jika likuiditas tunai terbatas, gunakan fasilitas talangan agunan Resi Gudang (SRG) yang masih memiliki sisa plafond SCF belum ditarik.
3. **Langkah 3 (Penyesuaian Posisi / Stop-Loss Order)**:
   - Jika pasar mengalami kondisi *short squeeze* berkepanjangan, lakukan penutupan parsial posisi derivatif (*partial unwinding*) atau beli opsi beli (*call option*) untuk membatasi kerugian lebih lanjut.

---

## 4. Prosedur Darurat 3: Penolakan Kadar Mutu Logam oleh Laboratorium Assay

### Indikator Pemicu
1. Hasil pengujian spektrometri menunjukkan kadar nikel primer di bawah ambang batas komersial ($\text{Ni} < 10.0\%$ untuk NPI atau $\text{Ni} < 8.0\%$ batas batas absolut).
2. Kadar pengotor fosfor atau belerang melampaui batas toleransi LME ($\text{P} > 0.040\%$ atau $\text{S} > 0.050\%$).

### Prosedur Penanganan Langkah demi Langkah
1. **Langkah 1 (Karantina Lot Produk)**:
   - Tandai tumpukan balok ingot leburan dengan label merah "MUTU DALAM INVESTIGASI".
   - Sistem secara otomatis menolak penerbitan Resi Gudang (`IssueMetalsWarehouseReceiptAction` memvalidasi kelayakan assay lab).
2. **Langkah 2 (Uji Ulang / Re-Assay Independen)**:
   - Ambil 3 sampel sekunder dari bagian atas, tengah, dan bawah cetakan leburan.
   - Kirim ke laboratorium penguji independen terakreditasi KAN (misal PT Sucofindo / PT Geoservices).
3. **Langkah 3 (Peleburan Ulang / Blending Agregat)**:
   - Bila hasil re-assay tetap tidak memenuhi syarat, jadwalkan pencampuran (*re-melting blending*) dengan umpan bijih kadar tinggi pada siklus tanur berikutnya.

---

## 5. Prosedur Darurat 4: Penolakan Pendaftaran Resi Gudang Bappebti / ISWARE

### Indikator Pemicu
1. Respon gateway ISWARE mengembalikan status `REGISTRATION_REJECTED`.
2. Kode galat: Ketidaksesuaian nomor registrasi gudang terakreditasi atau tanggal kedaluwarsa dokumen sertifikasi timbangan.

### Prosedur Penanganan
1. Periksa masa berlaku sertifikasi kalibrasi jembatan timbang (*weighbridge calibration certificate*).
2. Pastikan nomor izin operasional pengelola gudang (WMS) masih aktif di database Bappebti.
3. Jalankan kembali aksi registrasi setelah perbaikan metadata:
   ```bash
   php artisan tinker --execute="app(\Modules\Mining\Application\Actions\RegisterWarehouseReceiptBappebtiAction::class)->execute(\Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt::where('receipt_number', 'SRG-XXX')->first());"
   ```

---

## 6. Prosedur Rutin Harian & Mingguan

### Prosedur Harian (Daily Runbook)
1. **Pukul 06.00**: Rekonsiliasi neraca massa dan energi batch tanur hari sebelumnya (`php artisan mining:run-smelter-balance`).
2. **Pukul 14.30**: Sinkronisasi kuotasi harga penutupan LME Cash & Forward.
3. **Pukul 15.00**: Eksekusi valuasi Mark-to-Market seluruh kontrak aktif (`php artisan mining:mtm-metals-trading`).
4. **Pukul 18.00**: Verifikasi manifest pengiriman terak slag ke SIMPEL KLHK.

### Prosedur Mingguan (Weekly Runbook)
1. Audit rantai kustodi ketertelusuran tanda tangan digital dan HMAC segel resi gudang (`AuditSmelterChainOfCustodyAction`).
2. Pemeriksaan fisik stok ingot logam di brankas gudang terakreditasi Bappebti.
3. Ekspor laporan operasional hilirisasi format CSV untuk laporan berkala ke Ditjen Minerba ESDM (`php artisan mining:export-fase121-csv`).
