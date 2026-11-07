# Panduan Operasional Peran: Mine Manager (`mine_manager`) — Fase 93

## 1. Tanggung Jawab Utama
Mine Manager mengawasi efektivitas operasional seluruh site tambang, memastikan pencapaian target produksi bulanan pada kurva-S, mengendalikan ketersediaan armada berat melalui metrik OEE, dan menyetujui langkah mitigasi bila terjadi kendala besar.

## 2. Alur Tugas & Prosedur Harian
1. **Monitoring Armada & OEE**:
   - Buka menu **Heavy Equipment** di `/mining/equipment`.
   - Periksa status operasional armada (Available, Assigned, Operating, Standby, Under Maintenance, Breakdown).
   - Tinjau indikator OEE (Ketersediaan, Kinerja, Kualitas) dan nilai pembacaan sensor telemetri terakhir (suhu engine, tekanan oli hidrolik).

2. **Evaluasi Six Big Losses (CLI Scan)**:
   - Jalankan pemindaian OEE benchmark:
     ```bash
     php artisan mining:oee-scan
     ```
   - Tinjau klasifikasi armada (World Class $\ge 85\%$, Acceptable, Unsatisfactory) dan penyebab utama kerugian waktu kerja (breakdown, setup, idling, reduced speed).

3. **Meninjau Ekspor Laporan Produksi**:
   - Buka `/mining/reports/export?type=production_summary` untuk mengunduh rekapitulasi data shift harian dalam format CSV.


---

## 4. Evaluasi Biaya per BCM, Keandalan MTBF/MTTR, dan Kesehatan Ban TKPH
Melalui komponen komposit `Modules\Mining\Http\ViewModels\MiningFase93OperationsViewModel` yang terhubung ke halaman `/mining/equipment` dan `/mining/plans`, **Mine Manager** memantau tiga indikator keekonomian dan keselamatan tambahan:
1. **Struktur Biaya Kupasan Tanah Penutup (`MiningCostPerBcmCalculator`)**:
   - Menampilkan *Stripping Ratio* dalam basis points (`stripping_ratio_bps`, di mana $32.000\text{ bps} = 3,20\times$) serta porsi biaya pengupasan overburden ($70\%$) dibanding biaya penambangan bijih ($30\%$).
2. **Keandalan Mekanikal Armada (`EquipmentTelemetryAnalyticsService`)**:
   - Menampilkan nilai *Mean Time Between Failures* (`mtbf_hours`), *Mean Time To Repair* (`mttr_hours`), serta skor kesehatan unit (`health_score_bps`) yang diturunkan dari frekuensi eksekusi `PredictiveMaintenanceWorkOrderSaga` dan getaran puncak `vibration_rms_scaled`.
3. **Batas Termal Ban Haul Truck (`RecordTireTemperaturePressureAction`)**:
   - Mengawasi indeks *Ton-Kilometer Per Hour* (`tkph_scaled`) dan mendeteksi peringatan `ALERT_HOT_TIRE` apabila suhu ban $\ge 95^\circ\text{C}$ atau tekanan angin berada di luar rentang $[90, 130]\text{ psi}$.
