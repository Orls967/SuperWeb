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

- Perintah verifikasi `php artisan min:audit` dan `php artisan mining:check-royalty` memverifikasi kesetimbangan tiga titik (tiket timbang $\leftrightarrow$ pergerakan stockpile $\leftrightarrow$ pengapalan offtake), keutuhan rantai hash, kesesuaian royalti terhadap versi tarif pada tanggal produksi, serta kesamaan saldo piutang terhadap buku besar.


---

## 4. Alternatif Solusi yang Dipertimbangkan (Trade-off Analysis)

Dalam merancang arsitektur hilirisasi dan kepatuhan pertambangan ini, beberapa alternatif arsitektural telah dievaluasi:

### 4.1 Penyimpanan Tarif Royalti: Hardcoded vs Tabel Konfigurasi vs Model Berversi (Dipilih)
- **Opsi A (Hardcoded di PHP)**: Sangat cepat dan deterministik, tetapi memerlukan rilis kode baru setiap kali Menteri ESDM memperbarui formula harga patokan atau tier kalori. Ditolak karena tidak fleksibel.
- **Opsi B (Tabel Konfigurasi Global Dinamis)**: Fleksibel, tetapi perubahan nilai tarif dapat menimpa kalkulasi historis (*retroactive contamination*), merusak audit kepatuhan tahun-tahun sebelumnya. Ditolak karena melanggar integritas audit akuntansi.
- **Opsi C (Tabel Berversi Append-Only - DIPILIH)**: Tabel `min_royalty_rate_versions` menyimpan riwayat tanggal efektif (`effective_from`), nomor versi, dan sumber regulasi resmi. Setiap perhitungan royalti masa lalu mereferensikan versi yang berlaku pada saat produksi terjadi, menjamin reproduksibilitas audit 100%.

### 4.2 Verifikasi Integritas Timbangan: Database Trigger vs HMAC Hash-Chain (Dipilih)
- **Opsi A (Database Trigger & Checksum)**: Bergantung pada fitur spesifik basis data, menyulitkan migrasi dan tidak menyediakan bukti kriptografis portabel ke pihak ketiga. Ditolak.
- **Opsi B (HMAC-SHA256 Hash-Chain per Pos Timbang - DIPILIH)**: Tiket timbang membentuk rantai kriptografis yang diverifikasi pada lapisan domain aplikasi. Memungkinkan audit eksternal independen tanpa akses langsung ke mesin database produksi.

### 4.3 Koordinasi Pengapalan Offtake: Two-Phase Commit vs Saga Orkestrasi (Dipilih)
- **Opsi A (Two-Phase Commit / 2PC)**: Menahan penguncian terdistribusi (*distributed locks*) lintas modul `Mining`, `TradeFinance`, dan `Finance`. Sangat rentan terhadap kebuntuan (*deadlock*) dan latensi jaringan dermaga yang fluktuatif. Ditolak.
- **Opsi B (Saga dengan Kompensasi Kompensasi - DIPILIH)**: `OfftakeShipmentLifecycleSaga` mengelola transisi bertahap dengan aksi kompensasi balik (*compensating transactions*) yang menjamin konsistensi eventual tanpa penguncian basis data yang membebani.

---

## 5. Mitigasi Risiko Manipulasi & Fraud di Lapangan Tambang

Operasi penambangan di lokasi terpencil memiliki kerentanan terhadap kecurangan fisik dan administratif:
1. **Penyelundupan Muatan Bersih (Tare Manipulation)**:
   - Pengemudi membuang air ballast atau kotoran sebelum menimbang tarra keluar.
   - Mitigasi: `WeighbridgeAnomalyDetector` membandingkan nilai tarra terhadap deviasi historis kendaraan ($\pm 3.5\%$). Pelanggaran memicu inspeksi visual dan penimbangan ulang.
