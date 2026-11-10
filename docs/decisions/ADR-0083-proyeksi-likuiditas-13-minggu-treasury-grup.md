# ADR-0083: Model Proyeksi Arus Kas 13-Minggu Konsolidasi dan Saldo Kas Minimum Operasional

**Status**: DITERIMA  
**Tanggal**: 2026-11-11  
**Pencetus**: Arsitek Solusi Keuangan Grup [W8-P5.3]  
**Konteks**: Modul `EnterpriseFinance` (Fase 54)

---

## 1. Konteks Bisnis & Masalah
Manajemen perbendaharaan konglomerasi memerlukan visibilitas jangka pendek dan menengah atas likuiditas grup untuk mengantisipasi jatuh tempo kewajiban vendor, bunga sindikasi, pajak, dan dividen anak. Proyeksi bulanan statis terlalu lambat dalam mendeteksi krisis likuiditas mendadak di salah satu anak perusahaan.

## 2. Keputusan yang Diambil
1. **Rolling Forecast 13-Minggu Konsolidasi**:
   - Membangun skedul proyeksi arus kas bergulir 13-minggu yang mengintegrasikan rencana penerimaan piutang dagang, jadwal pembayaran utang dagang, jadwal angsuran utang bank, dan belanja modal yang disetujui.
2. **Definisi Saldo Minimum Operasional (Target Working Cash Balance)**:
   - Setiap anak perusahaan ditetapkan batas bawah saldo kas operasional minimum berdasar rata-rata pengeluaran kas harian selama 14 hari kerja.
3. **Mekanisme Sweep & Rebalancing Otomatis**:
   - Saldo kas di atas batas minimum diidentifikasi sebagai kas surplus yang siap disapu (*swept*) ke notional pooling holding (bekerja sama dengan modul Treasury).
   - Anak perusahaan yang mengalami proyeksi defisit kas di bawah batas minimum memicu permintaan injeksi likuiditas internal (*cash injection request*).

## 3. Konsekuensi
- Mencegah terjadinya gagal bayar operasional di unit bisnis; memaksimalkan imbal hasil atas kas menganggur (*idle cash*) di tingkat holding.
