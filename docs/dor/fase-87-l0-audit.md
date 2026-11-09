# L.0 Bukti Kondisi Lama — Fase 87 (Rumah Sakit I)

## 1. Inventaris Kondisi Lama

| Komponen | Status | Penjelasan & Rencana |
|---|---|---|
| `hsp_patients` | Benar Sebagian | Sudah ada MRN, name, passport_hash. Belum ada blind_index_nik, party_id, KYC link, serta enkripsi payload terstruktur. Perlu diperdalam via migrasi aditif. |
| `hsp_encounters` | Benar Sebagian | Sudah ada tipe encounter dan status dasar. Perlu sinkronisasi dengan episode billing dan saga admisi. |
| `hsp_admissions` | Tidak Ada | Belum ada tabel terpisah untuk siklus hidup admisi 8 status. Wajib dibuat migrasi `hsp_admissions` dan `hsp_admission_transitions`. |
| `hsp_beds` | Benar Sebagian | Sudah ada tabel ranjang dasar (VIP, CLASS_1-3, ICU, HDU, ISOLATION), namun enum status belum lengkap 7 status dan belum ada riwayat transisi `hsp_bed_transitions`. |
| `hsp_orders` (Clinical Pathway) | Benar Sebagian | Sudah ada order dasar, namun belum mencakup 7 status ClinicalOrder, delay detector multi-tahap, dan riwayat transisi. |
| `hsp_code_blue_events` | Tidak Ada | Baru ada kolom boolean di `hsp_vitals_telemetries`. Perlu tabel event eksplisit 7 status dengan riwayat eskalasi tim. |
| `hsp_external_factors` | Tidak Ada | Belum ada tabel parameter berversi untuk DBD/ISPA/bencana/tarif. Wajib dibuat migrasi. |
| Algoritma B1–B5 | Tidak Ada | `BedAssignmentOptimizer`, `EarlyWarningScoreCalculator`, `ClinicalPathwayDelayDetector`, `BedEfficiencyIndicators`, `CodeBlueEscalationPlanner` belum ada. Wajib diimplementasikan bersertifikat. |
| State Machines (Admission, Bed, Order, CodeBlue) | Tidak Ada | Belum ada kelas StateMachine eksplisit dengan transisi append-only dan guard ketat. Wajib dibuat. |
| Sagas (Admission, Discharge & Cleaning) | Tidak Ada | Belum ada saga 2-fase dengan kompensasi otomatis. Wajib dibuat. |
| Rute & UI (/hospital/beds, /er, /nursing, /critical-care, /patients/{id}/passport, /efficiency) | Tidak Ada | Hanya ada `/hospital` dan sub-rute rawat jalan lama. Halaman spesifik Fase 87 dan tampilan 375 px belum ada. |
| Commands (`hospital:bed-sweep`, `hospital:pathway-scan`, `hospital:efficiency-report`, `hospital:telemetry-scan`) | Tidak Ada | Belum ada command terjadwal tersebut. |
| Audit `hsp:audit` dua sumber | Benar Sebagian | Sudah ada audit rawat jalan dan uji klinis, namun belum mengaudit invarian ranjang, ledger bed-day, code blue hash, dan paspor kesehatan. |

## 2. Keputusan L.0 per Kelas Eksisting
- `HospitalEmrAndBedService`: Dipakai ulang dan diperdalam sebagai fasad koordinasi, mendelegasikan aksi bisnis berat ke Action-action baru di `Modules\Hospital\Application\Actions\Care\`.
- `HospitalAccounts`: Dipakai penuh tanpa modifikasi karena akun `hsp:patient_deposit_escrow:IDR`, `hsp:patient_receivable:IDR`, dan `hsp:hospital_revenue:IDR` sudah terdaftar resmi.
- `EncounterBillReader` & `MedicalEmergencyResponseDispatcher`: Dipertahankan utuh tanda tangannya untuk menjamin integrasi modul Insurance dan Venue tetap hijau.

