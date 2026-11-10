# Peta Arsitektur Event Spine & Topik Lintas Modul

Peta hubungan produsen, topik, skema event, dan grup konsumen aktif pada Universal Event Spine SuperWeb.

```mermaid
flowchart LR
    subgraph Producers["Produsen Event (Producers)"]
        P_Store["Store"]
        P_Logistics["Logistics"]
        P_Auto["AutoServe / Ev"]
        P_Hospital["Hospital"]
        P_Hotel["Hotel"]
        P_Core["Core (System)"]
    end

    subgraph Topics["Topik Event Spine (sim_event_spine)"]
        T_Store["store.order.paid.v1"]
        T_LgxDelivered["lgx.shipment.delivered.v1"]
        T_LgxTemp["lgx.temperature.breached.v1"]
        T_AutoEv["auto.ev.session_completed.v1"]
        T_HospCodeBlue["hospital.code_blue.activated.v1"]
        T_HotelBeo["hotel.banquet.beo_finalized.v1"]
        T_SpineDlq["core.spine.dead_lettered.v1"]
        T_SpineGap["core.spine.gap_detected.v1"]
    end

    subgraph Consumers["Grup Konsumen (Consumer Groups)"]
        C_Logistics["logistics_fulfillment"]
        C_Manufacturing["manufacturing_cogs"]
        C_Accounting["accounting_revenue"]
        C_Insurance["insurance_claims"]
        C_Esg["esg_carbon_tracking"]
        C_HospitalOps["hospital_emergency_ops"]
        C_HotelKitchen["hotel_banquet_kitchen"]
        C_SpineMonitor["spine_alert_monitor"]
    end

    P_Store --> T_Store
    T_Store --> C_Logistics
    T_Store --> C_Manufacturing

    P_Logistics --> T_LgxDelivered
    P_Logistics --> T_LgxTemp
    T_LgxDelivered --> C_Accounting
    T_LgxDelivered --> C_Insurance
    T_LgxTemp --> C_Insurance

    P_Auto --> T_AutoEv
    T_AutoEv --> C_Esg

    P_Hospital --> T_HospCodeBlue
    T_HospCodeBlue --> C_HospitalOps

    P_Hotel --> T_HotelBeo
    T_HotelBeo --> C_HotelKitchen

    P_Core --> T_SpineDlq
    P_Core --> T_SpineGap
    T_SpineDlq --> C_SpineMonitor
    T_SpineGap --> C_SpineMonitor
```

---

## Matriks Peta Topik, Skema & Konsumen

| Nama Event | Versi | Modul Produsen | Modul & Grup Konsumen | Tujuan Bisnis | Status Kompatibilitas |
| :--- | :---: | :--- | :--- | :--- | :---: |
| `store.order.paid.v1` | 1 | Store | Logistics, Manufacturing, Pricing | Pemenuhan order, hitung HPP/COGS, kunci harga | Aktif (A6 Valid) |
| `lgx.shipment.delivered.v1` | 1 | Logistics | Logistics, Core, Insurance | Pengakuan pendapatan kargo, penutupan polis transit | Aktif (A6 Valid) |
| `lgx.temperature.breached.v1` | 1 | Logistics | Payment, Insurance | Penahanan pembayaran armada, klaim asuransi otomatis | Aktif (A6 Valid) |
| `auto.telematics.anomaly_detected.v1` | 1 | Telematics | AutoServe, Logistics | Booking draft service armada preventif | Aktif (A6 Valid) |
| `auto.ev.session_completed.v1` | 1 | Ev | Core, Esg | Update SoH baterai kendaraan, hitung emisi Scope 2 | Aktif (A6 Valid) |
| `mall.footfall.recorded.v1` | 1 | Mall | Resto, CloudKitchen | Rekap beban dapur bersama & omset tenant | Aktif (A6 Valid) |
| `hospital.code_blue.activated.v1` | 1 | Hospital | Hospital, Nursing | Aktivasi tim tanggap darurat resusitasi | Aktif (A6 Valid) |
| `hotel.banquet.beo_finalized.v1` | 1 | Hotel | Hotel, F&B Kitchen | Sinkronisasi persiapan pesanan banquet & MICE | Aktif (A6 Valid) |
| `core.spine.dead_lettered.v1` | 1 | Core | SpineMonitor, TaskInbox | Notifikasi insiden DLQ & tugas operator | Aktif (A6 Valid) |
| `core.spine.gap_detected.v1` | 1 | Core | SpineMonitor, AuditOps | Deteksi celah nomor urut stream aggregate | Aktif (A6 Valid) |

