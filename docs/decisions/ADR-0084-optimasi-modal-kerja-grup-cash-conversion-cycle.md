# ADR-0084: Kerangka Optimasi Modal Kerja Terpadu Melalui Pengukuran Siklus Konversi Kas (Cash Conversion Cycle)

**Status**: DITERIMA  
**Tanggal**: 2026-11-11  
**Pencetus**: Arsitek Solusi Keuangan Grup [W8-P5.3]  
**Konteks**: Modul `EnterpriseFinance` (Fase 54)

---

## 1. Konteks Bisnis & Masalah
Efisiensi modal kerja konglomerasi menentukan seberapa cepat modal yang diinvestasikan dalam rantai pasok dan operasi kembali menjadi kas. Banyak unit usaha mengalami penumpukan persediaan (*excess inventory*) dan piutang macet yang memperpanjang siklus konversi kas dan membebani likuiditas holding.

## 2. Keputusan yang Diambil
1. **Pengukuran 4 Metrik Modal Kerja Kanonikal**:
   - Days Sales Outstanding (DSO): $\text{DSO} = \text{intdiv}(\text{Piutang Dagang} \times 365, \text{Pendapatan Tahunan})$.
   - Days Inventory Outstanding (DIO): $\text{DIO} = \text{intdiv}(\text{Persediaan} \times 365, \text{Beban Pokok Penjualan})$.
   - Days Payable Outstanding (DPO): $\text{DPO} = \text{intdiv}(\text{Utang Dagang} \times 365, \text{Pembelian/HPP})$.
   - Cash Conversion Cycle (CCC): $\text{CCC} = \text{DSO} + \text{DIO} - \text{DPO}$.
2. **Kalkulasi Deterministik Integer Hari**:
   - Seluruh hasil perhitungan dinyatakan dalam integer hari (tanpa koma desimal).
3. **Penyusunan Peringkat Efisiensi Unit Usaha**:
   - Sistem membandingkan CCC antar-anak perusahaan sejenis dan menetapkan target efisiensi per kuartal.
   - Entitas dengan CCC negatif atau optimal diberikan prioritas alokasi anggaran investasi holding.

## 3. Konsekuensi
- Mendorong transparansi pengelolaan rantai nilai operasional anak perusahaan dan membebaskan kas terperangkap dalam modal kerja.
