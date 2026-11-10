# ADR-0069: Central Cash Pooling Zero-Balancing Berbasis Pinjaman Intercompany

## Status
DITERIMA (2026-11-11) — Keputusan W8-D69 (Gelombang 8, Prompt P5).

## Konteks
Penyapuan dana (*cash sweep*) dari rekening bank anak perusahaan ke rekening induk holding tidak boleh sekadar berupa pemindahan saldo virtual atau pengurangan sepihak di database, melainkan harus diakui sebagai pinjaman antar-perusahaan (*Intercompany Loan*) yang sah secara hukum perpajakan dan berpegang pada prinsip kewajaran (*arm's length interest*). Selain itu, penyapuan kas tidak boleh menyebabkan rekening operasional anak perusahaan mengalami saldo minus.

## Keputusan
1. **Zero-Balancing Berbasis Pinjaman IC**: Setiap sweep surplus kas dari rekening anak perusahaan ke rekening induk holding secara otomatis menerbitkan kontrak pinjaman antar-entitas (`ic_loans`) dengan perhitungan amortisasi bunga wajar.
2. **Posting Buku Besar Berimbang**:
   Penyapuan membukukan piutang pinjaman di induk dan kewajiban pinjaman di anak melalui akun clearing perantara:
   `DR ic:loan_receivable:{induk}:{anak}:IDR / CR trs:pool_sweep_clearing:{anak}:IDR`
3. **Proteksi Saldo Negatif**: Algoritma sweep secara ketat melarang pemindahan dana yang menyebabkan saldo rekening anak berada di bawah nol.
4. **Reverse Sweep Likuiditas**: Jika anak perusahaan mengalami defisit di bawah target balance operasional, sistem melakukan reverse sweep untuk mengembalikan likuiditas yang didanai oleh induk.

## Konsekuensi
- Memastikan struktur pooling kas grup memenuhi kepatuhan perpajakan dan akuntansi intercompany, sekaligus menjamin ketersediaan likuiditas operasional seluruh entitas anak.
