# Runbook Operasional Modul Pertambangan (Mining)

Runbook ini memuat panduan eksekusi perintah terjadwal (cron/schedule), command operasional manual, penanganan kegagalan, dan prosedur pemulihan (disaster recovery) untuk modul Pertambangan.

---

## 1. Daftar Artisan Commands & Jadwal Eksekusi

### 1.1 `mining:phase-plan {month}`
- **Tujuan**: Memecah target produksi bulanan ke dalam alokasi shift harian berdasarkan ketersediaan jam efektif (M1 Weighted Time Phasing).
- **Jadwal Eksekusi**: Bulanan, setiap tanggal 28 pukul 23:00 UTC (menjelang bulan baru).
- **Idempotensi**: Idempoten penuh. Jika dijalankan ulang untuk bulan yang sama, rencana yang sudah `active` tidak ditimpa; hanya shift yang belum dimulai yang disinkronkan.
- **Perintah Eksekusi**:
  ```bash
  php artisan mining:phase-plan 2026-11
  ```
- **Pemulihan Bila Gagal**: Periksa apakah tabel `min_external_factors` memiliki data jam hujan untuk site tersebut. Jika kosong, isi parameter default.

### 1.2 `mining:ingest-telemetry`
- **Tujuan**: Memproses batch telemetri IoT armada alat berat (suhu engine, tekanan hidrolik, jam operasi, getaran) secara idempoten.
- **Jadwal Eksekusi**: Setiap 5 menit (`*/5 * * * *`).
- **Idempotensi**: Idempoten melalui unique constraint `(equipment_id, sequence_number)` pada tabel `min_equipment_telemetries`.
- **Perintah Eksekusi**:
  ```bash
  php artisan mining:ingest-telemetry
  ```
- **Pemulihan Bila Gagal**: Periksa konektivitas MQTT/IoT broker dan keutuhan format payload JSON.

### 1.3 `mining:shift-close {date} {shift}`
- **Tujuan**: Menutup shift kerja tambang, merekonsiliasi total tonase siklus hauling, dan membukukan tagihan kontraktor angkut ke buku besar.
- **Jadwal Eksekusi**: 3 kali sehari di akhir shift:
  - Shift 1: 15:15 WITA (`07:15 UTC`)
  - Shift 2: 23:15 WITA (`15:15 UTC`)
  - Shift 3: 07:15 WITA (`23:15 UTC`)
- **Idempotensi**: Idempoten melalui idempotency key `min:shift_bill:{pit_id}:{shift_date}:{shift_code}` pada ledger posting.
- **Perintah Eksekusi**:
  ```bash
  php artisan mining:shift-close 2026-11-07 SHIFT_1
  ```
- **Pemulihan Bila Gagal**: Periksa apakah semua siklus hauling telah berstatus `COMPLETED` atau `ABORTED`. Jika ada siklus yang menggantung di status `LOADING`, gunakan menu override dispatcher sebelum menjalankan ulang command.

### 1.4 `mining:fuel-anomaly-scan {date}`
- **Tujuan**: Memindai seluruh pemakaian solar alat berat harian menggunakan regresi linier baseline M3, mendeteksi kasus deviasi $> 15\%$, dan memicu saga penahanan dana kontraktor (hold).
- **Jadwal Eksekusi**: Harian, pukul 01:00 UTC.
- **Idempotensi**: Idempoten melalui case number unik `FAC-{shift_date}-{equipment_code}`.
- **Perintah Eksekusi**:
  ```bash
  php artisan mining:fuel-anomaly-scan 2026-11-07
  ```

### 1.5 `min:audit` (atau `mining:audit`)
- **Tujuan**: Audit dua sumber untuk memverifikasi konsistensi status armada, keutuhan hash-chain timbangan, kesetimbangan saldo hold bahan bakar terhadap buku besar, dan deteksi anomali tanpa bukti.
- **Jadwal Eksekusi**: Harian sebelum settlement dan otomatis di CI gate.
- **Perintah Eksekusi**:
  ```bash
  php artisan min:audit
  ```
- **Tindakan Bila Exit Code $\ne 0$**: Tinjau pesan error pada tabel audit. Jika saldo hold tidak seimbang, periksa apakah ada manipulasi langsung pada tabel `min_contractor_payment_holds` tanpa melalui `FuelAnomalyHoldSaga`.


---

### 1.6 `mining:estimate-reserve {pit_id} {--cutoff-ppm=12000} {--variance-bps=500}`
- ** Kelas Pelaksana**: `Modules\Mining\Console\Commands\BlockReserveEstimateCommand` $\to$ `Modules\Mining\Application\Actions\EstimateBlockReserveAction`.
- **Tujuan**: Melakukan estimasi cadangan bijih 3D pada grid blok voxel (`min_block_model_voxels`) berdasarkan data kadar lubang bor (`min_borehole_assays`) di pit terkait menggunakan algoritma `InverseDistanceWeightingInterpolator` dan `OrdinaryKriging3D`.
- **Jadwal Eksekusi**: Mingguan setiap Senin pukul 02:00 UTC atau setiap kali kampanye pemboran eksplorasi baru selesai diunggah.
- **Idempotensi**: Idempoten per koordinat `(pit_id, voxel_code)` melalui operasi `updateOrCreate` pada tabel `min_block_model_voxels`.
- **Perintah Eksekusi**:
  ```bash
  php artisan mining:estimate-reserve PIT-KLP-01 --cutoff-ppm=12000 --variance-bps=500
  ```
