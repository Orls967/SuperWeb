# Katalog Spesifikasi Event Universal Event Spine v1.0

Dokumen ini mendefinisikan katalog spesifikasi resmi event domain lintas modul (v1.0) yang berjalan di atas Universal Event Spine. Setiap event memiliki spesifikasi skema kanonikal, produsen resmi, konsumen terdaftar, aturan kunci partisi/agregat, serta parameter SLA pengiriman.

---

## 1. Konvensi Penamaan & Struktur Header Event

Setiap event yang diterbitkan ke Universal Event Spine harus mematuhi konvensi penamaan topik hierarkis tiga segmen:
`<domain>.<entitas>.<aksi_past_tense>`

Contoh: `finance.invoice.issued`, `logistics.shipment.dispatched`.

### Standar Amplop Event (Universal Event Envelope)
Setiap rekaman event di tabel `sim_event_spine` memuat struktur amplop berikut:
- **`event_id`** (`string`): UUID v4 unik global untuk setiap kemunculan event.
- **`topic`** (`string`): Topik event hierarkis yang terdaftar dalam katalog.
- **`aggregate_type`** (`string`): Nama entitas domain aggregate root (misal `Invoice`, `Shipment`).
- **`aggregate_id`** (`string`): Pengenal unik instans aggregate untuk penjaminan urutan partisi.
- **`sequence`** (`int`): Nomor urut monotonik per `aggregate_id` (dimulai dari 1) untuk deteksi celah A4.
- **`schema_version`** (`int`): Versi skema JSON payload (dimulai dari 1).
- **`occurred_at`** (`string`): Timestamp ISO-8601 UTC saat peristiwa bisnis terjadi di dunia nyata.
- **`published_at`** (`string`): Timestamp ISO-8601 UTC saat pesan berhasil ditulis ke outbox/spine.
- **`correlation_id`** (`string`): ID korelasi terdistribusi untuk penelusuran tracing multi-langkah.
- **`causation_id`** (`string`): `event_id` atau `command_id` langsung yang memicu terbitnya event ini.
- **`payload`** (`object`): Data faktual peristiwa bisnis yang divalidasi ketat terhadap JSON schema terdaftar.

---

## 2. Katalog Domain & Spesifikasi Event Resmi

### 2.1 Domain Keuangan & Akuntansi (Finance & Accounting)

#### `finance.invoice.issued`
- **Deskripsi**: Diterbitkan saat faktur penjualan atau piutang resmi diterbitkan ke pelanggan.
- **Produsen**: `Modules\Finance\Application\Services\InvoiceService`
- **Konsumen Terdaftar**:
  - `finance_gl_poster`: Membuat jurnal piutang dan pendapatan.
  - `notification_dispatcher`: Mengirim email dan notifikasi WhatsApp invoice ke pelanggan.
  - `tax_calculator_sync`: Merekonsiliasi faktur pajak elektronik.
- **Aggregate**: `Invoice` (`aggregate_id` = nomor invoice / ID)
- **SLA Delivery**: $\le 5$ detik (P95)
- **JSON Schema v1**:
```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "required": ["invoice_number", "customer_id", "currency", "subtotal", "tax_amount", "total_amount", "due_date", "lines"],
  "properties": {
    "invoice_number": { "type": "string", "minLength": 5 },
    "customer_id": { "type": "integer", "minimum": 1 },
    "currency": { "type": "string", "enum": ["IDR", "USD", "SGD", "EUR"] },
    "subtotal": { "type": "number", "minimum": 0 },
    "tax_amount": { "type": "number", "minimum": 0 },
    "total_amount": { "type": "number", "minimum": 0 },
    "due_date": { "type": "string", "format": "date" },
    "payment_terms": { "type": "string" },
    "lines": {
      "type": "array",
      "minItems": 1,
      "items": {
        "type": "object",
        "required": ["item_id", "description", "quantity", "unit_price", "amount"],
        "properties": {
          "item_id": { "type": "integer" },
          "description": { "type": "string" },
          "quantity": { "type": "number", "minimum": 0.001 },
          "unit_price": { "type": "number", "minimum": 0 },
          "amount": { "type": "number", "minimum": 0 }
        }
      }
    }
  }
}
```

