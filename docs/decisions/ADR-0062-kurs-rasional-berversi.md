# ADR-0062: Kurs Rasional Berversi Tak Dapat Diubah (Immutable Rational Rates)

## Status
DITERIMA (2026-11-11) — Keputusan W8-D62 (Gelombang 8, Prompt P5).

## Konteks
Operasi valuta asing sering mengalami distorsi kepresisian biner bila menggunakan tipe floating-point, dan rentan terhadap manipulasi histori bila kurs lama dapat diubah (mutable). Skema lama `rate_numerator` integer scaled 1e6 di `trs_exchange_rates` membatasi representasi pecahan fraksional murni dan tidak mencatat versi histori perubahan kurs.

## Keputusan
1. **Representasi Rasional Murni**: Kurs valuta asing dinyatakan sebagai pasangan bilangan bulat rasional `RationalRate($numerator, $denominator)` di mana $n > 0$ dan $d > 0$. Kurs menyatakan nilai IDR per 1 unit mata uang dasar asing (misal USD 15.650 = 15650/1).
2. **Kurs Berversi & Immutable**: Setiap perubahan atau koreksi kurs harian diterbitkan sebagai nomor versi baru (`version + 1`) pada tabel `trs_exchange_rates`. Baris kurs yang telah diposting tidak pernah di-update di tempat.
3. **Jenis Kurs Resmi**: Mendukung jenis kurs `spot`, `middle`, `tax`, `closing`, dan `average`.
4. **ExchangeRateLookup Contract**: Akses kurs lintas modul disediakan melalui interface `Modules\Treasury\Contracts\ExchangeRateLookup::rate(string $ccy, string $date, string $type): RationalRate`.

## Konsekuensi
- Mencegah inkonsistensi matematis selisih desimal float pada seluruh transaksi valuta asing.
- Seluruh histori kurs dapat diaudit secara forensik dan deterministik.
