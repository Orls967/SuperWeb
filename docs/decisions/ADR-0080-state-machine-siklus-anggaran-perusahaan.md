# ADR-0080: Siklus Hidup Anggaran Korporat 8-Status Melalui State Machine Formal

**Status**: DITERIMA  
**Tanggal**: 2026-11-11  
**Pencetus**: Arsitek Solusi Keuangan Grup [W8-P5.3]  
**Konteks**: Modul `EnterpriseFinance` (Fase 54)

---

## 1. Konteks Bisnis & Masalah
Penyusunan anggaran belanja korporat konglomerasi kerap mengalami kebocoran anggaran akibat tidak adanya kejelasan transisi status pengesahan. Pagu anggaran sering kali direalisasikan sebelum disetujui direksi, atau tetap digunakan setelah tahun fiskal berakhir. Dibutuhkan state machine formal yang mengunci setiap tahap persetujuan dan pembelanjaan pagu anggaran.

## 2. Keputusan yang Diambil
1. **Penerapan `EnterpriseBudgetCycleStateMachine`**: Mengelola siklus anggaran melalui tepat 8 status kanonikal:
   - `DRAFT`: Perancangan rincian alokasi biaya per departemen.
   - `SUBMITTED`: Pengajuan formal ke Komite Anggaran Holding.
   - `REVIEWED`: Penelaahan kewajaran belanja dan kesesuaian target KPI.
   - `APPROVED`: Pengesahan resmi Dewan Direksi & Komisaris.
   - `ACTIVE`: Pagu dibuka penuh untuk komitmen (*encumbrance*) dan belanja kas.
   - `REVISED`: Penyesuaian pagu anggaran kuartalan atau tengah tahun.
   - `FROZEN`: Pembekuan hak belanja darurat saat cash flow defisit.
   - `CLOSED`: Penutupan tahun fiskal; pagu yang tidak terserap hangus.
2. **Aturan Transisi & Maker-Checker**:
   - Transisi dari `REVIEWED` ke `APPROVED` wajib disahkan oleh user dengan peran `admin` atau `treasury` yang berbeda dari pembuat draft (*maker-checker rule*).
   - Realisasi belanja hanya diperbolehkan pada status `ACTIVE`.
   - Transisi tidak valid melempar `BudgetInvalidTransitionException`.
3. **Pencatatan Riwayat Transisi**: Setiap transisi status dicatat di tabel riwayat `ef_budget_histories` dengan mencantumkan operator ID, timestamp, alasan perubahan, dan snapshot pagu.

## 3. Konsekuensi
- Menjamin kepatuhan tata kelola anggaran korporat; tidak ada pagu anggaran liar yang dapat dibelanjakan tanpa jejak persetujuan resmi.
