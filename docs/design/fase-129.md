# Desain Teknis — Fase 129: Microgrid, Storage & Resilience

## 1. Arsitektur Domain & Database Schema
Perluasan tabel `egy_`:
1. `egy_microgrids`:
   - `id`, `microgrid_code`, `site_name`, `operating_mode` (`grid_connected`, `islanded`, `blackout`, `restoring`), `total_capacity_kw`, `current_generation_kw`, `current_load_kw`, `is_black_start_capable`.
2. `egy_microgrid_feeders`:
   - `id`, `microgrid_id`, `feeder_code`, `feeder_name`, `tier` (`tier_1_critical`, `tier_2_essential`, `tier_3_commercial`, `tier_4_flexible`), `priority_rank`, `rated_load_kw`, `is_energized`.
3. `egy_bess_assets`:
   - `id`, `bess_code`, `site_name`, `capacity_kwh`, `max_charge_power_kw`, `max_discharge_power_kw`, `current_soc_bps` (0-10000 bps = 0-100%), `cycle_count`, `state_of_health_bps`, `replacement_cost_idr`.
4. `egy_bess_dispatches`:
   - `id`, `dispatch_number`, `bess_asset_id`, `dispatch_type` (`arbitrage`, `peak_shaving`, `frequency_support`, `black_start`), `energy_kwh`, `charging_cost_idr`, `discharging_revenue_idr`, `degradation_cost_idr`, `net_profit_idr`, `ledger_transaction_id`.
5. `egy_generator_logs`:
   - `id`, `generator_asset_code`, `site_name`, `run_type` (`weekly_no_load_test`, `weekly_load_test`, `grid_outage_support`, `black_start`), `start_time`, `end_time`, `duration_minutes`, `fuel_consumed_liters`, `load_percentage_bps`, `emissions_kg_co2e`, `is_passed`.
6. `egy_demand_response_events`:
   - `id`, `event_code`, `curtailment_signal_id`, `start_time`, `target_curtailment_kw`, `achieved_curtailment_kw`, `response_time_seconds`, `incentive_revenue_idr`, `status`, `ledger_transaction_id`.
7. `egy_black_start_plans`:
   - `id`, `plan_code`, `microgrid_id`, `status` (`draft`, `in_progress`, `completed`, `failed`), `step_sequence_json`, `execution_log_json`, `certificate_hash`.

## 2. Tiga Algoritma Bersertifikat
- **E12 `BatteryArbitrageOptimizer`**:
  Memilih strategi charge/discharge 24 jam dengan optimasi keuntungan arbitrase dikurangi biaya penuaan/degradasi baterai berbasis tarif Time-of-Use.
- **E13 `MicrogridLoadSheddingKnapsack`**:
  0/1 Knapsack berbobot prioritas untuk menentukan pemutusan feeder secara deterministik ketika microgrid terisolasi (islanding) agar frekuensi grid stabil.
- **E14 `BlackStartSynchronizationSequencer`**:
  Topological sorting urutan pembangkitan mandiri (Cranking Genset -> Black-start unit -> Energize busbar primer -> Step-up transformer -> Critical load -> Grid reconnection).

## 3. Akun Ledger & Aturan Integritas
- Pendapatan Arbitrase BESS: `egy:bess_arbitrage_revenue:IDR` (REVENUE)
- Biaya Energi BESS: `egy:bess_energy_cost:IDR` (EXPENSE)
- Beban Degradasi BESS: `egy:bess_degradation_expense:IDR` (EXPENSE)
- Cadangan Penggantian Baterai: `egy:bess_replacement_reserve:IDR` (LIABILITY)
- Pendapatan Insentif Curtailment PLN: `egy:demand_response_incentive_revenue:IDR` (REVENUE)
- Piutang Operator Grid PLN: `ar:egy:grid_operator:IDR` (ASSET)
