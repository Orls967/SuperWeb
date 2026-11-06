# Desain & Bukti Kondisi Lama — Fase 126 (Genco, Grid & Smart Metering)

## L.0 Bukti kondisi lama (diukur 2026-11-06 pada master `f653a563`)

| Aspek | Temuan | Penilaian |
|---|---|---|
| Tabel `egy_*` modul Egy | `egy_generation_assets`, `egy_grid_nodes`, `egy_smart_meters`, `egy_meter_readings`, `egy_ppa_contracts`, `egy_utility_consolidated_invoices`, `egy_utility_invoice_lines` | **ada (kerangka)**; belum memiliki dukungan parameter berversi `egy_parameters`, dispatch runs, curtailment records, dan demand response events |
| Registri Meter Tunggal (126.1) | Smart meter terbatas pada skema listrik tanpa tipe multi-utility | **salah** (G7-D12); perlu dukungan meter multi-utility (listrik, air, gas, termal) |
| Smart Metering 15-Menit & TOU (126.2) | Logika pembacaan belum memiliki idempotensi interval dan algoritma TOU bersertifikat | **salah**; belum ada `Utility\TouBilling` bersertifikat E1 |
| Grid Dispatch & Curtailment (126.3) | `EnergyGridAndMeteringService::simulateHourlyDispatch` sangat sederhana tanpa min-stable dan tanpa sertifikat | **salah**; belum ada `Energy\MeritOrderDispatch` bersertifikat E2 |
| PPA & Net Metering (126.4) | `settlePpa` dan kalkulasi net metering belum menggunakan integer Wh/milli-unit dan belum ada sertifikat | **salah**; belum ada `Energy\NetMeteringSettlement` bersertifikat E3 |
| EV Off-Peak Load Shifting (126.5) | Belum ada integrasi ke EV Mobility dan belum ada pembagian demand response via LargestRemainder | **tidak ada**; kontrak G7-K8 belum dibuat di Mobility |
| Kontrak G7-K8 | `Modules\Mobility\Contracts\EvChargingSessionSource` | **tidak ada**; wajib dibuat aditif di `Modules\Mobility` |
| Algoritma E1–E3 | `Utility\TouBilling`, `Energy\MeritOrderDispatch`, `Energy\NetMeteringSettlement` | **tidak ada**; wajib dibuat di `Shared/Domain/Algorithms/` |
| Rute & UI | Rute lama `/energy/grid`, `/energy/meters`, `/energy/ppa` belum lengkap, tidak ada papan dispatch, profil beban, ekspor CSV, dan portal customer | **kurang**; wajib dibuat sesuai spesifikasi UI responsif 375 px |
| Akun ledger Egy | `EgyAccounts` belum memiliki akun tagihan listrik, biaya pembelian daya, dan demand response expense | **kurang**; perlu dilengkapi sesuai D126-6 |

Bukti perintah: `php artisan arch:scan` bersih; `vendor/bin/pest tests/Architecture` 248 passed.

---

## L.1 Desain Arsitektur & Spesifikasi

### Modul & Prefiks
Modul pemilik `Modules\Egy` dengan prefiks tabel `egy_` (KONSEP §A1, `config/modules.php`).
Tabel produksi:
- `egy_parameters` ▣: parameter tarif TOU, feed-in, demand charge, emisi, degradasi (label S1).
- `egy_generation_assets`: aset pembangkit (kapasitas MW/kW, fuel type, min-stable, marginal cost, committed status).
- `egy_grid_nodes`: gardu induk / transformator / penyulang distribusi.
- `egy_smart_meters`: registri meter tunggal (tipe: `electricity`, `water`, `gas`, `thermal`).
- `egy_meter_readings` ▣: pembacaan 15 menit dengan index/unique `(meter_id, interval_start)`.
- `egy_utility_consolidated_invoices`: invoice utilitas dengan state machine 8 status.
- `egy_utility_invoice_lines` ▣: baris rincian tagihan energi, demand charge, net metering credit.
- `egy_dispatch_runs`: catatan proses dispatch per jam.
- `egy_dispatch_orders` ▣: detail per unit pembangkit, dispatch MW, status curtailment.
- `egy_ppa_contracts`: kontrak jual beli listrik jangka panjang dengan PPA terms.
- `egy_demand_response_events` ▣: event penggeseran beban fleksibel EV dan alokasi reward.
- `egy_status_transitions` ▣: log append-only transisi state machine invoice dan dispatch.

### State Machine Eksplisit (≥ 6 Status)
1. **Utility Consolidated Invoice Lifecycle**:
   `draft` -> `validated` -> `issued` -> `partially_paid` -> `paid` -> `overdue` -> `disputed` -> `adjusted` (8 status).

### Akun Buku Besar & Arah Posting (D126-6)
Semua transaksi diposting double-entry integer Rupiah tanpa nilai negatif (kecuali akun clearing/escrow):
| Kejadian | Debit | Kredit | Keterangan |
|---|---|---|---|
| Tagihan listrik properti | `ar:egy:customer:{party}:IDR` | `egy:grid_electricity_revenue:IDR` | Piutang tagihan listrik ke pelanggan |
| Beli dari pembangkit/PPA | `egy:power_purchase_expense:IDR` | `ap:egy:generator:{party}:IDR` | Beban beli daya ke genco/mitra |
| Feed-in surplus pelanggan | `egy:power_purchase_expense:IDR` | `ar:egy:customer:{party}:IDR` | Pengurang tagihan pelanggan net-metering |
| Reward demand response | `egy:demand_response_expense:IDR` | `wallet:user:{penerima}:IDR` | Pembagian reward penggeseran beban EV |
| Pembayaran tagihan | `wallet:clearing:IDR` | `ar:egy:customer:{party}:IDR` | Penerimaan pembayaran dari clearing/wallet |

### Kontrak Lintas Modul
1. **G7-K8** `Modules\Mobility\Contracts\EvChargingSessionSource`:
   `public function sessionsBetween(int $siteAssetId, \Carbon\CarbonImmutable $from, \Carbon\CarbonImmutable $to): array`
   Membaca sesi pengisian EV fleksibel dari modul Mobility.
2. `Modules\Asset\Contracts\AssetRegistryLookupInterface`:
   Memverifikasi aset pembangkit dan meter yang terdaftar di modul Asset.
3. `Modules\Contract\Contracts\ContractLookupInterface`:
   Memvalidasi status dan ketentuan kontrak PPA aktif.
4. `Modules\Esg\Contracts\EmissionBenefitRecorder`:
   Mencatat pengurangan emisi dari penggeseran beban puncak dan surplus energi terbarukan.
