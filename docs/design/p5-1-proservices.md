# Design Specification - P5.1 Lini 25 Jasa Profesional (ProServices)

## Keputusan Arsitektur & Bisnis
- **D25-1**: Modul ProServices baru (`Modules\ProServices`). Sengketa & legal ops tetap di Contract, tidak diduplikasi.
- **D25-2**: Siklus proyek: Party klien -> ContractLookupInterface -> tipe T&M / fixed fee -> anggota (K12 EmployeeDirectory) & rate card -> timesheet harian (append-only) -> approval PM -> invoice -> pengakuan pendapatan.
- **D25-3**: Revenue recognition:
  - Fixed Fee: CostToCostRecognition kumulatif catch-up.
  - T&M: Pendapatan = jam disetujui x tarif.
- **D25-4**: Double-entry ledger integration dengan contract asset (`psv:contract_asset:{project}:IDR`) dan contract liability (`psv:contract_liability:{project}:IDR`).
- **D25-5**: Command `psv:audit` independen memvalidasi data relasional terhadap ledger posting.
