# Spesifikasi API RESTful, Format Payload & Skema Request/Response E-Health Fase 104

Dokumen ini mendokumentasikan spesifikasi antarmuka pemrograman aplikasi (Application Programming Interface / API) berbasis RESTful JSON yang disediakan oleh modul Hospital Fase 104 untuk konsumsi oleh aplikasi frontend (Web/Mobile) dan subsistem integrasi pihak ketiga.

---

## 1. Standar Desain API & Format Serialisasi

1. **Format Serialisasi**: JSON (`application/json; charset=UTF-8`).
2. **Karakter Penamaan Kunci**: `snake_case` untuk atribut payload data.
3. **Standar Waktu**: ISO 8601 UTC (`YYYY-MM-DDTHH:mm:ssZ`).
4. **Respon Seragam**: Seluruh endpoint mengembalikan struktur respons standar:
   ```json
   {
     "success": true,
     "message": "Operasi berhasil dieksekusi",
     "data": { ... },
     "meta": {
       "request_id": "REQ-20261109-A8F2",
       "timestamp": "2026-11-09T08:45:00Z"
     }
   }
   ```

---

## 2. Endpoint Booking & Pelaksanaan Telekonsultasi

### 2.1 Pendaftaran Sesi Telekonsultasi
- **Metode**: `POST`
- **Rute**: `/api/hospital/telemedicine/book`
- **Otorisasi**: Pasien Terdaftar atau Dokter / Petugas Administrasi
- **Contoh Request Payload**:
  ```json
  {
    "patient_id": 1042,
    "doctor_id": 88,
    "channel_type": "VIDEO",
    "chief_complaint": "Sesak napas ringan setelah aktivitas fisik, riwayat asma bronkiale",
    "scheduled_at": "2026-11-10T14:00:00Z",
    "vital_signs": {
      "systolic_bp": 125,
      "diastolic_bp": 82,
      "heart_rate_bpm": 78,
      "oxygen_saturation_pct": 97.5,
      "body_temperature_c": 36.8
    }
  }
  ```
- **Contoh Response (HTTP 201 Created)**:
  ```json
  {
    "success": true,
    "message": "Sesi telekonsultasi berhasil dijadwalkan",
    "data": {
      "consult_code": "TC-8F2B1C04",
      "triage_category": "YELLOW",
      "recommended_action": "Konsultasi telemedis terpantau dengan penyiapan opsi rujukan IGD bila desaturasi < 95%",
      "room_token": "TOK-WEBRTC-9988-A2F1-X0",
      "scheduled_at": "2026-11-10T14:00:00Z"
    }
  }
  ```

---

## 3. Endpoint Penerbitan Resep Elektronik Dokter

### 3.1 Submit Draf & Penerbitan E-Resep
- **Metode**: `POST`
- **Rute**: `/api/hospital/telemedicine/prescriptions`
- **Otorisasi**: Dokter Spesialis (`doctor`) dengan SIP Aktif
- **Contoh Request Payload**:
  ```json
  {
    "tele_consult_id": 412,
    "patient_id": 1042,
    "diagnosis_icd10": "J45.909",
    "clinical_notes": "Asma terkontrol sebagian, berikan bronkodilator inhalasi dan kortikosteroid oral jangka pendek",
    "items": [
      {
        "drug_code": "SALBUTAMOL_INHALER",
        "drug_name": "Salbutamol Inhaler 100mcg/puff",
        "dosage_instructions": "2 semprotan saat sesak, maks 4 kali sehari",
        "quantity": 1,
        "is_cold_chain": false
      },
      {
        "drug_code": "METHYLPREDNISOLONE_4",
        "drug_name": "Methylprednisolone Tablet 4mg",
        "dosage_instructions": "3 x 1 tablet sesudah makan selama 5 hari",
        "quantity": 15,
        "is_cold_chain": false
      }
    ],
    "doctor_sip_number": "SIP/503/123/DISKES/2026",
    "signature_timestamp": "2026-11-09T08:50:00Z"
  }
  ```
- **Contoh Response (HTTP 200 OK)**:
  ```json
  {
    "success": true,
    "message": "Resep elektronik berhasil ditandatangani secara digital dan diteruskan ke apotek",
    "data": {
      "prescription_code": "RX-TLM-20261109-7712",
      "safety_score": {
        "score_points": 100,
        "safety_level": "SAFE",
        "is_dispensable": true,
        "requires_dual_doctor_approval": false,
        "warnings": []
      },
      "signature_envelope": {
        "signer_id": "DOC-88",
        "sip_number": "SIP/503/123/DISKES/2026",
        "payload_hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        "signature_hash": "b2f68903c734491702f354a86f99047249a5840d4d29388f8d6892e8fae83e9b",
        "signed_at": "2026-11-09T08:50:00Z"
      },
      "fulfilling_branch": {
        "branch_code": "BR-JKT-01",
        "branch_name": "Apotek Sentral Rumah Sakit",
        "distance_km": 2.45
      }
    }
  }
  ```

---

## 4. Endpoint Pengiriman Logistik & e-POD Kurir

### 4.1 Kirim Telemetri Suhu Realtime Boks Kurir
- **Metode**: `POST`
- **Rute**: `/api/hospital/epharmacy/courier/telemetry`
- **Otorisasi**: Kurir IoT Armada (`courier`)
- **Contoh Request Payload**:
  ```json
  {
    "delivery_code": "DEL-A1B2C3D4",
    "current_temperature_c": 4.6,
    "gps_latitude": -6.2104,
    "gps_longitude": 106.8421,
    "battery_pct": 92
  }
  ```
- **Contoh Response**:
  ```json
  {
    "success": true,
    "data": {
      "delivery_status": "IN_TRANSIT",
      "is_temperature_breached": false,
      "last_recorded_temp_c": 4.6
    }
  }
  ```

### 4.2 Penyelesaian Bukti Penyerahan Obat (e-POD)
- **Metode**: `POST`
- **Rute**: `/api/hospital/epharmacy/courier/complete-pod`
- **Otorisasi**: Kurir IoT Armada (`courier`)
- **Contoh Request Payload**:
  ```json
  {
    "delivery_code": "DEL-A1B2C3D4",
    "recipient_nik": "3171012345678901",
    "recipient_name": "Ny. Siti Aminah",
    "raw_signature_token": "SIG-BIO-20261109-TOKEN-8812"
  }
  ```
- **Contoh Response**:
  ```json
  {
    "success": true,
    "message": "Serah terima obat berhasil dicatat secara sah",
    "data": {
      "delivery_code": "DEL-A1B2C3D4",
      "delivery_status": "DELIVERED",
      "pod_signature_hash": "7f83b1657ff1fc53b92dc18148a1d65dfc2d4b1fa3d677284addd200126d9069",
      "delivered_at": "2026-11-09T09:35:12Z"
    }
  }
  ```

