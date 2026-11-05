# Desain & Bukti Kondisi Lama — Fase 157 (Reasuransi, Kapital & CAT Modelling)

## L.0 Bukti kondisi lama (diukur 2026-11-06 pada master `4dd3ec9d`)

| Aspek | Temuan | Penilaian |
|---|---|---|
| Tabel `ins_*` modul Insurance | `ins_products`, `ins_policies`, `ins_claims`, `ins_policy_earnings`, `ins_policy_sequences`, `ins_parametric_oracles`, `ins_full_products/policies/schedules/claims`, `ins_actuarial_triangles` | ada; tidak ada tabel treaty/cession/bordereau/solvabilitas/CAT/cat bond |
| Reasuransi di `FullInsuranceService::quoteAndBindPolicy` | cession = `intdiv(gross × quota_share_pct, 100)` per **produk**, memposting `reins:ceded_premium_expense` tanpa lawan utang ke reinsurer | **salah**: tanpa treaty, tanpa reinsurer (Party), tanpa layer, bulat ke bawah (bukan HalfUp) |
| `settleClaim` | recoverable = `intdiv(net × pct, 100)` ke `reins:claims_recoverable:IDR` | **salah**: flat, tidak per treaty/reinsurer, tidak ada XoL/reinstatement |
| `calculateUnearnedPremium` | memakai `time()`/`strtotime` (tanpa ClockInterface), retained saja | **salah** (U-2) |
| Penomoran polis/klaim | `count()+1` | **salah**: tidak gapless/konkuren-aman (A3) |
| Akun ledger reasuransi | hanya `reins:ceded_premium_expense:IDR`, `reins:claims_recoverable:IDR` | tidak lengkap (tidak ada payable/commission/reinstatement per reinsurer) |
| Solvabilitas | `Integration\ReinsuranceAndCatService::calculateSolvencyRatio` (float, parameter pemanggil) | referensi saja (X2/X21), tidak dipakai |
| CAT | `allocateCatLoss(peril, float…)` tanpa katalog/YLT | referensi saja, tidak dipakai |
| Cat bond | tidak ada; Rwa punya `RwaTokenService`, `VotingWeightReader` | tidak ada kontrak G7-K3 |
| Command | `ins:audit` (hanya memeriksa ceded+retained=gross kolom polis) | audit tautologis: dua sumber tidak independen |
| Rute | `/insurance/*` (19 rute, 156) — tidak ada rute reasuransi/kapital/CAT/cat bond/investor | tidak ada |
| Test | 24 test Insurance hijau (82 assertion) | tidak menyentuh treaty/CAT |
| Algoritma bersama | `ChainLadder`, `LargestRemainder`, `CanonicalHashChain` ada; `MinStd`, `ProgramAllocator`, `RiskBasedCapital`, `ExceedanceCurve`, `EventCatalogGenerator` **tidak ada** | dibuat aditif di `Shared/Domain/Algorithms/*` |

Bukti perintah: `php artisan route:list --path=insurance` (19 rute), `vendor/bin/pest tests/Acceptance/Insurance modules/Insurance/tests` → 24 passed (82 assertions); `php artisan arch:scan` → bersih.

## L.1 Desain

### Modul & prefiks
Modul pemilik `Insurance` (`ins_`, KONSEP §A1). Tidak ada modul baru. Nama tabel baru sengaja berprefiks `ins_rein_*`, `ins_cat_*`, `ins_solv_*`, `ins_parameters` agar tidak bentrok dengan tabel kerangka `reins_*`/`ins_*` milik `Integration` (frozen).

