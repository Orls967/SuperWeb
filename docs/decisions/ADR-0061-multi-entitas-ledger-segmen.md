# ADR-0061: Multi-Entitas Pada Ledger Berbasis Segmentasi Kode Akun

## Status
DITERIMA (2026-11-11) — Keputusan W8-D61 (Gelombang 8, Prompt P5).

## Konteks
Tabel buku besar (`ledger_transactions` dan `ledger_entries`) pada modul `Banking` tidak memiliki kolom `entity_id` untuk membedakan transaksi antar anak perusahaan dalam grup holding. Mengubah skema database modul `Banking` atau tabel ledger inti dilarang keras karena berdampak regresi ke seluruh 46 modul ekosistem yang telah tersertifikasi.

## Keputusan
1. **Representasi Multi-Entitas via Segmen Kode Akun**: Multi-entitas direpresentasikan secara konsisten melalui struktur segmen string kode akun:
   `{prefiks}:{nama_akun}:{kode_entitas}[:{kode_lawan}]:{mata_uang}`
   Contoh:
   - `ic:ar:HOLDING:SUBSID_A:IDR` (Piutang intercompany Holding ke Subsid A)
   - `ic:ap:SUBSID_A:HOLDING:IDR` (Utang intercompany Subsid A ke Holding)
   - `trs:pool_sweep_clearing:SUBSID_A:IDR` (Akun perantara sweep kas Subsid A)
2. **EntityAccountFactory**: Penyediaan kelas helper idempoten `Modules\Treasury\Ledger\EntityAccountFactory` dan `Modules\Intercompany\Ledger\EntityAccountFactory` untuk men-generate kode akun terstandarisasi.
3. **Registri Entitas Grup**: Entitas grup dicatat pada tabel `ic_entities` (kode, nama, party_id, mata uang fungsional, segmen, wilayah) dan relasi kepemilikan pada `ic_ownership_links`.
4. **Tanpa Perubahan Skema Ledger**: Tidak ada penambahan kolom entitas atau modifikasi pada modul `Banking`.

## Konsekuensi
- Memungkinkan pembukuan per entitas independen dan konsolidasi grup tanpa mengubah fondasi ledger modul `Banking`.
- Memerlukan helper kode akun idempoten saat posting dan agregasi pelaporan saldo per entitas.

