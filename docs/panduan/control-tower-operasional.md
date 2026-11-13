# Panduan Operasional Control Tower & S&OP (Fase 53)

Panduan ini disusun untuk tim **Supply Chain Planner**, **Logistics Admin**, **Auditor**, serta **Administrator Sistem** guna mengoperasikan seluruh fitur halaman web, perintah Artisan CLI, ekspor laporan CSV, serta alur kerja kolaboratif harian pada modul `ControlTower`.

---

## 1. Matriks Rute Web & Otorisasi Peran (RBAC)

Seluruh rute dilindungi autentikasi `web`, `auth`, serta pemetaan otorisasi di `tests/Architecture/route-roles.php`:

| Rute HTTP | Nama Rute | Peran Pembaca / Aktor | Deskripsi Halaman / Aksi |
| :--- | :--- | :--- | :--- |
| `GET /control-tower` | `control_tower.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Ringkasan kokpit pasokan multi-eselon, KPI terbaru, dan alert aktif. |
| `GET /control-tower/promises` | `control_tower.promises` | `admin`, `logistics_admin`, `planner`, `auditor` | Janji Pesanan Konsumen: posisi ATP/CTP kumulatif & status pengiriman. |
| `POST /control-tower/promises/quote` | `control_tower.promises.quote` | `admin`, `logistics_admin`, `planner` | Evaluasi kuotasi janji ATP/CTP idempoten baru melalui antarmuka web. |
| `GET /control-tower/promises/export.csv` | `control_tower.promises.export` | `admin`, `logistics_admin`, `planner`, `auditor` | Ekspor daftar janji pesanan & kode alasan keterlambatan ke CSV. |
| `GET /control-tower/forecast` | `control_tower.forecast.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Perbandingan model I2 (SES, Holt, Croston), MAE backtest, & daftar override. |
| `POST /control-tower/forecast/select` | `control_tower.forecast.select` | `admin`, `logistics_admin`, `planner` | Menjalankan seleksi model permintaan otomatis I2 untuk SKU terpilih. |
| `POST /control-tower/forecast/override` | `control_tower.forecast.override` | `admin`, `logistics_admin`, `planner` | Mengajukan penyesuaian konsensus dengan alasan wajib. |
| `POST /control-tower/forecast/override/{override}/revert` | `control_tower.forecast.revert` | `admin`, `logistics_admin`, `planner` | Membalik override kembali ke nilai baseline proyeksi statistik. |
| `GET /control-tower/forecast/export.csv` | `control_tower.forecast.export` | `admin`, `logistics_admin`, `planner`, `auditor` | Ekspor tabel hasil proyeksi, MAE, dan status override ke CSV. |
| `GET /control-tower/safety-stock` | `control_tower.safety_stock.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Matriks ABC/XYZ 3×3 & rekomendasi *Multi-Echelon Safety Stock* I3. |
| `POST /control-tower/safety-stock/propose` | `control_tower.safety_stock.propose` | `admin`, `logistics_admin`, `planner` | Mengusulkan kebijakan safety stock baru berbasis statistik pemasok. |
| `POST /control-tower/safety-stock/{policy}/decide` | `control_tower.safety_stock.decide` | `admin`, `logistics_admin` | Menyetujui atau menolak usulan kebijakan safety stock (four-eyes). |
| `GET /control-tower/safety-stock/export.csv` | `control_tower.safety_stock.export` | `admin`, `logistics_admin`, `planner`, `auditor` | Ekspor parameter safety stock, ROP, dan kelas ABC/XYZ ke CSV. |
| `GET /control-tower/sop` | `control_tower.sop.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Papan siklus bulanan S&OP 7-status & log riwayat peristiwa. |
| `POST /control-tower/sop` | `control_tower.sop.store` | `admin`, `logistics_admin`, `planner` | Membuka siklus S&OP bulanan baru (`YYYY-MM`). |
| `POST /control-tower/sop/{cycle}/transition` | `control_tower.sop.transition` | `admin`, `logistics_admin`, `planner` | Melakukan transisi antar tahap S&OP sesuai aturan state machine. |
| `POST /control-tower/sop/{cycle}/consensus` | `control_tower.sop.consensus` | `admin`, `logistics_admin`, `planner` | Orkestrasi advance/rollback beralasan, task inbox, dan publikasi konsensus. |
| `GET /control-tower/sop/export.csv` | `control_tower.sop.export` | `admin`, `logistics_admin`, `planner`, `auditor` | Ekspor rekap siklus S&OP & estimasi margin ke CSV. |
| `GET /control-tower/alerts` | `control_tower.alerts.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Daftar peringatan disrupsi aktif & usulan realokasi antar-gudang. |
| `POST /control-tower/alerts/scan` | `control_tower.alerts.scan` | `admin`, `logistics_admin`, `planner` | Memicu pemindaian anomali disrupsi & emisi event idempoten. |
| `POST /control-tower/alerts/{alert}/mitigate` | `control_tower.alerts.mitigate` | `admin`, `logistics_admin`, `planner` | Menyetujui mitigasi atau menutup peringatan disrupsi. |
| `GET /control-tower/alerts/export.csv` | `control_tower.alerts.export` | `admin`, `logistics_admin`, `planner`, `auditor` | Ekspor log peringatan disrupsi ke CSV. |
| `GET /control-tower/risk` | `control_tower.risk.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Pemantauan *Order Risk Score* (X53-1) komponen 0..1000 per SKU/order. |
| `POST /control-tower/risk/scan` | `control_tower.risk.scan` | `admin`, `logistics_admin`, `planner` | Menjalankan evaluasi skor risiko pesanan baru. |
| `GET /control-tower/risk/export.csv` | `control_tower.risk.export` | `admin`, `logistics_admin`, `planner`, `auditor` | Ekspor matriks risiko pesanan ke CSV. |
| `GET /control-tower/suppliers` | `control_tower.suppliers.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Kartu skor kinerja pemasok (X53-2), tiering, dan tren bulanan. |
| `POST /control-tower/suppliers/refresh` | `control_tower.suppliers.refresh` | `admin`, `logistics_admin`, `planner` | Memperbarui skor pemasok dari `SupplierLeadTimeStatistics`. |
| `GET /control-tower/suppliers/export.csv` | `control_tower.suppliers.export` | `admin`, `logistics_admin`, `planner`, `auditor` | Ekspor kartu skor pemasok ke CSV. |
| `GET /control-tower/inventory-health` | `control_tower.inventory_health.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Analisis kesehatan stok (X53-3), aging bucket, & eksposur modal obsolet. |
| `POST /control-tower/inventory-health/refresh` | `control_tower.inventory_health.refresh` | `admin`, `logistics_admin`, `planner` | Memindai ulang snapshot kesehatan persediaan multi-eselon. |
| `GET /control-tower/inventory-health/export.csv` | `control_tower.inventory_health.export` | `admin`, `logistics_admin`, `planner`, `auditor` | Ekspor analisis kesehatan stok ke CSV. |
| `GET /control-tower/twin` | `control_tower.twin.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Workbench simulasi What-If Digital Twin bebas mutasi. |
| `POST /control-tower/twin/whatif` | `control_tower.twin.whatif` | `admin`, `logistics_admin`, `planner` | Menjalankan skenario kejut sandbox dengan pemeriksaan hash sebelum/sesudah. |
| `GET /control-tower/twin/export.csv` | `control_tower.twin.export` | `admin`, `logistics_admin`, `planner`, `auditor` | Ekspor skenario Digital Twin ke CSV. |
| `GET /control-tower/kpi` | `control_tower.kpi.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Dasbor KPI pasokan eksekutif & ringkasan snapshot harian. |
| `GET /control-tower/kpi/drilldown` | `control_tower.kpi.drilldown` | `admin`, `logistics_admin`, `planner`, `auditor` | Rincian per pesanan untuk perhitungan OTIF permil & Fill-Rate permil. |
| `GET /control-tower/kpi/export.csv` | `control_tower.kpi.export` | `admin`, `logistics_admin`, `planner`, `auditor` | Ekspor riwayat KPI eksekutif ke CSV. |

