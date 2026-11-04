# Desain Teknis P4.1 — Fase 58: HCM Inti

## 1. Arsitektur & Model
- `Department`: hierarki pohon dengan deteksi siklus (mencegah siklus relasi parent-child).
- `Employee`: NIK dienkripsi + blind index SHA-256 (`nik_bidx`).
- `ShiftSchedule` & `AttendanceLog`: jadwal interval setengah terbuka [start, end), deteksi keterlambatan & lembur terhitung menit.
- `RosterOptimizationService`: integrasi `HungarianSolver` + `AssignmentCertificateVerifier` untuk penugasan shift optimal berbiaya minimum (vektor prompt: 4x4 -> 13).
- `PayrollService`: kalkulasi berparameter S2 (BPJS Kes, BPJS TK, PPh21 TER), posting ledger terperinci sesuai spesifikasi KONSEP §8F.
- `AuditHcmCommand`: rekonsiliasi database vs ledger dan integritas slip payroll.