#### `finance.payment.received`
- **Deskripsi**: Diterbitkan saat pembayaran dari pelanggan telah terkonfirmasi masuk rekening kas/bank.
- **Produsen**: `Modules\Finance\Application\Services\PaymentService`
- **Konsumen Terdaftar**:
  - `finance_ar_settlement`: Mengurangi saldo piutang dan memperbarui status invoice.
  - `treasury_cash_flow`: Memperbarui proyeksi arus kas harian.
- **Aggregate**: `Payment` (`aggregate_id` = payment_id)
- **SLA Delivery**: $\le 3$ detik (P95)
- **JSON Schema v1**:
```json
{
  "type": "object",
  "required": ["payment_reference", "invoice_id", "amount_paid", "payment_method", "cleared_at"],
  "properties": {
    "payment_reference": { "type": "string" },
    "invoice_id": { "type": "integer", "minimum": 1 },
    "amount_paid": { "type": "number", "minimum": 0.01 },
    "payment_method": { "type": "string", "enum": ["BANK_TRANSFER", "VIRTUAL_ACCOUNT", "CREDIT_CARD", "QRIS", "CASH"] },
    "cleared_at": { "type": "string", "format": "date-time" },
    "bank_account_code": { "type": "string" },
    "gateway_reference": { "type": "string" }
  }
}
```

---

### 2.2 Domain Logistik & Distribusi (Logistics & Distribution)

#### `logistics.shipment.dispatched`
- **Deskripsi**: Diterbitkan saat muatan armada/ekspedisi meninggalkan gudang pengiriman.
- **Produsen**: `Modules\Logistics\Application\Services\DispatchService`
- **Konsumen Terdaftar**:
  - `order_tracking_updater`: Mengubah status pesanan menjadi `IN_TRANSIT`.
  - `fleet_telematics_initiator`: Memulai pemantauan GPS geofencing armada.
  - `customer_sms_notifier`: Mengirim link pelacakan kurir ke nomor penerima.
- **Aggregate**: `Shipment` (`aggregate_id` = shipment_id)
- **SLA Delivery**: $\le 2$ detik (P95)
- **JSON Schema v1**:
```json
{
  "type": "object",
  "required": ["shipment_tracking_number", "origin_warehouse_id", "destination_address", "carrier_code", "vehicle_plate_number", "departure_time"],
  "properties": {
    "shipment_tracking_number": { "type": "string" },
    "origin_warehouse_id": { "type": "integer" },
    "destination_address": { "type": "string" },
    "carrier_code": { "type": "string" },
    "vehicle_plate_number": { "type": "string" },
    "driver_name": { "type": "string" },
    "estimated_arrival": { "type": "string", "format": "date-time" },
    "departure_time": { "type": "string", "format": "date-time" },
    "package_count": { "type": "integer", "minimum": 1 },
    "total_weight_kg": { "type": "number", "minimum": 0.01 }
  }
}
```

#### `logistics.shipment.delivered`
- **Deskripsi**: Diterbitkan saat kiriman tiba di tujuan dan bukti pengiriman (POD) ditandatangani.
- **Produsen**: `Modules\Logistics\Application\Services\DeliveryService`
- **Konsumen Terdaftar**:
  - `order_fulfillment_finalizer`: Menyelesaikan lifecycle pesanan menjadi `COMPLETED`.
  - `billing_trigger`: Menandai pesanan siap untuk ditagihkan/diakui pendapatannya.
  - `demurrage_calculator`: Menghentikan argo denda keterlambatan kontainer/armada.
- **Aggregate**: `Shipment` (`aggregate_id` = shipment_id)
- **SLA Delivery**: $\le 5$ detik (P95)
- **JSON Schema v1**:
```json
{
  "type": "object",
  "required": ["shipment_tracking_number", "recipient_name", "pod_signature_url", "delivered_at"],
  "properties": {
    "shipment_tracking_number": { "type": "string" },
    "recipient_name": { "type": "string" },
    "recipient_relationship": { "type": "string" },
    "pod_signature_url": { "type": "string" },
    "latitude": { "type": "number" },
    "longitude": { "type": "number" },
    "delivered_at": { "type": "string", "format": "date-time" },
    "notes": { "type": "string" }
  }
}
```