---

## 2. Daftar Perintah Artisan CLI

1. **`php artisan tower:atp-refresh {--item=} {--as-of=}`**
   - Menghitung ulang netting ATP/CTP horizon aktif berdasar posisi `ItemAvailabilityReader`, penerimaan MPS, dan komitmen pesanan terbuka.
2. **`php artisan tower:forecast-select {--item=} {--period=}`**
   - Menjalankan seleksi model otomatis I2 (`SES`, `HOLT`, `CROSTON`) secara idempoten per item/periode.
3. **`php artisan tower:safety-stock {--item=} {--node=}`**
   - Menghitung ulang rekomendasi *Multi-Echelon Safety Stock* dan ROP dengan statistik lead time pemasok riil (`SupplierLeadTimeStatistics`).
4. **`php artisan tower:risk-scan {--item=}`**
   - Mengevaluasi skor risiko pesanan `0..1000` (`OrderRiskScorer`) dan menyimpannya ke `sct_order_risk_scores`.
5. **`php artisan tower:alerts-scan`**
   - Memindai pelanggaran ambang stok pengaman, item risiko `CRITICAL`, serta keterlambatan in-transit, lalu menerbitkan alert terdeduplikasi serta event `tower.alert.raised.v1`.
6. **`php artisan tower:supplier-scorecard {--supplier=} {--period=}`**
   - Menghasilkan atau menyegarkan kartu skor pemasok (`sct_supplier_scorecards`) dan tren performa bulanan.
