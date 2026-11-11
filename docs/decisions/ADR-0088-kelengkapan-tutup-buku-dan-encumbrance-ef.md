# ADR-0088: Kelengkapan Tutup Buku Fiskal, Encumbrance Anggaran & Penegakan SoD Finansial Grup

## Status
Diterima (Accepted) — Dilaksanakan pada PR [W8-P5-b.1] Fase 54

## Konteks
Pemeriksaan pasca-merge W8-P5 (#111) menunjukkan bahwa meskipun fondasi dasar permodalan konglomerasi telah tercatat, implementasi spesifikasi penutupan siklus fiskal, pengikatan komitmen anggaran pengadaan (*budget encumbrance*), pencegahan aktif pemisahan tugas (*Segregation of Duties*), serta paket audit data pack ZIP belum lengkap di master.

Untuk menjamin kepatuhan tata kelola keuangan konglomerasi sesuai prinsip KONSEP §A2 dan spesifikasi D54-1 s/d D54-12, diperlukan standarisasi teknis yang mengikat.

## Keputusan
1. **Pencatatan Encumbrance Non-Ledger (Memo Account)**:
   Komitmen anggaran pengadaan barang/jasa dicatat pada tabel operasional `ef_budget_encumbrances` (status `RESERVED`, `LIQUIDATED`, `RELEASED`) melalui kontrak `BudgetCommitmentPort`. Anggaran dan komitmen tidak diposting ke buku besar keuangan (prinsip memo, D76).
2. **Posting Buku Besar Penutupan Periode Fiskal (D54-10)**:
   Penutupan buku bulanan mengeksekusi penjurnalan berimbang tunggal:
   - Menutup seluruh akun pendapatan nominal ke akun ikhtisar `ef:income_summary:{entitas}:IDR`.
   - Menutup seluruh akun beban nominal ke akun ikhtisar `ef:income_summary:{entitas}:IDR`.
   - Memindahkan saldo bersih laba ke `ef:retained_earnings:{entitas}:IDR` (debit `income_summary`, kredit `retained_earnings`) atau rugi (debit `retained_earnings`, kredit `income_summary`).
   - Setiap mutasi menggunakan kunci idempotensi deterministik `IDEM:EF:CLOSE:{ENTITAS}:{PERIODE}`.
3. **Penyelesaian Selisih PPN Masa**:
   Rekonsiliasi PPN bulanan mendebit `ef:vat_output_payable:{entitas}:IDR` dan mengkredit `ef:vat_input_receivable:{entitas}:IDR` serta kliring setoran `ef:vat_settlement_clearing:{entitas}:IDR`.
4. **Matriks SoD dan Pencegahan Berbatas Waktu**:
   12 aturan konflik SoD diberlakukan secara ketat. Penetapan peran yang menimbulkan konflik secara default dilempar `SodViolationException` kecuali terdapat otorisasi dispensasi aktif di `ef_sod_exceptions` yang belum kedaluwarsa.
5. **Pengujian Kontrol Otomatis (RCM)**:
   Command harian `enterprise:control-tests` menguji titik kendali (Four-Eyes, 3-Way Match, Toleransi Timbangan, Margin Floor), mencatat log uji di `ef_control_test_runs`, dan menugaskan defisiensi via `TaskInboxService::createTask()`.
6. **Paket Bukti Audit Eksternal (Auditor Data Pack Generator)**:
   Command `enterprise:data-pack` menghasilkan arsip ZIP yang memuat laporan buku besar, neraca saldo, mutasi kas bank, register aset tetap, dan file integritas `manifest.sha256`.

## Konsekuensi
- Modul `EnterpriseFinance` memiliki kontrol menyeluruh atas seluruh siklus belanja dari reservasi PO hingga tutup buku dan pelaporan pajak.
- Penutupan periode mengunci mutasi baru ke periode terkait (`ef_financial_period_locks`).
- Integritas buku besar terverifikasi secara dual-source melalui `enterprise:audit`.

