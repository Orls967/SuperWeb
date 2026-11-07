# ADR-0094: Arsitektur Timbangan Hash-Chain, Tarif Royalti Berversi PP 19/2025, Rekonsiliasi Massa M5 & Saga Offtake (Fase 94)

- **Tanggal**: 2026-11-07
- **Status**: Disetujui
- **Pembuat**: Pelaksana `[G7-P4]`
- **Keputusan Terkait**: `G7-D25`, `G7-D26`, `G7-D27`, `G7-D28`, `G7-K11`, `G7-K12`

---

## 1. Konteks

Fase 94 memperluas modul `Mining` untuk mencakup hilirisasi operasional dari jembatan timbang (*weighbridge*), pengendalian kadar (*grade control*), neraca metalurgi smelter, kewajiban PNBP royalti minerba sesuai PP 19/2025, keselamatan kerja & lingkungan (HSE), reklamasi pascatambang, hingga pengapalan komersial (*offtake*). Diperlukan keputusan arsitektur eksplisit untuk menjamin ketidakbisaubahan tiket timbang, versi tarif regulasi, serta penyelesaian sengketa dan kegagalan pengapalan lintas modul.

## 2. Keputusan

1. **Tarif Royalti Berversi & Harga Acuan (`G7-D26`)**:
   - Seluruh tarif royalti PP 19/2025 disimpan pada tabel `min_royalty_rate_versions` dengan kolom `effective_from`, `version`, `source`, dan `label` (`regulasi` / `fixture`). Perubahan tarif masa depan ditambahkan sebagai baris versi baru (append-only), tidak pernah menimpa baris lama.
   - Kewajiban royalti wajib dihitung oleh `Modules\Mining\Domain\Algorithms\RoyaltyTierResolver` (M4) dari tonase produksi terverifikasi (tiket timbang hash-chain yang telah direkonsiliasi), bukan dari angka ketikan manual.
2. **Tiket Timbang Hash-Chain & Anti-Double-Dip (`G7-D28`)**:
   - Setiap tiket timbang pada `min_weighbridge_tickets` ditautkan menggunakan `Modules\Shared\Domain\Algorithms\Chain\CanonicalHashChain` per site/jembatan timbang.
   - Tiket dengan kombinasi `(vehicle_plate_number, weighed_at ±60 detik)` yang sama ditolak secara otomatis sebagai dugaan *double-dip*. Koreksi berat hanya dapat dilakukan melalui penerbitan tiket pembalik (*reversal ticket*).
3. **Kontrak Lintas Modul Baru (`G7-K11` & `G7-K12`)**:
   - `Modules\TradeFinance\Contracts\OfftakeLetterOfCreditGateway` (G7-K11) membungkus `IssueDocumentaryLetterOfCreditAction` dan `VerifyLcDocumentPresentationAction` untuk penerbitan dan pemeriksaan dokumen LC pengapalan offtake.
   - `Modules\Manufacturing\Contracts\ProcessOutputRecorder` (G7-K12) membungkus `RecordShopFloorWorkOrderOutputAction` untuk mencatat output ekstraksi smelter dari konsumsi bijih/konsentrat.
4. **Mata Uang Fungsional IDR & Selisih Kurs (`G7-D27`)**:
   - Kontrak dan invoice offtake berdenominasi USD menyimpan nominal asli (`amount_usd_cents`) beserta kurs konversi harian dari `MiningExternalFactors`. Saat pelunasan (*settlement*), perbedaan kurs antara tanggal invoice dan tanggal pelunasan dibukukan secara otomatis ke akun `min:fx_gain_loss:IDR`.

## 3. Konsekuensi

- Perintah audit `php artisan min:audit` dan `php artisan mining:audit-royalty` memverifikasi kesetimbangan tiga titik (tiket timbang $\leftrightarrow$ pergerakan stockpile $\leftrightarrow$ pengapalan offtake), keutuhan rantai hash, kesesuaian royalti terhadap versi tarif pada tanggal produksi, serta kesamaan saldo piutang terhadap buku besar.
