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
