# ADR-279-B: Sertifikat Kriptografis Kanonik untuk Algoritma Domain Robotika

- **Status**: Diterima (2026-11-13)
- **Terkait**: `ADR-279-wms-robotics.md`
- **Lingkup**: `modules/Wms/Domain/Certificates/*` & `modules/Wms/Domain/Algorithms/*`

## 1. Konteks Masalah
Sistem robotika gudang mengambil keputusan berisiko tinggi: mengalokasikan ruang gerak fisik (`ZoneReservationScheduler`), mengelompokkan pesanan pelanggan (`WaveBinPacker`), menyetujui investasi CAPEX bernilai miliaran (`AutomationNpvCalculator`), memutus misi saat deadlock (`TrafficDeadlockDetector`), serta menghitung cadangan energi baterai (`RobotEnergyConsumptionEstimator`, `RobotBatteryDegradationTracker`).

Tanpa verifikasi integritas struktural atas keluaran algoritma, kegagalan serialisasi, modifikasi tak sengaja di layer aplikasi, atau pemalsuan payload dapat menyebabkan pelanggaran keselamatan maupun kerugian finansial.

## 2. Penggerak Keputusan
1. **Verifiable Determinism**: Setiap eksekusi algoritma harus dapat diaudit ulang dari `canonicalInput` dan `steps` hingga menghasilkan `result` serta hash SHA-256 yang sama persis.
2. **Fail-Closed Verification**: Pemalsuan satu field saja pada hasil sertifikat wajib terdeteksi secara sinkron (`InvalidArgumentException`) sebelum data disimpan ke database.
3. **Zero Floating-Point Ambiguity**: Serialisasi JSON kanonik (`JSON_THROW_ON_ERROR`) hanya boleh berisi tipe integer, boolean, string, dan array terstruktur—tanpa `float` yang berisiko berubah representasi desimalnya.

## 3. Daftar Sertifikat & Invarian yang Diverifikasi
| Sertifikat | Algoritma Induk | Invarian Utama yang Diuji di `verify()` / `verifyOrFail()` |
|---|---|---|
| `ZoneReservationCertificate` | `ZoneReservationScheduler` (`J1`) | Konsistensi hash SHA-256 `(canonicalInput, steps, result)` dan validitas interval `[start, end)` |
| `WaveBinPackerCertificate` | `WaveBinPacker` (`J2`) | Konservasi 100% ID order (nol hilang, nol duplikat) dan hash SHA-256 kanonik |
| `AutomationNpvCertificate` | `AutomationNpvCalculator` (`J3`) | Konsistensi nilai `npv`, `irr_bps`, `payback_months` terhadap hash SHA-256 |
| `TrafficDeadlockCertificate` | `TrafficDeadlockDetector` | Konsistensi `has_deadlock`, `cycle_nodes`, `victim_node` terhadap graf tunggu masukan |
| `RobotEnergyCertificate` | `RobotEnergyConsumptionEstimator` | Penjumlahan `totalDrainPct` & `totalEnergyMwh`, konsistensi `projectedEndBatteryPct`, aturan `allFeasible`, dan `hash_equals` |
| `BatteryDegradationCertificate` | `RobotBatteryDegradationTracker` | Kecocokan `totalRobots`, `replacementCount`, `fleetAvgHealthPermil`, dan `hash_equals` |

## 4. Opsi yang Dipertimbangkan
1. **Opsi A — Hanya Mengembalikan Array Asosiatif Biasa**: Cepat ditulis, namun tidak ada jaminan integritas antara perhitungan Domain dan penyimpanan di Application Action; mudah rusak diam-diam jika ada bug transformasi.
2. **Opsi B — Value Object Sertifikat Readonly + SHA-256 Kanonik (Dipilih)**: Memberikan bukti audit deterministik, memudahkan uji sabotase (L.5: memalsukan satu field sertifikat langsung melempar `InvalidArgumentException`), dan selaras dengan `HungarianSolver` (`AssignmentCertificateVerifier`).

## 5. Konsekuensi & Mitigasi
- **Positif**: Uji sabotase pada `RoboticsAlgorithmsVectorTest` membuktikan seluruh 6 jenis sertifikat menolak payload yang dimanipulasi.
- **Mitigasi Biaya**: Komputasi `sha256(json_encode(...))` berukuran kecil (< 10 KB per eksekusi) sehingga latensi per sertifikat berada di bawah 0,1 ms.