7. **`php artisan tower:whatif {scenario=PORT_CLOSURE} {--magnitude=2500} {--days=14}`**
   - Menjalankan simulasi What-If Digital Twin sandbox dengan jaminan nol mutasi tabel operasional nyata.
8. **`php artisan tower:kpi {--from=} {--to=}`**
   - Mengagregasi fakta pemenuhan pesanan dari modul `Distribution` menjadi snapshot KPI harian (`otif_bps`, `fill_rate`, `dos_days`, `cash_to_cash_days`).
9. **`php artisan tower:audit`**
   - Menjalankan pemeriksaan integritas menyeluruh Control Tower (exit code `0` saat bersih).

---

## 3. Siklus Operasional Bulanan Tim S&OP

1. **Minggu 1 (`data_gathering -> demand_review`):** Jalankan `tower:forecast-select` dan buka `/control-tower/forecast`. Tim komersial mengajukan override (`ForecastOverrideService`) disertai alasan tertulis.
2. **Minggu 2 (`supply_review`):** Planner meninjau laporan `tower:safety-stock`, kapasitas pabrik (`CapacityAndMaterialProvider`), dan kartu skor pemasok (`/control-tower/suppliers`). Bila ditemukan selisih besar yang tidak dapat ditangani, lakukan *rollback* ke `demand_review` dengan alasan terdokumentasi.
3. **Minggu 3 (`pre_sop -> exec_sop`):** Uji kejut kapasitas maupun penutupan pelabuhan di `/control-tower/twin`, evaluasi eksposur dead-stock di `/control-tower/inventory-health`, lalu kirim paket konsensus ke `ApprovalEngineInterface`.
4. **Minggu 4 (`published -> closed`):** Penyetuju eksekutif (berbeda dari pembuat siklus) mempublikasikan rencana final, menerbitkan event `tower.sop.published.v1`, lalu menutup siklus setelah bulan berjalan usai.


---

## 4. Playbook Operasional Harian, Mingguan, dan Bulanan

### 4.1 Siklus Harian (Perencana & Operator Logistik)
1. **02:30 WIB (Otomatis Scheduler):** `tower:kpi` dan `tower:supplier-scorecard` memperbarui metrik OTIF/Fill-Rate nyata dari `Distribution` serta meranking pemasok di `/control-tower/suppliers`.
2. **Setiap 15 Menit (`tower:alerts-scan`):** Sistem mengevaluasi keterlambatan transit dari `Logistics`, deviasi lead-time dari `Procurement`, dan shortfall `ATP`. Planner memantau tab `/control-tower/alerts` dan `/control-tower/risk`.
3. **Sepanjang Jam Kerja (Kuotasi Pesanan):** Tim Sales/Order Management memanggil `POST /control-tower/promises/quote` dengan header `Idempotency-Key` unik per permintaan pesanan; pengulangan permintaan dengan key & payload identik membalas respons asli tanpa menggandakan pengurangan kuota ATP.

### 4.2 Siklus Mingguan (Evaluasi Stok Pengaman & Kesehatan Inventori)
1. Buka `/control-tower/inventory-health` dan klik **Refresh Analisis** (atau `POST /control-tower/inventory-health/refresh`) untuk memetakan modal kerja terkunci pada item `DEAD_STOCK`, `SLOW_MOVING`, dan `SHORTAGE_RISK`.
2. Jalankan `php artisan tower:safety-stock` atau ajukan pembaruan kebijakan via `POST /control-tower/safety-stock/propose` agar algoritma `I3` menyesuaikan penyangga eselon terhadap pergeseran variabilitas pemasok (`sigma_days`).
3. Admin logistik independen meninjau `proposed_echelon_breakdown` dan memutuskan lewat `POST /control-tower/safety-stock/{policy}/decide` (**pembuat proposal dilarang menyetujui proposalnya sendiri**).

