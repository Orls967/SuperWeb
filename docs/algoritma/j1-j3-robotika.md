# Algoritma J1–J3: Robotika Gudang (`Wms\Robotics`)

## J1 — `AutomationRoiPlanner`
Menghitung kelayakan investasi automasi gudang secara deterministik berbasis bilangan bulat (tanpa `float`):
- `roi_bps = intdiv(annual_savings_idr * 10000, capex_idr)`
- `payback_months = ceil(capex_idr / intdiv(annual_savings_idr, 12))`
- Diverifikasi dengan `AutomationPlanCertificate` (SHA-256).

## J2 — `RobotTaskAllocator` (Reuse `HungarianSolver`)
Membangun matriks biaya integer `(jarak + penalti zona + penalti baterai)` lalu mendelegasikan penyelesaian penugasan optimal ke `Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver` beserta `AssignmentCertificateVerifier`.

## J3 — `WaveAutomationDispatcher` + `TrafficDeadlockDetector`
- Menentukan mode `automated`, `hybrid`, atau `manual_fallback` sesuai rasio robot siap pakai terhadap jumlah tugas wave.
- `TrafficDeadlockDetector` mendeteksi siklus pada wait-for graph antar unit AMR sebelum dispatch.

