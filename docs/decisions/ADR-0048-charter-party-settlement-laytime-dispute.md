# ADR-0048: Charter Party Settlement & Laytime Dispute Standard (G7-D48)

## Status
Diterima (Accepted)

## Konteks
Sengketa waktu muat/bongkar kapal (*Laytime Disputes*) dan denda keterlambatan (*Demurrage Claims*) menimbulkan friksi finansial berkepanjangan antara pemilik kapal (*Shipowner*) dan penyewa (*Charterer*). Diperlukan sistem kalkulasi otomatis yang taat asas terhadap definisi hukum BIMCO dan bukti dokumen waktu nyata (*Statement of Facts*).

## Keputusan
1. **Adopsi Definisi Standar BIMCO Laytime 2013**:
   Mengimplementasikan terminologi resmi NOR, Turn-Time, SHINC, SHEX, dan WWD pada kelas domain `Modules\Shared\Domain\Algorithms\Port\LaytimeDemurrageCalculator`.
2. **Penegakan Prinsip Once on Demurrage Always on Demurrage**:
   Setelah waktu laytime habis terlampaui, pengecualian cuaca hujan (WWD) atau hari libur (SHEX) tidak lagi menghentikan penghitungan denda keterlambatan.
3. **Penyelesaian Otomatis Despatch Money**:
   Menetapkan insentif despatch sebesar $50\%$ dari tarif harian demurrage bila operasi pemuatan atau pembongkaran kargo selesai sebelum batas waktu yang diizinkan.
4. **Pencatatan Otomatis ke Buku Besar Keuangan**:
   Penerbitan faktur demurrage atau nota despatch memicu pencatatan akrual otomatis pada akun `prt:receivable:demurrage` atau `prt:payable:despatch`.

## Konsekuensi
- Menghilangkan perselisihan subjektif dalam interpretasi lembar fakta SOF pelabuhan.
- Menjamin kepastian hukum dan percepatan rekonsiliasi kas penyewaan kapal niaga.