---

### 2.3 Domain Pengadaan & Inventori (Procurement & Inventory)

#### `procurement.purchase_order.approved`
- **Deskripsi**: Diterbitkan saat Purchase Order (PO) disetujui manajer pengadaan atau ApprovalEngine.
- **Produsen**: `Modules\Procurement\Application\Services\PurchaseOrderService`
- **Konsumen Terdaftar**:
  - `supplier_edi_transmitter`: Mengirimkan PO terstruktur ke portal vendor via EDI/API.
  - `finance_encumbrance_reserve`: Mencadangkan anggaran komitmen belanja.
  - `warehouse_inbound_planner`: Menjadwalkan slot dermaga bongkar muat gudang.
- **Aggregate**: `PurchaseOrder` (`aggregate_id` = po_id)
- **SLA Delivery**: $\le 5$ detik (P95)
- **JSON Schema v1**:
```json
{
  "type": "object",
  "required": ["po_number", "vendor_id", "approved_by_user_id", "total_commitment", "expected_delivery_date"],
  "properties": {
    "po_number": { "type": "string" },
    "vendor_id": { "type": "integer" },
    "approved_by_user_id": { "type": "integer" },
    "total_commitment": { "type": "number", "minimum": 0 },
    "currency": { "type": "string" },
    "expected_delivery_date": { "type": "string", "format": "date" },
    "items": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["sku", "quantity", "unit_price"],
        "properties": {
          "sku": { "type": "string" },
          "quantity": { "type": "number", "minimum": 0.01 },
          "unit_price": { "type": "number", "minimum": 0 }
        }
      }
    }
  }
}
```

#### `inventory.stock.adjusted`
- **Deskripsi**: Diterbitkan saat terjadi penyesuaian stok fisik (opname, scrap, transfer rak, shrinkage).
- **Produsen**: `Modules\Inventory\Application\Services\StockAdjustmentService`
- **Konsumen Terdaftar**:
  - `inventory_valuation_recalculator`: Menghitung ulang COGS / HPP metode Moving Average/FIFO.
  - `stock_alert_monitor`: Mengirim peringatan jika stok berada di bawah safety stock.
- **Aggregate**: `StockBatch` (`aggregate_id` = batch_id)
- **SLA Delivery**: $\le 2$ detik (P95)
- **JSON Schema v1**:
```json
{
  "type": "object",
  "required": ["warehouse_id", "sku", "quantity_delta", "reason_code", "adjusted_at"],
  "properties": {
    "warehouse_id": { "type": "integer" },
    "sku": { "type": "string" },
    "batch_number": { "type": "string" },
    "quantity_delta": { "type": "number" },
    "reason_code": { "type": "string", "enum": ["STOCK_OPNAME_VARIANCE", "DAMAGED_SCRAP", "EXPIRED_WRITE_OFF", "RETURN_INSPECTION"] },
    "operator_id": { "type": "integer" },
    "adjusted_at": { "type": "string", "format": "date-time" }
  }
}
```

---

### 2.4 Domain Rumah Sakit & Kesehatan (Hospital & Healthcare)

#### `hospital.patient.admitted`
- **Deskripsi**: Diterbitkan saat pasien selesai diregistrasi dan diterima di Instalasi Gawat Darurat (IGD) atau Rawat Inap.
- **Produsen**: `Modules\Hospital\Application\Services\AdmissionService`
- **Konsumen Terdaftar**:
  - `bed_management_allocator`: Mengunci ketersediaan ranjang ruang rawat.
  - `nursing_workstation_notifier`: Menyiapkan form triage dan riwayat rekam medis.
  - `pharmacy_inpatient_profiler`: Menginisialisasi formularium obat pasien rawat inap.
