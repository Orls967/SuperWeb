# Arsitektur Event Domain, Outbox, & Idempotensi Modul WMS

Dokumen ini menjelaskan bagaimana modul `Wms` menerbitkan event domain ke seluruh ekosistem secara andal menggunakan pola *Transactional Outbox* dan kunci idempotensi deterministik.

---

## 1. Prinsip Dasar Event-Driven di WMS
Setiap perubahan status signifikan pada fisik gudang tidak boleh memanggil modul eksternal (`Store`, `Logistics`, `EnterpriseFinance`) secara sinkron di dalam *controller* atau *loop* berat. Sebagai gantinya:
1. Mutasi basis data (`wms_bin_stocks`, `wms_tasks`, `wms_task_events`, `wms_receiving_receipts`) dan posting buku besar (`WmsLedgerPoster`) dieksekusi di dalam satu `DB::transaction()`.
2. Event domain dipublikasikan melalui Laravel `Event::dispatch()` dan `WmsEventCatalog` (`OutboxBusInterface`) sehingga konsumen eksternal dapat memprosesnya secara asinkron dan idempoten.

---

## 2. Katalog Event Resmi (`WmsEventCatalog::TOPICS`)

### 2.1 `warehouse.task.confirmed.v1`
- **Kelas Event**: `Modules\Wms\Domain\Events\WarehouseTaskConfirmedEvent`
- **Pemicu**:
  - `ExecuteDirectedPutawayAction` setelah `WarehouseTaskStateMachine` mencapai status `confirmed` dan `closed`.
  - `AllocateOutboundWavePickingAction` setelah tugas `pick` dikonfirmasi dan reservasi di-commit.
- **Skema Payload**:
  - `event`: `"warehouse.task.confirmed.v1"`
  - `task_id`: integer/string ID tugas
  - `warehouse_id`: integer/string ID gudang
  - `kind`: `"putaway"` | `"pick"` | `"pack"` | `"stage"` | `"replenish"`
  - `actor_user_id`: integer ID operator (nullable)
  - `occurred_at`: stempel waktu ISO-8601 dari `ClockInterface`

### 2.2 `warehouse.stock.adjusted.v1`
- **Kelas Event**: `Modules\Wms\Domain\Events\WarehouseStockAdjustedEvent`
- **Pemicu**:
  - `RecordCycleCountAdjustmentAction` ketika hasil hitung fisik berbeda atau cocok dengan saldo sistem.
- **Skema Payload**:
  - `event`: `"warehouse.stock.adjusted.v1"`
  - `warehouse_id`: ID gudang
  - `product_id`: ID produk
  - `delta_qty_base`: selisih kuantitas dalam integer base unit (positif untuk *gain*, negatif untuk *shrinkage*)
  - `delta_value_idr`: selisih nilai dalam integer IDR minor units
  - `reason`: string referensi (mis. `"cycle_count:CC-20261113-01"`)
  - `occurred_at`: ISO-8601

### 2.3 `warehouse.transfer.received.v1`
- **Kelas Event**: `Modules\Wms\Domain\Events\WarehouseTransferReceivedEvent`
- **Pemicu**:
  - `ProcessCrossDockingTransferAction` ketika pengiriman antar-gudang tiba dan dicatat di gudang tujuan.
- **Skema Payload**:
  - `event`: `"warehouse.transfer.received.v1"`
  - `transfer_id`: integer hash/ID transfer
  - `from_warehouse_id`: ID gudang asal
  - `to_warehouse_id`: ID gudang tujuan
  - `total_value_idr`: nilai persediaan yang diterima di tujuan (IDR minor units)
  - `occurred_at`: ISO-8601

---

## 3. Standar Kunci Idempotensi (`IDEM:WMS:*`)

Seluruh operasi posting buku besar menggunakan format kunci idempotensi yang deterministik (dilarang menggunakan `Str::uuid()` atau nilai acak pada kunci idempotensi):

| Operasi | Format `idempotency_key` | `reference_type` |
|---|---|---|
| Saldo Awal (D41-1) | `IDEM:WMS:OPENING:{WAREHOUSE_CODE}` | `WMS_OPENING_BALANCE` |
| Penerimaan Inbound (D41-2) | `IDEM:WMS:INBOUND:{RECEIPT_REF}` | `WMS_RECEIPT` |
| Pengeluaran Outbound (D41-3) | `IDEM:WMS:OUTBOUND:{WAVE_REF}` | `WMS_ISSUE` |
| Transfer Keluar (D41-4) | `IDEM:WMS:TRANSFER_OUT:{TRANSFER_REF}` | `WMS_TRANSFER` |
| Transfer Masuk (D41-5) | `IDEM:WMS:TRANSFER_IN:{TRANSFER_REF}` | `WMS_TRANSFER` |
| Cycle Count Gain (D41-6) | `IDEM:WMS:CC_GAIN:{CYCLE_COUNT_REF}` | `WMS_CYCLE_COUNT` |
| Cycle Count Shrinkage (D41-7) | `IDEM:WMS:CC_SHRINK:{CYCLE_COUNT_REF}` | `WMS_CYCLE_COUNT` |
| Write-Off Rusak/Kadaluarsa (D41-8) | `IDEM:WMS:WRITEOFF:{REF}` | `WMS_WRITEOFF` |
| Transit Loss (D41-9) | `IDEM:WMS:TRANSIT_LOSS:{TRANSFER_REF}` | `WMS_TRANSFER` |
| Revaluasi Mark-Up (D41-10) | `IDEM:WMS:REVAL_UP:{REF}` | `WMS_REVALUATION` |
| Revaluasi Mark-Down (D41-11) | `IDEM:WMS:REVAL_DOWN:{REF}` | `WMS_REVALUATION` |
| Tagihan 3PL (D41-12) | `IDEM:WMS:3PL:{BILLING_REF}` | `WMS_3PL_BILLING` |

---

## 4. Penanganan Duplikasi & Retry
Apabila sebuah *job* atau *command* dijalankan ulang (mis. `php artisan wms:post-opening-balance` dijalankan dua kali berturut-turut):
1. `WmsLedgerPoster::postBalanced()` memeriksa keberadaan `idempotency_key` pada tabel `bank_ledger_transactions`.
2. Jika sudah ada, metode mengembalikan `false` tanpa melempar pengecualian dan tanpa membuat entri ganda.
3. Pemanggil (`PostOpeningBalanceAction`) melaporkan `already_posted: true` sehingga integrasi tetap bersih dan aman untuk *cron retry*.