- **Pemulihan Bila Gagal**:
  - Jika muncul pesan `Tidak ada data borehole assay untuk pit ini`, pastikan minimal terdapat $\ge 4$ titik sampel tak-kolinear pada tabel `min_borehole_assays` untuk `pit_id` tersebut.
  - Jika matriks Kriging singular akibat dua titik bor memiliki koordinat $(x, y, z)$ identik, bersihkan duplikasi koordinat sampel bor sebelum menjalankan ulang command.

### 1.7 `mining:oee-scan {site_code?}`
- **Kelas Pelaksana**: `Modules\Mining\Console\Commands\OeeBenchmarkScanCommand` $\to$ `Modules\Mining\Application\Services\FleetOeeRankingService`.
- **Tujuan**: Menghitung peringkat *Overall Equipment Effectiveness* (OEE = Availability $\times$ Performance $\times$ Quality dalam basis points `0..10000`) untuk seluruh truk angkut (`HAUL_TRUCK`) berdasarkan fakta siklus angkut (`min_haul_cycles`) dan rencana shift (`min_mine_plan_shifts`).
- **Jadwal Eksekusi**: Harian setiap pukul 06:00 UTC.
- **Idempotensi**: Bersifat *read-only* murni dari tabel fakta; aman dijalankan berulang kali kapan saja tanpa mengubah state database.
- **Perintah Eksekusi**:
  ```bash
  php artisan mining:oee-scan SITE-KALTIM-01
  ```

---

## 2. Prosedur Penanganan Insiden & Pemulihan Saga (Fase 93)

### 2.1 Pemulihan Saga Perawatan Prediktif (`PREDICTIVE_MAINTENANCE`)
Ketika `IngestEquipmentTelemetryAction` mendeteksi `engine_hours_total > 5000` atau `vibration_rms_scaled > 1800`, `PredictiveMaintenanceWorkOrderSaga` mengeksekusi tiga langkah berurutan yang dicatat pada tabel `min_mining_sagas`:
1. `STEP_1_WORK_ORDER`: Memanggil kontrak `Modules\Asset\Contracts\MaintenanceWorkOrderRequester::request` dengan kunci idempotensi `min:wo:{equipment_id}:{shift_date}:{shift_code}`.
2. `STEP_2_PARTS_REQUISITION`: Memanggil kontrak `Modules\Procurement\Contracts\MrpRequisitionProposer::proposeRequisition` untuk suku cadang kritis (`SP-BEARING-777D`).
3. `STEP_3_DOWNTIME_ADJUSTMENT`: Mengalihkan status unit di `min_equipment` melalui `EquipmentStateMachine::transitionToMaintenance` (`breakdown` $\to$ `under_maintenance`) dan memotong jam efektif shift di `min_mine_plan_shifts`.

**Langkah Pemulihan Jika Saga Berstatus `COMPENSATED_FAILED`**:
- Periksa kolom `compensation_log_json` pada tabel `min_mining_sagas` untuk membaca pesan kegagalan dari modul Asset atau Procurement.
- Pastikan unit alat berat telah dikembalikan secara otomatis ke status `available` oleh metode kompensasi `EquipmentStateMachine::restoreFromMaintenance`.
- Setelah gangguan pada layanan tujuan teratasi, jalankan ulang `php artisan mining:ingest-telemetry` atau kirim ulang payload telemetri dengan nomor urut baru untuk memicu pembuatan Work Order pengganti.

### 2.2 Rekonsiliasi Ketidakcocokan Buku Besar Penahanan BBM (`min:contractor_payment_hold:IDR`)
Apabila `php artisan min:audit` melaporkan ketidakcocokan antara total kolom `hold_amount_idr` berstatus `HELD` di tabel `min_contractor_payment_holds` dan saldo buku besar `min:contractor_payment_hold:IDR`:
1. Identifikasi nomor referensi hold (`hold_reference`) yang belum memiliki pasangan ayat jurnal di tabel `bank_ledger_entries` dengan memeriksa `idempotency_key` (`min:fuel-hold:{case_id}`).
2. Gunakan `MiningFase93LedgerPostingService::postContractorFuelHold` untuk membukukan ulang entri yang terlewat secara idempoten, atau jalankan penyelesaian sengketa melalui `/mining/fuel` (`MiningFuelController::resolve`).
3. Jalankan kembali `php artisan min:audit` dan pastikan nilai `Discrepancies` kembali ke `0` (exit code `0`).