### 4.3 Siklus Bulanan S&OP Kolaboratif (7 Tahap Kanonik)
1. `data_gathering`: Jalankan `php artisan tower:forecast-select` agar `I2` menentukan model terbaik (`SES`, `HOLT`, `CROSTON`) per SKU aktif.
2. `demand_review`: Kumpulkan penyesuaian komersial melalui `/control-tower/forecast` dengan alasan minimal 5 karakter (`X53-4`).
3. `supply_review`: Uji kelayakan pasokan & kapasitas pabrik dengan membandingkan metrik dari `ManufacturingCapacityAndMaterialProvider`.
4. `pre_sop`: Jalankan skenario `PORT_CLOSURE` / `DEMAND_SURGE` pada `/control-tower/twin` untuk memverifikasi ketahanan margin sebelum konsensus.
5. `executive_sop` -> `consensus`: Panggil `POST /control-tower/sop/{cycle}/consensus` yang menciptakan tugas di `TaskInboxService`, melewati `ApprovalEngineInterface`, dan menerbitkan `tower.sop.published.v1`.
6. `closed`: Arsip siklus serta unduh bukti laporan CSV untuk keperluan audit internal.


---

## 5. Panduan Penggunaan Per Peran & Contoh Payload API/CLI

### 5.1 Matriks Hak Akses per Peran (`RBAC`)

| Peran Pengguna | Akses Baca & Ekspor CSV | Aksi Operasional (Quote, Select, Scan, What-If, Consensus) | Persetujuan Empat Mata (`safety_stock.decide`) | Audit & Kesehatan |
|---|---|---|---|---|
| `admin` | Penuh seluruh rute GET & CSV | Penuh semua POST operasional | Diizinkan (kecuali pengaju sendiri) | `tower:audit` & UI |
| `logistics_admin` | Penuh seluruh rute GET & CSV | Penuh semua POST operasional | Diizinkan (kecuali pengaju sendiri) | Pemantauan UI & CSV |
| `planner` | Penuh seluruh rute GET & CSV | Kuotasi ATP/CTP, forecast select/override, propose SS, scan alert/risk, S&OP consensus, twin what-if | Diblokir (`403 Forbidden`) | Pemantauan UI & CSV |
| `auditor` | Penuh seluruh rute GET & CSV | Diblokir total (`403 Forbidden`) — hanya baca (*read-only*) | Diblokir (`403 Forbidden`) | Audit & download CSV |

### 5.2 Contoh Payload Request & Respons JSON

1. **Kuotasi Janji Pesanan Idempoten (`POST /control-tower/promises/quote`):**
   - Header: `Idempotency-Key: QUOTE-SO-2026-0091`
   - Body JSON:
     ```json
     {
       "item_code": "SP-001",
       "quantity": 60,
       "requested_period": 1,
       "order_reference": "SO-2026-0091",
       "as_of_date": "2026-11-16"
     }
     ```
   - Respons `200 OK`: mengembalikan `promise_code`, `atp_confirmed_qty`, `ctp_manufacturing_qty`, `promised_period`, `late_periods`, `reason_code`, `certificate_hash`.
   - Pengiriman ulang `Idempotency-Key` sama dengan `quantity: 80` mengembalikan `409 Conflict` (`PromiseIdempotencyConflictException`).

2. **Simulasi What-If Digital Twin (`POST /control-tower/twin/whatif`):**
   - Body JSON:
     ```json
     {
       "scenario_code": "WIF-PORT-14D",
       "name": "Penutupan Pelabuhan 14 Hari",
       "shock_type": "PORT_CLOSURE",
       "shock_magnitude_bps": 2500,
       "duration_days": 14
     }
     ```
   - Respons `200 OK`: menyertakan `before_hash` dan `after_hash` yang identik (bukti nol mutasi tabel produksi Control Tower) bersama `baseline_otif_bps`, `simulated_otif_bps`, `at_risk_orders_count`, serta `estimated_margin_delta_idr`.

3. **Pengajuan & Keputusan Safety Stock Empat Mata:**
   - Tahap 1 (`planner`): `POST /control-tower/safety-stock/propose` dengan `{ "item_code": "SP-001", "node_weights": { "PLANT": 5, "DC": 3, "OUTLET": 2 } }`. Kebijakan aktif tidak berubah; baris menyimpan `proposed_safety_stock_qty` & `status = "proposed"`.
   - Tahap 2 (`logistics_admin` berbeda): `POST /control-tower/safety-stock/{policy}/decide` dengan `{ "decision": "approve", "decision_notes": "Disetujui sesuai variabilitas GRN Q4" }`. Nilai aktif dan `EchelonStock` ikut diperbarui sekaligus secara transaksional.

4. **Perintah CLI Operasional Cepat:**
   - `php artisan tower:forecast-select --item=SP-001 --period=2026-11`
   - `php artisan tower:atp-refresh --item=SP-001 --date=2026-11-14`
   - `php artisan tower:risk-scan --as-of=2026-11-14`
   - `php artisan tower:alerts-scan --as-of=2026-11-14`
   - `php artisan tower:supplier-scorecard --period=2026-11`
   - `php artisan tower:kpi --from=2026-10-01 --to=2026-11-14`
   - `php artisan tower:audit`
