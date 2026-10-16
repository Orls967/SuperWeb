# Quality Gate Report: Fase R2

- **Commit:** `58f3b6500e13e7ae6a0df309fc69ed7c9364ad0e` (58f3b65)
- **Tanggal:** 2026-10-16T15:08:29+00:00
- **PHP:** 8.4.26
- **Database:** sqlite (v3.53.4)
- **Status Keseluruhan:** PASS 🟢

## 1. Ringkasan Test Suite (Pest / JUnit)
- **Total Test:** 2850
- **Total Assertion:** 14675
- **Durasi:** 714.05s
- **Gagal / Error:** 0 failures, 0 errors

## 2. Hasil Perintah Protokol P4
| Perintah | Deskripsi | Exit Code | Status |
|---|---|---|---|
| `composer validate --strict` | Langkah gate: composer validate --strict (0.20s) | 0 | PASS 🟢 |
| `vendor/bin/pest --parallel --log-junit=storage/logs/pest-junit.xml` | Langkah gate: pest (81.24s) | 0 | PASS 🟢 |
| `vendor/bin/pint --test` | Langkah gate: pint --test (1.93s) | 0 | PASS 🟢 |
| `php artisan arch:scan --json` | Langkah gate: arch:scan --json (6.26s) | 0 | PASS 🟢 |
| `php artisan migrate:fresh --seed --force` | Langkah gate: migrate:fresh --seed (26.52s) | 0 | PASS 🟢 |
| `php artisan bank:reconcile` | Langkah gate: bank:reconcile (0.30s) | 0 | PASS 🟢 |
| `php artisan chain:audit-all` | Langkah gate: chain:audit-all (0.21s) | 0 | PASS 🟢 |
| `php artisan super:health-check` | Langkah gate: super:health-check (0.21s) | 0 | PASS 🟢 |
| `npm run build` | Langkah gate: npm run build (1.05s) | 0 | PASS 🟢 |

