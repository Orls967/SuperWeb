# Runbook Operasional: Audit Dua Sumber EnterpriseFinance (enterprise:audit)

## 1. Tujuan & Tanggung Jawab
Runbook ini memandu operator perbendaharaan dan auditor internal dalam menjalankan command audit dua sumber `enterprise:audit` guna memverifikasi integritas finansial konglomerasi tanpa diskrepansi.

---

## 2. Prosedur Eksekusi Audit Terjadwal & Manual

### A. Eksekusi Perintah
```bash
php artisan enterprise:audit
```

### B. Indikator Keberhasilan (Exit Code 0)
Perintah audit berhasil apabila seluruh invarian terpenuhi dengan keluaran:
```
Starting Enterprise Finance & Conglomerate Governance Audit...
Status: OK
Budgets Audited: 125
Tax Summaries Audited: 48
Debt Covenants Audited: 16
Consolidation Runs Audited: 12
Discrepancies: 0
Enterprise Finance audit PASSED with 0 discrepancy.
```

---

## 3. Matriks Eskalasi Diskrepansi (Exit Code 1)
Apabila audit mendeteksi diskrepansi (`FAILURE`), lakukan langkah mitigasi berikut:

| Kode Masalah | Deskripsi Diskrepansi | Tindakan Remediasi |
|---|---|---|
| **ERR-BGT-01** | Belanja aktual melebihi pagu Hard-Stop | Periksa transaksi belanja terakhir di departemen terkait; batalkan voucher yang melanggar. |
| **ERR-COV-02** | Sertifikat covenant utang gagal diverifikasi hash | Verifikasi ulang keabsahan sertifikat melalui `SyndicatedLoanCovenantCertificateVerifier`. |
| **ERR-TB-03** | Neraca saldo konsolidasi tidak seimbang zero-sum | Jalankan rekonsiliasi eliminasi bilateral di modul Intercompany; telusuri jurnal yang belum diposting. |
| **ERR-LED-04** | Saldo belanja modul berbeda dengan buku besar perbankan | Jalankan sinkronisasi ulang ledger posting via `EnterpriseFinanceLedgerPoster`. |
