# Matriks Penegakan Invarian & Aturan Bisnis — Fase 93

Dokumen ini memetakan seluruh invarian teknis dan aturan bisnis Fase 93 terhadap lokasi file dan baris kode produksi aktual di dalam `modules/Mining/**` dan `modules/Shared/**`.

---

## 1. Tabel Penegakan Invarian Kode Produksi

| ID Invarian | Deskripsi Singkat Invarian | File Implementasi Kode | Kelas / Metode Penegak | Mekanisme Pertahanan & Verifikasi |
| :--- | :--- | :--- | :--- | :--- |
| **INV-93-01** | Konservasi total tonase alokasi shift harian wajib sama persis dengan kuota rencana bulanan | `modules/Shared/Domain/Algorithms/Scheduling/WeightedTimePhasingCertificateVerifier.php` | `WeightedTimePhasingCertificateVerifier::verify` | Sertifikat kriptografis; exception `InvalidArgumentException` jika $\sum q_i \ne Q$ |
| **INV-93-02** | Alokasi truk aktif per shift tidak boleh melebihi jumlah unit fisik yang berstatus available/assigned | `modules/Mining/Domain/Algorithms/NeedTimeTruckDispatcherCertificateVerifier.php` | `NeedTimeTruckDispatcherCertificateVerifier::verify` | Verifikasi batas kapasitas; tolak penugasan jika $N_{active} > N_{avail}$ |
| **INV-93-03** | Penahanan dana tagihan kontraktor akibat anomali BBM tepat satu kali (anti-double-dip) | `modules/Mining/Application/Sagas/FuelAnomalyHoldSaga.php` | `FuelAnomalyHoldSaga::executeHold` | Idempotency key unik DB `min:fuel_hold:{case_id}` & status transition lock |
| **INV-93-04** | Saldo akun buku besar `min:contractor_payment_hold:IDR` wajib seimbang dengan total record HELD | `modules/Mining/Application/Services/MiningFleetDispatchService.php` | `MiningFleetDispatchService::auditMining` | Pengecekan dua sumber; exit code 1 bila ada diskrepansi saldo |
| **INV-93-05** | Rekursi dan siklus state machine armada wajib append-only dan melarang status melompat | `modules/Mining/Domain/StateMachines/EquipmentStateMachine.php` | `EquipmentStateMachine::transition` | Exception `InvalidMiningStateTransitionException` & append-only log |
| **INV-93-06** | Estimasi kadar nikel voxel 3D wajib berada dalam convex hull titik sampel borehole | `modules/Mining/Domain/Algorithms/InverseDistanceWeightingCertificateVerifier.php` | `InverseDistanceWeightingCertificateVerifier::verify` | Uji batas numerik $\min \le Z(x) \le \max$ & pembuktian total bobot $10.000\text{ bps}$ |
| **INV-93-07** | Kondisi Kriging tidak bias (unbiasedness constraint $\sum \lambda_i = 1$) dan varians non-negatif | `modules/Shared/Domain/Algorithms/Geo/OrdinaryKriging3DCertificateVerifier.php` | `OrdinaryKriging3DCertificateVerifier::verify` | Verifikasi pengali Lagrange dan matriks kovarians semi-variogram bola |
| **INV-93-08** | Monitoring keselamatan suhu ban haul truck TKPH tidak boleh melampaui rating pabrikan | `modules/Mining/Application/Actions/RecordTireTemperaturePressureAction.php` | `RecordTireTemperaturePressureAction::execute` | Evaluasi suhu kritis $> 95^\circ\text{C}$ dan pembatasan kecepatan tempuh |
| **INV-93-09** | Validasi volume dispense solar tidak boleh melebihi sisa fisik di tangki penyimpanan | `modules/Mining/Application/Actions/DispenseFuelToEquipmentAction.php` | `DispenseFuelToEquipmentAction::execute` | Database row-level locking (`lockForUpdate`) dan verifikasi kuota |
| **INV-93-10** | Isolasi multi-tenant portal mandiri kontraktor untuk mencegah celah IDOR antar-perusahaan | `modules/Mining/Http/Controllers/MiningContractorPortalController.php` | `MiningContractorPortalController::index` | Filter strictly scoped pada `contractor_party_ref == Auth::id()` |

---

## 2. Register Sumber Daya Regulasi Eksternal (T-8)
1. **PP No. 19 Tahun 2025**: Ketentuan tarif royalti berjenjang mineral dan batubara (efektif 26 April 2025).
2. **Kepmen ESDM No. 1827 K/30/MEM/2018**: Pedoman Kaidah Teknik Pertambangan yang Baik (*Good Mining Practice*).
3. **Kepmen ESDM No. 72.K/MB.01/MEM.B/2025**: Penetapan Harga Batubara Acuan (HBA) dan Harga Patokan Mineral (HPM).
4. **SNI 4726:2019**: Pedoman Pelaporan Hasil Eksplorasi, Sumber Daya, dan Cadangan Batubara dan Mineral.