## 3. Status Test Blok Bukti Fase R2
| Item | Nama Test | File | Status (JUnit) | Durasi |
|---|---|---|---|---|
| R2.1 | `menghitung tagihan hidang dua kali memposting HPP tepat sekali dan mengurangi porsi tray sekali` | `modules/Resto/tests/Feature/HidangBillIdempotencyTest.php` | PASS 🟢 | 0.352s |
| R2.1 | `menghitung tagihan hidang dua kali tidak meresirkulasi ulang piring yang dikembalikan utuh` | `modules/Resto/tests/Feature/HidangBillIdempotencyTest.php` | PASS 🟢 | 0.353s |
| R2.1 | `memperbarui diskon pada hitung tagihan ulang hanya mengubah grand total tanpa efek samping stok atau ledger` | `modules/Resto/tests/Feature/HidangBillIdempotencyTest.php` | PASS 🟢 | 0.346s |
| R2.1 | `endpoint kalkulasi tagihan pos resto oleh role berhak dan tidak berhak` | `modules/Resto/tests/Feature/HidangBillIdempotencyTest.php` | PASS 🟢 | 0.372s |
| R2.2 | `isi penawaran tidak terbaca offer null dan terenkripsi sebelum tender dibuka` | `modules/Procurement/tests/Feature/BlindTenderSealTest.php` | PASS 🟢 | 2.491s |
| R2.2 | `is seal intact mengembalikan true untuk penawaran sah dan false bila dimanipulasi` | `modules/Procurement/tests/Feature/BlindTenderSealTest.php` | PASS 🟢 | 2.461s |
| R2.2 | `open bids membuka seluruh penawaran tersegel dan memulihkan offer setelah tenggat` | `modules/Procurement/tests/Feature/BlindTenderSealTest.php` | PASS 🟢 | 2.356s |
| R2.2 | `manipulasi isi penawaran atau seal hash setelah segel ditolak saat open bids` | `modules/Procurement/tests/Feature/BlindTenderSealTest.php` | PASS 🟢 | 2.490s |
| R2.3 | `test_contract_clauses_create_renders_successfully` | `tests/Feature/ControllerErrorPathsAndContractViewsTest.php` | PASS 🟢 | 0.141s |
| R2.3 | `test_contract_reports_renders_successfully_with_active_contract` | `tests/Feature/ControllerErrorPathsAndContractViewsTest.php` | PASS 🟢 | 0.139s |
| R2.3 | `test_agency_approve_payout_redirects_with_errors_on_domain_exception` | `tests/Feature/ControllerErrorPathsAndContractViewsTest.php` | PASS 🟢 | 0.143s |
| R2.3 | `test_distribution_cover_territory_redirects_with_errors_on_domain_exception` | `tests/Feature/ControllerErrorPathsAndContractViewsTest.php` | PASS 🟢 | 0.101s |
| R2.3 | `test_pricing_store_price_list_item_redirects_with_errors_on_domain_exception` | `tests/Feature/ControllerErrorPathsAndContractViewsTest.php` | PASS 🟢 | 0.131s |
| R2.4 | `commit dan release membuat baris stock movement baru yang menautkan reservasi tanpa memutasi baris lama` | `modules/Inventory/tests/Feature/InventoryAppendOnlyAndContractTest.php` | PASS 🟢 | 0.113s |
| R2.4 | `memanggil commit atau release dua kali pada reservasi yang sama bersifat idempoten` | `modules/Inventory/tests/Feature/InventoryAppendOnlyAndContractTest.php` | PASS 🟢 | 0.137s |
| R2.4 | `model stock movement menolak update dan delete secara langsung` | `modules/Inventory/tests/Feature/InventoryAppendOnlyAndContractTest.php` | PASS 🟢 | 0.093s |
| R2.4 | `deduct ingredient dan add ingredient bekerja secara atomik lewat kontrak resto dengan unique constraint outlet id dan ingredient id` | `modules/Inventory/tests/Feature/InventoryAppendOnlyAndContractTest.php` | PASS 🟢 | 0.105s |
| R2.5 | `test_subscriber_a_sukses_dan_subscriber_b_gagal_pada_dispatch_pertama_maka_retry_hanya_mengirim_ulang_ke_subscriber_b` | `modules/Core/tests/Feature/OutboxReliabilityAndSsrfTest.php` | PASS 🟢 | 0.094s |
| R2.5 | `test_dua_worker_outbox_paralel_mengklaim_pesan_secara_atomik_tanpa_duplikasi_pengiriman` | `modules/Core/tests/Feature/OutboxReliabilityAndSsrfTest.php` | PASS 🟢 | 0.047s |
| R2.5 | `test_url_webhook_privat_localhost_dan_cloud_metadata_169_254_169_254_ditolak_oleh_guard_anti_ssrf` | `modules/Core/tests/Feature/OutboxReliabilityAndSsrfTest.php` | PASS 🟢 | 0.032s |
| R2.5 | `test_target_subscriber_non_webhook_dieksekusi_lewat_handler_terdaftar_dan_gagal_jika_handler_tidak_ditemukan` | `modules/Core/tests/Feature/OutboxReliabilityAndSsrfTest.php` | PASS 🟢 | 0.050s |
| R2.6 | `test_critical_dtc_grounds_vehicle_device` | `modules/Telematics/tests/Feature/TelematicsTest.php` | PASS 🟢 | 0.036s |
| R2.6 | `test_non_critical_dtc_does_not_ground_device_and_does_not_trigger_critical_anomaly` | `modules/Telematics/tests/Feature/TelematicsTest.php` | PASS 🟢 | 0.041s |
| R2.6 | `test_seven_day_rolling_baseline_calculated_from_actual_historical_ticks` | `modules/Telematics/tests/Feature/TelematicsTest.php` | PASS 🟢 | 0.037s |
| R2.6 | `test_anomaly_event_dispatched_after_commit_and_deduplicated_hourly` | `modules/Telematics/tests/Feature/TelematicsTest.php` | PASS 🟢 | 0.033s |
| R2.7 | `test_can_record_ghg_emissions` | `modules/Esg/tests/Feature/EsgTest.php` | PASS 🟢 | 0.041s |
| R2.7 | `test_record_emission_rejects_unknown_activity_type_without_silent_default` | `modules/Esg/tests/Feature/EsgTest.php` | PASS 🟢 | 0.036s |
| R2.7 | `test_document_numbering_uses_gapless_sequence_instead_of_random` | `modules/Esg/tests/Feature/EsgTest.php` | PASS 🟢 | 0.025s |
| R2.7 | `test_can_purchase_and_retire_carbon_credits` | `modules/Esg/tests/Feature/EsgTest.php` | PASS 🟢 | 0.015s |
| R2.7 | `test_esg_web_index_accessible` | `modules/Esg/tests/Feature/EsgTest.php` | PASS 🟢 | 0.027s |
| R2.8 | `test_webhook_dispatch_failure_records_failed_status_instead_of_fake_success` | `modules/Integration/tests/Feature/FakeStatusEliminationTest.php` | PASS 🟢 | 0.043s |
| R2.8 | `test_malformed_edi_message_is_rejected_rather_than_faked_accepted` | `modules/Integration/tests/Feature/FakeStatusEliminationTest.php` | PASS 🟢 | 0.021s |
| R2.8 | `test_claim_without_evidence_attachment_is_marked_simulated_unverified` | `modules/Integration/tests/Feature/FakeStatusEliminationTest.php` | PASS 🟢 | 0.015s |
| R2.8 | `test_claim_with_persisted_evidence_document_is_published_verified` | `modules/Integration/tests/Feature/FakeStatusEliminationTest.php` | PASS 🟢 | 0.023s |
| R2.9 | `test_hcm_register_employee_rejects_missing_salary_and_bank_details_with_validation_exception` | `tests/Feature/SilentDefaultEliminationTest.php` | PASS 🟢 | 0.019s |
| R2.9 | `test_telematics_ingest_tick_rejects_missing_sensor_metrics_with_validation_exception` | `tests/Feature/SilentDefaultEliminationTest.php` | PASS 🟢 | 0.028s |
| R2.9 | `test_esg_record_emission_rejects_unknown_activity_without_silent_default_factor` | `tests/Feature/SilentDefaultEliminationTest.php` | PASS 🟢 | 0.013s |
| R2.9 | `test_explicit_valid_payloads_create_records_cleanly_without_hidden_defaults` | `tests/Feature/SilentDefaultEliminationTest.php` | PASS 🟢 | 0.018s |

