# ADR-0093: Mine Planning, Fleet Dispatch, and Fuel Anomaly Management Architecture

## Status
Accepted

## Konteks
Operasi pertambangan (Pilar 12F) memerlukan kepastian deterministik dalam pemecahan target produksi tambang bulanan ke shift harian, penugasan armada truk ke titik shovel, serta pengendalian konsumsi bahan bakar alat berat kontraktor dengan pengawalan tata kelola keuangan berbasis double-entry ledger. Standar W8 menuntut integritas data append-only, algoritma optimasi bersertifikat yang dapat diverifikasi mesin secara independen, serta saga kompensasi untuk setiap transaksi lintas modul.

## Keputusan Arsitektur

### 1. Satuan dan Nilai Skalar (G7-D25)
- Seluruh tonase disimpan dalam integer kilogram (`weight_kg`, di mana $1\text{ ton} = 1.000\text{ kg}$).
- Jam operasional disimpan dalam detik integer (`duration_seconds`).
- Jarak dalam meter integer (`distance_meters`).
- Volume bahan bakar dalam mililiter integer (`fuel_ml`, $1\text{ liter} = 1.000\text{ ml}$).
- Konsumsi BBM spesifik diukur dalam basis point integer ($1\text{ bps} = 0,01\%$) deviasi terhadap garis dasar empiris.
- Perhitungan rasional dan pemecahan pecahan menggunakan `Modules\Shared\Domain\Math\BigRational` dan `LargestRemainder` tanpa pembulatan mengambang (anti-float).

### 2. Time-Phasing Rencana Tambang (D93-1, Algoritma M1)
- Rencana bulanan (`min_mine_plans`) dipecah ke alokasi harian dan shift melalui kelas `Modules\Shared\Domain\Algorithms\Scheduling\WeightedTimePhasing`.
- Jam efektif shift diturunkan dari ketersediaan nominal yang dikurangi durasi hujan ekstrem (via `MiningExternalFactors`) dan durasi perawatan terencana.
- State Machine `MinePlan` memiliki 8 status siklus hidup: `draft` -> `submitted` -> `reviewed` -> `approved` -> `active` -> `revised` -> `closed` -> `cancelled`. Setiap revisi menghasilkan nomor versi baru tanpa menimpa baris sebelumnya.

### 3. Dispatch Armada dan Match Factor (D93-2, Algoritma M2)
- Penugasan truk berikutnya ke shovel dilakukan oleh `Modules\Mining\Domain\Algorithms\NeedTimeTruckDispatcher`.
- Logika penugasan memprioritaskan waktu mulai pemuatan paling awal ($\max(\text{waktu tiba}, \text{waktu shovel bebas})$), dengan pemecah seri (tie-break) memprioritaskan shovel yang telah menganggur lebih lama, lalu ID shovel terkecil.
- Match Factor dihitung secara deterministik:
  $$\text{MF} = \frac{N_{\text{truk}} \times T_{\text{muat}}}{N_{\text{shovel}} \times T_{\text{siklus}}}$$
  Jika $\text{MF} < 1$, sistem menerbitkan status undertrucked beserta rekomendasi penambahan truk.
- Siklus angkut dikelola oleh State Machine `HaulCycle` (8 status): `queued` -> `assigned` -> `travelling_empty` -> `loading` -> `hauling` -> `dumping` -> `completed` -> `aborted`.
- Setiap intervensi manual dispatcher (`dispatcher_override`) wajib menyertakan alasan tertulis dan tercatat di riwayat audit append-only.

### 4. Telemetri dan Status Alat Berat (D93-3)
- Ingest telemetri alat berat (`min_equipment_telemetries`) bersifat idempoten berbasis pasangan kunci unik `(equipment_id, sequence_number)`.
- Metrik efektivitas peralatan keseluruhan (OEE) dihitung per shift:
  $$\text{OEE} = \text{Availability} \times \text{Performance} \times \text{Quality}$$
  dan dinyatakan dalam basis point integer (bps).
- Siklus hidup alat berat dikontrol oleh State Machine `Equipment` (8 status): `available` -> `assigned` -> `operating` -> `standby` -> `breakdown` -> `under_maintenance` -> `returned_to_service` -> `retired`.
- Seluruh unit alat berat terdaftar secara tunggal di `min_equipment` dan memvalidasi keberadaan aset fisik ke modul Asset melalui `AssetRegistryLookupInterface` (G7-D29).

### 5. Saga Perawatan Prediktif dan Suku Cadang (D93-4)
- Jika pembacaan telemetri (jam mesin akumulasi atau intensitas getaran) melampaui batas ambang kritis:
  1. Langkah 1: Terbitkan Work Order perawatan prediktif ke modul Asset via `G7-K9` (`MaintenanceWorkOrderRequester`).
  2. Langkah 2: Buat usulan requisition suku cadang ke modul Procurement via `MrpRequisitionProposer`.
  3. Langkah 3: Sesuaikan target shift pit karena adanya estimasi downtime alat berat.
- Cabang Kompensasi: Jika Work Order dibatalkan atau ditolak, langkah kompensasi membatalkan usulan MRP requisition dan memulihkan kuota target shift ke kondisi semula.

### 6. Deteksi Anomali BBM dan Penahanan Pembayaran Kontraktor (D93-5, Algoritma M3)
- Garis dasar konsumsi bahan bakar dihitung menggunakan regresi linier kuadrat terkecil rasional (`Modules\Shared\Domain\Algorithms\Stats\LinearBaselineRegression`):
  $$\text{Liter Prediksi} = a + b \times \text{ton-km}$$
- Kelebihan konsumsi aktual terhadap prediksi dinyatakan dalam bps:
  $$\text{Excess Bps} = \left\lfloor \frac{\text{Aktual} - \text{Prediksi}}{\text{Prediksi}} \times 10.000 \right\rfloor$$
- Deviasi melebihi ambang batas regulasi parameter (1.500 bps) menandai kasus anomali (`FuelAnomalyCase`).
- State Machine `FuelAnomalyCase` (8 status): `detected` -> `verifying` -> `confirmed` -> `payment_held` -> `disputed` -> `resolved_deducted` -> `resolved_released` -> `written_off`.
- Tindakan penahanan pembayaran kontraktor membukukan jurnal ledger:
  - Debit: `ap:min:contractor:{party}:IDR`
  - Kredit: `min:contractor_payment_hold:IDR`
- Jika anomali terbukti pencurian/pemborosan, dana yang ditahan dieksekusi menjadi pemulihan varians:
  - Debit: `min:contractor_payment_hold:IDR`
  - Kredit: `min:fuel_variance_recovery:IDR`

### 7. Kepekaan Faktor Eksternal (G7-D30, T-5)
- Faktor cuaca (curah hujan milimeter), harga acuan komoditas (HBA/HMA), kurs mata uang, dan kebijakan ekspor dibaca secara terpusat melalui gerbang `Modules\Mining\Domain\Services\MiningExternalFactors` dari tabel parameter berversi `min_external_factors`.
- Curah hujan ekstrem di atas ambang batas secara otomatis menurunkan ketersediaan jam kerja efektif shift dan memicu `RePlanShiftTargetsAction`.

## Konsekuensi
- Menjamin konsistensi transaksi keuangan antara tagihan kontraktor, operasional lapangan, dan buku besar akuntansi.
- Mencegah kehilangan bahan bakar (fuel theft) dan ketidakseimbangan alokasi truk-shovel.
- Memenuhi seluruh kriteria batas bawah kode produksi (B1 $\ge 6.000$ baris) dan dokumentasi (B3 $\ge 1.500$ baris) sesuai spesifikasi W8.

