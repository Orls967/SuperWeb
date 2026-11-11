# ADR-0081: Penegakan Guardrail Anggaran Departemen Melalui Dua Lapis Kendali (Hard-Stop & Soft-Stop) Serta Pelacakan Encumbrance

**Status**: DITERIMA  
**Tanggal**: 2026-11-11  
**Pencetus**: Arsitek Solusi Keuangan Grup [W8-P5.3]  
**Konteks**: Modul `EnterpriseFinance` (Fase 54)

---

## 1. Konteks Bisnis & Masalah
Dalam organisasi skala perusahaan multinasional, pengeluaran kas kerap mendahului reservasi anggaran, mengakibatkan terjadinya *budget overrun* (belanja melampaui pagu resmi). Diperlukan sistem guardrail anggaran yang mendukung:
1. Pemisahan antara komitmen belanja (*encumbrance*) seperti penerbitan Purchase Order dan realisasi kas aktual (*actual spend*).
2. Mekanisme kendali ganda: *Hard-Stop* (pembatalan transaksi otomatis) untuk pos belanja tidak fleksibel dan *Soft-Stop* (peringatan deviasi) untuk pos belanja operasional rutin.

## 2. Keputusan yang Diambil
1. **Dua Tahap Pengikatan Dana**:
   - Tahap 1 (*Encumber*): Reservasi saldo pagu ketika dokumen komitmen (PO/kontrak vendor) diterbitkan. Saldo pagu efektif berkurang: $\text{Available} = \text{Allocated} - (\text{Encumbered} + \text{Spent})$.
   - Tahap 2 (*Realize*): Saat tagihan disetujui untuk pembayaran kas, nilai encumbrance dialihkan menjadi actual spend: $\text{Encumbered} \leftarrow \text{Encumbered} - X$, $\text{Spent} \leftarrow \text{Spent} + X$.
2. **Penolakan Transaksi pada Hard-Stop**:
   - Jika `control_type === 'HARD_STOP'` dan nominal transaksi melebihi `Available`, sistem wajib melempar `BudgetGuardrailBreachException` dan memblokir pencatatan transaksi.
3. **Pencatatan Single-Sign Ledger**:
   - Setiap realisasi belanja aktual memicu pencatatan jurnal ke `LedgerService` pada akun `ef:department_expense:IDR` dan `ef:capex_clearing:IDR` secara single-sign balancing.

## 3. Konsekuensi
- Mencegah terjadinya *unauthorized spending* dan over-budget; memberikan visibilitas real-time terhadap komitmen finansial perusahaan.