## 4. Ringkasan Arsitektur (`arch:scan`)
- **Status Baseline:** Terpasang (6869 pelanggaran terbaseline).

| Aturan | Deskripsi Singkat | Jumlah Pelanggaran |
|---|---|---|
| `A1` | Import/penggunaan Domain modul lain | 246 |
| `A2` | Tabel berprefiks modul lain / tidak terdaftar | 5240 |
| `A3` | Uang bertipe float / kolom IDR decimal | 319 |
| `A4` | Idempotency key dari nilai acak/waktu | 0 |
| `A5` | Tipe posting ledger > 32 char / di luar registry | 0 |
| `A6` | Parameter bool kontrol di Application/ | 132 |
| `A7` | Hash tanpa kunci untuk data sensitif | 0 |
| `A8` | Carbon/Date::setTestNow di produksi | 3 |
| `A9` | Kernel bergantung pada modul bisnis | 34 |
| `A10` | Model dengan $guarded = [] | 162 |
| `A11` | File PHP tanpa declare(strict_types=1) | 145 |
| `A12` | Kolom *_id di migrasi tanpa FK | 588 |
| `A13` | back()->errors() (HTTP 500 di jalur galat) | 0 |

## 5. Verifikasi
*(Bagian ini wajib diisi oleh verifikator independen sebelum mengubah status fase ke ✅ sesuai P7).*

- **Tanggal:** 
- **Verifikator:** 
- **Commit yang diverifikasi:** 
- **Keputusan:** BELUM DIVERIFIKASI

| # | Hasil | Bukti (command / commit / output) | Catatan |
|---|---|---|---|
| C1 | BELUM DIVERIFIKASI | | |
| C2 | BELUM DIVERIFIKASI | | |
| C3 | BELUM DIVERIFIKASI | | |
| C4 | BELUM DIVERIFIKASI | | |
| C5 | BELUM DIVERIFIKASI | | |
| C6 | BELUM DIVERIFIKASI | | |
| C7 | BELUM DIVERIFIKASI | | |
| C8 | BELUM DIVERIFIKASI | | |
| C9 | BELUM DIVERIFIKASI | | |
| C10 | BELUM DIVERIFIKASI | | |
| C11 | BELUM DIVERIFIKASI | | |
| C12 | BELUM DIVERIFIKASI | | |
| C13 | BELUM DIVERIFIKASI | | |
| C14 | BELUM DIVERIFIKASI | | |

**Minus yang ditambahkan verifikator:** 
**Alasan 🔁 (bila ada):**