2. **Kecurangan Tiket Hantu (Phantom Haul Cycle)**:
   - Pendaftaran tiket fiktif untuk menaikkan klaim ritase kontraktor.
   - Mitigasi: Integrasi geofence pit-to-crusher dan penolakan otomatis tiket duplikat pada jendela $\pm 60\text{ detik}$ untuk kendaraan yang sama.
3. **Penyimpangan Penagihan Offtake (Assay Arbitrage)**:
   - Perbedaan hasil analisis kadar laboratorium muat vs laboratorium bongkar.
   - Mitigasi: `CoalPriceAdjuster` dan `MassBalanceReconciler` memvalidasi toleransi perbedaan uji laboratorium menggunakan batasan standar ISO/ASTM dan menerapkan formula penalti/bonus komersial yang mengikat kedua belah pihak secara transparan.

---

## 6. Kepatuhan Regulasi ESDM, KLHK, & Standar Keuangan Internasional

Arsitektur Fase 94 menjamin kepatuhan terpadu terhadap:
1. **Peraturan Pemerintah No. 19 Tahun 2025 Lampiran I**:
   - Skema royalti berjenjang batubara berdasarkan kalori GAR dan harga HBA resmi Kementerian ESDM.
2. **Keputusan Menteri ESDM tentang Pedoman Pelaksanaan Kaidah Teknik Pertambangan yang Baik**:
   - Penerapan sistem izin kerja berisiko tinggi (PTW), pemantauan gas tambang bawah tanah, dan evaluasi kestabilan lereng tambang terbuka.
3. **Standar Industri Global tentang Manajemen Tailing (GISTM)**:
   - Pemantauan instrumentasi geoteknik bendungan tailing secara teratur, pemantauan garis freatik piezometer, dan debit rembesan kaki tanggul.
4. **SNI 7571:2010 (Baku Tingkat Getaran Peledakan)**:
   - Prediksi dan pengendalian Peak Particle Velocity (PPV) dan tingkat kebisingan impulsif (Airblast) untuk melindungi keselamatan warga sekitar konsesi.
5. **PSAK 64 / IFRIC 20 (Aktivitas Pengupasan Lapisan Tanah pada Tahap Produksi Tambang Terbuka)**:
   - Pemisahan biaya pengupasan tanah penutup normal dan kapitalisasi kelebihan rasio pengupasan sebagai *Deferred Stripping Asset*.

---

## 7. Status Implementasi & Pengesahan Dokumen

| Aspek Arsitektur | Komponen Penegak | Status Implementasi |
|---|---|---|
| **Hash-Chain Timbangan** | `RecordHashChainWeighbridgeTicketAction` & `WeighbridgeAnomalyDetector` | LENGKAP & TERVERIFIKASI |
| **Tarif Royalti PP 19/2025** | `RoyaltyTierResolver` (M4) & `CalculateVerifiedRoyaltyAction` | LENGKAP & TERVERIFIKASI |
| **Neraca Massa Metalurgi** | `MassBalanceReconciler` (M5) & `RunMetallurgicalMassBalanceReconciliationAction` | LENGKAP & TERVERIFIKASI |
| **Harga Komersial Batubara** | `CoalPriceAdjuster` (M6) & `AdjustCoalCommercialPriceAction` | LENGKAP & TERVERIFIKASI |
| **Saga Pengapalan Offtake** | `OfftakeShipmentLifecycleSaga` & `OfftakeLetterOfCreditGatewayService` | LENGKAP & TERVERIFIKASI |
| **Pemantauan K3 & Getaran** | `MineBlastingSeismicMonitor` & `EvaluateBlastingVibrationAction` | LENGKAP & TERVERIFIKASI |
| **Deplesi Cadangan KCMI** | `MineExplorationReserveDepletionTracker` & `TrackMineReserveDepletionAction` | LENGKAP & TERVERIFIKASI |
| **Pembukuan Buku Besar** | `MiningFase94LedgerPostingService` (8 Kejadian) | LENGKAP & TERVERIFIKASI |

Dokumen arsitektur ini telah diverifikasi dan disahkan sebagai acuan formal pelaksanaan teknis Fase 94.