### Tabel (semua FK nyata; fakta append-only ditandai ▣)
| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `ins_parameters` ▣ | code, version, effective_from, source, simulation_label, value(json), is_fixture, owner_confirmed_at | G7-D1; unique(code, version) |
| `ins_rein_treaties` | treaty_code (gapless), type, line_category, status, inception/expiry, quota_share_bps, surplus_retention_idr, surplus_lines, attachment_idr, limit_idr, reinstatement_rates_bps(json), layer_premium_idr, commission_bps, sl_attachment_lr_bps, sl_limit_lr_bps, approval_ref | state machine 7 status |
| `ins_rein_treaty_panel` | treaty_id→, reinsurer_party_id→pty_parties, share_bps | Σ share = 10000 (Hamilton) |
| `ins_rein_treaty_history` ▣ | treaty_id→, from/to status, reason, actor_user_id, approval_ref | append-only |
| `ins_cession_events` ▣ | event_key unique, kind, policy_id→, claim_id→, period_ym, gross/retained/ceded/commission/reinstatement, snapshot(json)+hash, ledger_transaction_id | satu per kejadian premi/klaim |
| `ins_cession_lines` ▣ | event_id→, treaty_id→, layer_code, reinsurer_party_id→, ceded/commission/reinstatement | per reinsurer per layer |
| `ins_rein_bordereaux` | number (gapless), treaty_id→, reinsurer_party_id→, kind, period_ym, status, totals, reverses_id→ | immutable setelah `sent` |
| `ins_rein_bordereau_lines` ▣ | bordereau_id→, cession_line_id→, amount, commission | |
| `ins_rein_account_currents` | number, reinsurer_party_id→, period_ym, premium/commission/recoverable/reinstatement/net, status, settlement_tx | unique(reinsurer, period) |
| `ins_solv_snapshots` ▣ | valuation_date, parameter_versions(json), risk_charges(json), correlation(json), operational, available_capital, rbc, ratio_bps, alert_level, input_hash | |
| `ins_solv_actions` | snapshot_id→, action_type, params, simulated_ratio_bps, status, approval_ref | what-if → approve → execute |
| `ins_cat_runs` | run_code, seed, years, parameter_versions, catalog_hash, exposure_hash, aal/aep/oep/pml/tvar, status | state machine 6 status |
| `ins_cat_exposures` ▣ / `ins_cat_events` ▣ / `ins_cat_year_losses` ▣ | per run | eksposur diturunkan dari polis aktif (bukan hard-code) |
| `ins_cat_plans` | run_id→, structure, attachment, limit, reinstatements, premium, ceded_pml100/250 | perbandingan struktur proteksi |
| `ins_cat_bonds` | bond_code, asset_code, rwa_asset_id, principal, outstanding, units, spread_bps, attachment, exhaustion, status | state machine 7 status |
| `ins_cat_bond_coupons` ▣ / `ins_cat_bond_coupon_payouts` ▣ / `ins_cat_bond_events` ▣ | | kupon & trigger |
`ins_full_policies` ditambah `region_code` (sumber eksposur CAT).

### Akun & arah posting (ditambahkan ke `InsuranceAccounts`, kind & sisi normal sesuai KONSEP §A2.1)
| Kejadian | Debit | Kredit |
|---|---|---|
| Penerbitan polis (bruto) | `ar:ins:premium:IDR` | `ins:unearned_premium:IDR` |
| Premi dialihkan | `reins:ceded_premium_expense:IDR` | `reins:payable:{reinsurer}:IDR` |
| Komisi reasuransi | `reins:payable:{reinsurer}:IDR` | `reins:commission_income:IDR` |
| Klaim recoverable | `reins:recoverable:{reinsurer}:IDR` | `ins:claim_expense:IDR` |
| Premi reinstatement | `reins:reinstatement_premium_expense:IDR` | `reins:payable:{reinsurer}:IDR` |
| Settlement account current | `reins:payable:{r}` (+ kas bila diterima) | `reins:recoverable:{r}` (+ `clearing:external:IDR` bila dibayar) |
| Kupon cat bond | `ins:catbond_coupon_expense:IDR` | `wallet:user:{holder}:IDR` (Hamilton) |
| Trigger cat bond | `ins:catbond_recoverable:IDR` | `ins:claim_expense:IDR` |

### Event (§A4.1)
`ins.policy.bound.v1` (produsen: bind; konsumen: cession listener), `ins.claim.settled.v1` (konsumen: cession klaim), `ins.cession.recorded.v1`, `ins.catbond.triggered.v1` (konsumen: write-down Rwa via G7-K3).

### Kontrak lintas modul
G7-K3 `Modules\Rwa\Contracts\CatBondTokenRegistry` (pembuat: P1; implementasi aditif di Rwa). Dipakai: `VotingWeightReader`, `ClockInterface`, `DocumentNumberingInterface`, `ApprovalEngineInterface`, `OutboxBusInterface`, `Ledger`.

### Role & rute
`reinsurance_mgr` (treaty, bordereau, account current, cat bond), `actuary` (kapital, CAT), `cfo_view` (dashboard baca), `investor` (portal `/investor/cat-bonds`, Policy kepemilikan holding). Semua rute tercatat di `tests/Architecture/route-roles.php`.

### Audit dua sumber
`ins:reinsurance-audit`: (1) Σ ceded + retained = bruto per kejadian (cession_events vs polis/klaim); (2) bordereau = cession_lines = posting ledger per reinsurer; (3) saldo `reins:payable/recoverable` = Σ cession_lines − settlement; (4) kupon cat bond = distribusi `VotingWeightReader`; fixture `ReinsAuditCorruptionFixture`.

### Tingkat simulasi & skala
Semua parameter regulasi/aktuaria S1 (`ins_parameters`, label S1, fixture ditandai). Seeder T1: 20.000 polis, 6 treaty, 8 reinsurer, katalog 5.000 event (seed tetap).

### Keputusan
- D157-A: cession bertreaty menggantikan quota share datar produk (kolom `quota_share_pct` tidak lagi dipakai mesin; dibiarkan untuk kompatibilitas data 156).
- D157-B: ceded premium diakui sebagai beban saat terbit (S1; tanpa unearned ceded premium).
- D157-C: eksposur CAT = Σ uang pertanggungan polis aktif per (region, peril) dengan peta lini→peril dari parameter `cat.line_peril_map`.
