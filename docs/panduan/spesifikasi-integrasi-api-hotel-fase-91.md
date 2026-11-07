# Spesifikasi Teknis Integrasi API & Adaptor Lintas Sistem (Fase 91)

Dokumen ini menguraikan arsitektur antarmuka perangkat lunak, protokol komunikasi data, kontrak integrasi lintas modul G7, dan payload data pertukaran.

---

## 1. Integrasi Modul Eksternal & Adaptor Simulasi

### 1.1 Modul Logistik (Logistics Shuttle Booking)
- **Tujuan**: Pemesanan armada mobil penjemputan tamu relokasi overbooking (walked guests) dan antar-jemput bandara.
- **Implementasi**: `LogisticsShuttleAdapter::bookShuttleForWalkGuest()`
- **Parameter Payload**:
  - `reservation_id`: ID unik reservasi tamu asal.
  - `origin_property`: Nama properti hotel asal.
  - `destination_property`: Nama properti hotel tujuan penerima relokasi.
- **Tingkat Simulasi**: S1 (Simulasi respons instan dengan nomor booking armada VIP).

### 1.2 Modul Rekayasa Aset (Asset Maintenance Work Order)
- **Tujuan**: Pembuatan tiket perbaikan kerusakan fasilitas fisik kamar oleh staf housekeeping.
- **Implementasi**: `AssetWorkOrderAdapter::createMaintenanceOrder()`
- **Parameter Payload**:
  - `room_id`: ID fisik kamar yang mengalami kerusakan.
  - `issue_type`: Kategori fasilitas (AC, plumbing, electrical, furniture).
  - `description`: Uraian rinci kerusakan yang dilaporkan.
- **SLA Respon**: Maksimal 2 jam untuk kerusakan berstatus kritikal.

### 1.3 Modul Lingkungan & Keberlanjutan (ESG Emission Benefit)
- **Tujuan**: Pencatatan metrik penghematan daya listrik AC kamar kosong terisi hasil algoritma H4 HVAC Setback.
- **Implementasi**: `EsgEnergyBenefitAdapter::recordKwhSaving()`
- **Parameter Payload**:
  - `room_id`: ID kamar hotel.
  - `kwh_saved`: Jumlah kilowatt-hour energi listrik yang berhasil dihemat.
- **Keluaran**: Pengurangan ekuivalen emisi karbon (kg CO2e) yang teragregasi pada pelaporan Environmental, Social, and Governance perusahaan.

### 1.4 Modul Kembar Digital (Core Digital Twin)
- **Tujuan**: Sinkronisasi status waktu nyata temperatur, kelembapan, dan okupansi kamar.
- **Implementasi**: `CoreDigitalTwinAdapter::syncRoomState()`
- **Parameter Payload**:
  - `room_id`: Nomor ID unit kamar.
  - `state_name`: Indikator status HVAC (misal: `HVAC_SETBACK_ECO`, `HVAC_COMFORT`).
  - `temperature_celsius`: Nilai pembacaan temperatur kamar.
