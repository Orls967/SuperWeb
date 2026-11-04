# Definition of Ready (DoR) — [G6-P4.2] Gig Bounty Internal (Fase 85)

## 1. Lingkup & Konteks
- **Fase**: 85 (Gig Bounty Internal)
- **Komponen Utama**:
  - D85-1: Model & skema Gig Bounty (`hcm_bounties`, `hcm_bounty_claims`, `hcm_bounty_pofs`).
  - D85-2: Validasi kualifikasi via `Modules\Edu\Contracts\CredentialVerifier`, geofencing, limit jam lembur harian/mingguan (UU Ketenagakerjaan).
  - D85-3: Hungarian bipartite matching internal talent roster untuk optimasi alokasi bounty (biaya = -skor kecocokan) + sertifikat verifikasi dual-bound.
  - D85-4: Integrasi supervisor POF approval dengan payroll berikutnya (baris payroll jenis `gig`), posting beban gig `expense:{unit}:gig_labor:IDR` ke kewajiban gaji karyawan `hcm:salary_payable:{employee}:IDR`.
  - D85-5: Scale Seeder T1 (200 bounty + 50 klaim).

## 2. Kriteria Penerimaan (Acceptance Criteria)
1. **D85-1**: Tabel dan model Eloquent menggunakan prefix `hcm_` (`hcm_bounties`, `hcm_bounty_claims`, `hcm_bounty_pofs`), relasi model utuh.
2. **D85-2**: Verifikasi sertifikasi karyawan via `CredentialVerifier` dari modul Edu. Karyawan tanpa kredensial valid ditolak saat mengklaim bounty bertarget sertifikasi.
3. **D85-3**: Layanan `GigBountyMatcherService` menggunakan `HungarianSolver` dengan deterministik min-cost mapping (bobot terbalik / negatif skor), menghasilkan sertifikat penugasan lolos verifikasi `AssignmentCertificateVerifier`.
4. **D85-4**: POF yang disetujui supervisor menghasilkan entitlement payroll tipe `gig`. Saat `RunPayrollAction` / `HcmPayrollCalculationService` berjalan, nilai payout gig diakumulasikan ke komponen penghasilan tambahan dan diposting ke ledger secara idempoten.
5. **D85-5**: Seeder skala T1 (200 bounty) berjalan cepat dan bersih.
6. **L.4 & L.5**: Audit dua sumber konsisten dan sabotase deteksi diskrepansi berhasil ditolak/terdeteksi.

## 3. Register Minus & Mitigasi
- *RM-85-01*: Nama tabel lama `gov_*` telah dialihkan lewat migrasi `hcm_bounties`. Backward compatibility query tetap dijaga lewat model yang menunjuk ke tabel baru `hcm_*`.
- *RM-85-02*: Batas lembur UU dihitung akurat terhadap akumulasi shift dalam minggu berjalan.
