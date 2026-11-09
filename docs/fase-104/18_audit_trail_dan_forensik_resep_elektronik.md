# Jejak Audit Forensik & Kepatuhan Integritas Resep Elektronik (Audit Trail & Forensic Log)

Dokumen ini mendokumentasikan spesifikasi implementasi jejak audit digital (*digital audit trail*), log forensik transaksi peresepan, serta jaminan non-repudiasi (*non-repudiation*) pada modul Hospital Fase 104 sesuai dengan amanat UU ITE, Permenkes Rekam Medis No. 24 Tahun 2022, dan standar ISO 27799 (Health Informatics Information Security Management).

---

## 1. Landasan Hukum & Kebutuhan Forensik Medis

Dalam sengketa medikolegal (malpraktik kedokteran, medication error, klaim ganti rugi asuransi), rekam jejak elektronik peresepan dan penyerahan obat sering menjadi barang bukti kunci di pengadilan. Setiap intervensi pengguna terhadap sistem harus terekam secara nir-sangkal (*non-repudiable*) dan tahan manipulasi (*tamper-evident*).

### Parameter Wajib Audit Trail:
1. **Who**: Pengenal identitas unik pengguna (`user_id`, `doctor_id`, `pharmacist_id`, `patient_id`) beserta peran aktifnya saat transaksi.
2. **When**: Timestamp tersinkronisasi server NTP berstandar presisi mikrodetik UTC.
3. **What**: Jenis aksi (pembuatan draf, evaluasi keselamatan CDSE, penandatanganan digital, persetujuan kedua dokter, peracikan FEFO, serah terima kurir).
4. **Where**: Alamat IP sumber, user agent peramban/aplikasi bergerak, dan koordinat GPS (untuk kurir).
5. **Why**: Justifikasi klinis bila dokter memilih melewati (*override*) peringatan interaksi berderajat moderat.

---

## 2. Struktur Log Forensik Imutabel

Model `TelemedicineClinicalAuditLogger` mencatat setiap siklus ke dalam tabel terpartisi `hsp_telemedicine_audit_logs`:

```json
{
  "log_uuid": "f81d4fae-7dec-11d0-a765-00a0c91e6bf6",
  "event_type": "PRESCRIPTION_ISSUED",
  "tele_consult_id": 412,
  "prescription_code": "RX-TLM-20261109-7712",
  "actor_user_id": 88,
  "actor_role": "doctor",
  "actor_ip_address": "192.168.10.45",
  "event_payload_hash": "a6b98e8f8c7d6e5a4b3c2d1e0f9a8b7c6d5e4f3a2b1c0d9e8f7a6b5c4d3e2f1a",
  "previous_log_hash": "7c6d5e4f3a2b1c0d9e8f7a6b5c4d3e2f1aa6b98e8f8c7d6e5a4b3c2d1e0f9a8b",
  "current_block_hash": "3b2c1d0e9f8a7b6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f0a9b8c7d6e5f4a3b2c",
  "client_signature": "SIG-DOC-88-HMAC-256",
  "recorded_at": "2026-11-09T08:50:00.124560Z"
}
```

Setiap entri log mengikat hash dari entri sebelumnya (*cryptographic hash chaining* layaknya struktur blockchain privat). Upaya pengubahan langsung pada database oleh pihak manapun akan merusak rantai verifikasi saat `php artisan hosp:audit` dijalankan.

---

## 3. Penanganan Pelanggaran & Peringatan Otomatis

Bila ditemukan anomali audit:
- Percobaan peracikan obat tanpa validasi tanda tangan dokter.
- Percobaan pengiriman obat yang sedang dalam status karantina suhu.
- Modifikasi retroaktif status admisi rawat inap tanpa otorisasi kepala rekam medis.

Sistem secara otomatis mengaktifkan status *Suspicious Integrity Breach* pada dashboard administrator dan mengirimkan peringatan prioritas tinggi ke Pejabat Kepatuhan Rumah Sakit (Chief Compliance Officer).

