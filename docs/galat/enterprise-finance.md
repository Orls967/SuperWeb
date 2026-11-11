# Register Galat (Error Registry): Modul EnterpriseFinance

Dokumen ini mendokumentasikan kode galat, pesan kesalahan, dan solusi penanganan dalam modul `EnterpriseFinance`.

---

## 1. Daftar Galat Bisnis & Penanganan

| Kode Galat | Pesan Galat | Penyebab | Solusi Penanganan |
|---|---|---|---|
| **EF-ERR-001** | `Pagu anggaran tidak mencukupi untuk reservasi komitmen.` | Nilai komitmen belanja PO melampaui sisa pagu tersedia pada mode Hard-Stop. | Kurangi nilai PO atau ajukan revisi penambahan pagu anggaran via state machine `REVISED`. |
| **EF-ERR-002** | `Transisi status siklus anggaran tidak sah dari [X] ke [Y].` | Percobaan transisi di luar matriks `EnterpriseBudgetCycleStateMachine`. | Ikuti alur transisi resmi: DRAFT -> SUBMITTED -> REVIEWED -> APPROVED -> ACTIVE. |
| **EF-ERR-003** | `Pelanggaran prinsip pembuat-pemeriksa (Four-Eyes Principle).` | Pengesahan anggaran dilakukan oleh user yang sama dengan pembuat usulan. | Wajib disahkan oleh pejabat otorisasi yang berbeda. |
| **EF-ERR-004** | `Sertifikat covenant utang sindikasi tidak valid atau rusak.` | Payload atau hash SHA-256 sertifikat diubah secara tidak sah. | Hitung ulang melalui `SyndicatedLoanCovenantCalculator` untuk menerbitkan sertifikat baru. |
| **EF-ERR-005** | `Neraca saldo konsolidasi tidak seimbang (Zero-Sum Breach).` | Total debit tidak sama dengan total kredit pada proses agregasi. | Identifikasi entitas anak yang memiliki selisih pembukuan sebelum konsolidasi. |
| **EF-ERR-006** | `Pelanggaran pemisahan tugas (SoD Conflict) tingkat kritis.` | Pengguna memiliki kombinasi peran terlarang (misal maker & checker). | Cabut salah satu peran pengguna di modul Identity/RBAC. |
| **EF-ERR-007** | `Nilai belanja melebihi batas delegasi wewenang (DoA Limit).` | Manajer mencoba menyetujui transaksi di atas plafon tingkatannya. | Eskalasi persetujuan ke tingkatan manajemen di atasnya (CFO / Direksi). |
| **EF-ERR-008** | `Kewajiban pelaporan regulasi telah melewati tenggat waktu (Overdue).` | Laporan belum diserahkan hingga tanggal jatuh tempo kepatuhan. | Serahkan laporan segera dan catat estimasi denda administrasi pada sistem. |