- **Aggregate**: `PatientAdmission` (`aggregate_id` = admission_id)
- **SLA Delivery**: $\le 1$ detik (CRITICAL SLA, P95)
- **JSON Schema v1**:
```json
{
  "type": "object",
  "required": ["admission_number", "patient_mrn", "ward_id", "bed_id", "admission_type", "admitted_at"],
  "properties": {
    "admission_number": { "type": "string" },
    "patient_mrn": { "type": "string" },
    "ward_id": { "type": "integer" },
    "bed_id": { "type": "string" },
    "admission_type": { "type": "string", "enum": ["EMERGENCY", "ELECTIVE", "MATERNITY", "ICU"] },
    "attending_physician_id": { "type": "integer" },
    "initial_triage_acuity": { "type": "string", "enum": ["RESUSCITATION", "EMERGENT", "URGENT", "LESS_URGENT", "NON_URGENT"] },
    "admitted_at": { "type": "string", "format": "date-time" }
  }
}
```

#### `hospital.prescription.dispensed`
- **Deskripsi**: Diterbitkan saat resep obat selesai diracik, diperiksa verifikasi double-check 7 benar, dan diserahkan.
- **Produsen**: `Modules\Hospital\Application\Services\PharmacyDispenseService`
- **Konsumen Terdaftar**:
  - `patient_billing_adder`: Memasukkan biaya farmasi ke tagihan rawat pasien.
  - `hospital_inventory_deductor`: Mengurangi stok fisik obat di apotek satelit.
- **Aggregate**: `Prescription` (`aggregate_id` = prescription_id)
- **SLA Delivery**: $\le 3$ detik (P95)
- **JSON Schema v1**:
```json
{
  "type": "object",
  "required": ["prescription_id", "admission_number", "pharmacist_id", "dispensed_at", "medications"],
  "properties": {
    "prescription_id": { "type": "string" },
    "admission_number": { "type": "string" },
    "pharmacist_id": { "type": "integer" },
    "dispensed_at": { "type": "string", "format": "date-time" },
    "medications": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["drug_code", "quantity_dispensed", "dosage_instructions"],
        "properties": {
          "drug_code": { "type": "string" },
          "quantity_dispensed": { "type": "number", "minimum": 1 },
          "dosage_instructions": { "type": "string" }
        }
      }
    }
  }
}
```

---

### 2.5 Domain Telematika & IoT Kendaraan (Telematics & IoT)

#### `telematics.telemetry.recorded`
- **Deskripsi**: Diterbitkan dari batch data pings sensor OBD-II/GPS perangkat IoT kendaraan.
- **Produsen**: `Modules\Telematics\Application\Services\TelemetryIngestService`
- **Konsumen Terdaftar**:
  - `geofence_violation_detector`: Memeriksa apakah armada keluar koridor rute yang diizinkan.
  - `fuel_theft_analyzer`: Mendeteksi penurunan drastis volume tangki bahan bakar secara anomali.
  - `vehicle_maintenance_odometer`: Mengakumulasi odometer untuk penjadwalan servis berkala.
- **Aggregate**: `Vehicle` (`aggregate_id` = vehicle_id)
- **SLA Delivery**: $\le 1$ detik (P95)
- **JSON Schema v1**:
```json
{
  "type": "object",
  "required": ["vehicle_id", "device_imei", "latitude", "longitude", "speed_kmh", "engine_rpm", "fuel_level_percent", "timestamp"],
  "properties": {
    "vehicle_id": { "type": "string" },
    "device_imei": { "type": "string" },
    "latitude": { "type": "number", "minimum": -90.0, "maximum": 90.0 },
    "longitude": { "type": "number", "minimum": -180.0, "maximum": 180.0 },
    "speed_kmh": { "type": "number", "minimum": 0 },
    "engine_rpm": { "type": "number", "minimum": 0 },
    "fuel_level_percent": { "type": "number", "minimum": 0, "maximum": 100 },
    "coolant_temp_celsius": { "type": "number" },
    "engine_running": { "type": "boolean" },
    "timestamp": { "type": "string", "format": "date-time" }
  }
}
```

---

### 2.6 Domain Banquet & Acara MICE (Banquet & Events)

#### `banquet.event_order.confirmed`
- **Deskripsi**: Diterbitkan saat Banquet Event Order (BEO) hotel/convention center dikonfirmasi oleh klien bersama uang muka.
- **Produsen**: `Modules\Banquet\Application\Services\EventOrderService`
- **Konsumen Terdaftar**:
  - `kitchen_fnb_procurement_planner`: Menghitung kebutuhan bahan baku makanan berdasarkan jumlah pax.
  - `ballroom_facility_scheduler`: Mengunci alokasi ballroom, proyektor, pencahayaan, dan sound system.
  - `housekeeping_roster_builder`: Mengatur jadwal shift staf banquet tambahan.
