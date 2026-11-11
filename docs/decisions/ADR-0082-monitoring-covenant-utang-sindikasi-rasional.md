# ADR-0082: Pemantauan Rasio Covenant Utang Sindikasi Berbasis Aritmetika Rasional Integer Permille

**Status**: DITERIMA  
**Tanggal**: 2026-11-11  
**Pencetus**: Arsitek Solusi Keuangan Grup [W8-P5.3]  
**Konteks**: Modul `EnterpriseFinance` (Fase 54)

---

## 1. Konteks Bisnis & Masalah
Fasilitas kredit sindikasi perbankan korporat mewajibkan pemenuhan covenant finansial ketat:
- Debt Service Coverage Ratio (DSCR): Kemampuan arus kas operasional menutup kewajiban pokok dan bunga utang berjalan.
- Debt-to-Equity Ratio (DER): Rasio total utang berbunga terhadap total ekuitas pemegang saham.
- Interest Service Coverage Ratio (ISCR): Rasio laba sebelum bunga dan pajak (EBIT) terhadap beban bunga.
- Current Ratio: Rasio aset lancar terhadap liabilitas jangka pendek.
Pelanggaran covenant dapat memicu *event of default* dan percepatan jatuh tempo penarikan pinjaman oleh konsorsium bank (*acceleration clause*). Penggunaan tipe data floating-point pada perhitungan rasio ini melanggar aturan arsitektur moneter (A3 / FloatMoneyRule) dan berisiko salah tafsir akibat eror presisi IEEE 754.

## 2. Keputusan yang Diambil
1. **Penerapan Algoritma Bersertifikat D7 (`SyndicatedLoanCovenantCalculator`)**:
   - Seluruh rasio dihitung menggunakan representasi bilangan bulat dalam satuan basis points atau permille ($1.000\text{ permille} = 1,000\times$, $1.250\text{ permille} = 1,250\times$, $2.500\text{ permille} = 2,500\times$).
   - Pembagian rasional integer tanpa IEEE 754 float: $\text{Ratio Permille} = \text{intdiv}(\text{Numerator Minor} \times 1000, \text{Denominator Minor})$.
2. **Kriteria Kepatuhan & Deteksi Pelanggaran**:
   - DSCR wajib $\ge \text{threshold}$ (umumnya $1.250$ permille).
   - DER wajib $\le \text{threshold}$ (umumnya $2.500$ permille).
   - ISCR wajib $\ge \text{threshold}$ (umumnya $2.000$ permille).
   - Current Ratio wajib $\ge \text{threshold}$ (umumnya $1.100$ permille).
3. **Penerbitan Sertifikat Kepatuhan SHA-256**: Setiap kalkulasi menghasilkan `SyndicatedLoanCovenantCertificate` dengan hash digital deterministik yang dapat diaudit oleh kreditur sindikasi.
4. **Alarm & Notifikasi Eskalasi**: Jika salah satu rasio mendekati ambang batas kritis (jarak margin toleransi $< 50$ permille), sistem memicu status peringatan dini (*warning*) ke manajemen perbendaharaan.

## 3. Konsekuensi
- Memastikan pemenuhan standar kepatuhan kreditur sindikasi tanpa kompromi presisi matematis dan mematuhi aturan nol toleransi float A3.