- **Aggregate**: `EventOrder` (`aggregate_id` = beo_id)
- **SLA Delivery**: $\le 5$ detik (P95)
- **JSON Schema v1**:
```json
{
  "type": "object",
  "required": ["beo_code", "client_name", "hall_id", "event_date", "session_type", "pax_count", "menu_package_code"],
  "properties": {
    "beo_code": { "type": "string" },
    "client_name": { "type": "string" },
    "hall_id": { "type": "string" },
    "event_date": { "type": "string", "format": "date" },
    "session_type": { "type": "string", "enum": ["MORNING_WEDDING", "EVENING_GALA", "FULLDAY_CONFERENCE", "AFTERNOON_HIGH_TEA"] },
    "pax_count": { "type": "integer", "minimum": 10 },
    "menu_package_code": { "type": "string" },
    "total_contract_value": { "type": "number", "minimum": 0 },
    "special_dietary_notes": { "type": "string" }
  }
}
```

---

## 3. Matriks Hubungan Produsen, Konsumen, dan Kebijakan Retry

| Topik Event | Produsen Utama | Konsumen Primer | Kebijakan Retry | Jeda Maksimal | Aksi Eskalasi DLQ |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `finance.invoice.issued` | Finance | GL, Notifikasi, Pajak | Eksponensial A5 ($r=5$) | 60 detik | TaskInbox ke `finance_supervisor` |
| `finance.payment.received` | Finance | AR Settlement, Treasury | Eksponensial A5 ($r=5$) | 60 detik | TaskInbox ke `treasury_analyst` |
| `logistics.shipment.dispatched`| Logistics | Tracking, Geofence, Notif | Eksponensial A5 ($r=3$) | 30 detik | Logging peringatan operasional |
| `logistics.shipment.delivered` | Logistics | Fulfillment, Billing | Eksponensial A5 ($r=5$) | 60 detik | TaskInbox ke `logistics_manager` |
| `procurement.purchase_order.approved` | Procurement | EDI, Reserve, Inbound | Eksponensial A5 ($r=5$) | 60 detik | TaskInbox ke `purchasing_officer` |
| `inventory.stock.adjusted` | Inventory | Valuation, Alert | Eksponensial A5 ($r=4$) | 45 detik | Notifikasi audit selisih stok |
| `hospital.patient.admitted` | Hospital | Bed, Nurse, Pharmacy | Eksponensial Cepat ($r=3$) | 10 detik | TaskInbox Kritis ke Kepala IGD |
| `hospital.prescription.dispensed`| Hospital | Tagihan, Apotek Satelit | Eksponensial A5 ($r=4$) | 30 detik | Peringatan farmasi rawat inap |
| `telematics.telemetry.recorded` | Telematics | Geofence, Fuel, Maintenance | Linier Cepat ($r=2$) | 5 detik | Discard setelah 2 kali (loss tolerant) |
| `banquet.event_order.confirmed`| Banquet | Kitchen, Facility, Roster | Eksponensial A5 ($r=5$) | 60 detik | TaskInbox ke `banquet_manager` |

---

## 4. Prosedur Pendaftaran & Verifikasi Skema Baru

Setiap penambahan event atau perubahan properti wajib melalui tahapan verifikasi kompatibilitas aditif (A6):
1. **Aturan Backward Compatibility**: Dilarang menghapus field existing, dilarang menambahkan required field baru pada versi skema yang sama, dilarang mengubah tipe data kolom menjadi tipe yang lebih sempit.
2. **Pendaftaran Skema**: Dilakukan melalui perintah konsol `php artisan spine:schema-register --topic=<topic> --version=<v>` atau `EventSchemaRegistry::register()`.
3. **Penyimpanan Skema**: Skema kanonikal disimpan di tabel `core_event_schemas` dengan SHA-256 hash untuk memastikan integritas tamper-evident.

