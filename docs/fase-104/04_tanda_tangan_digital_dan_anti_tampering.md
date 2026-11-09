# Tanda Tangan Digital & Integritas Kriptografis Anti-Tampering Resep Elektronik

Dokumen ini mendokumentasikan spesifikasi teknis kriptografis penerbitan dan verifikasi tanda tangan digital resep elektronik dokter pada modul Hospital Fase 104, mematuhi Permenkes No. 20 Tahun 2019 tentang Penyelenggaraan Pelayanan Telemedisin dan UU ITE No. 1 Tahun 2024.

---

## 1. Landasan Regulasi & Kebutuhan Keamanan

Dalam ekosistem telemedis dan farmasi digital, resep dokter yang dikirimkan melalui saluran telekomunikasi rentan terhadap risiko:
1. Pemalsuan identitas dokter penerbit (*physician impersonation*).
2. Modifikasi kuantitas atau jenis obat oleh pihak ketiga tanpa hak (*prescription tampering*), khususnya untuk memperoleh zat psikotropika/narkotika.
3. Penolakan pengakuan penerbitan oleh dokter bersangkutan (*repudiation*).
4. Penebusan ganda atas satu lembar resep elektronik (*replay attack*).

Untuk mengatasi ancaman tersebut, `DigitalPrescriptionSignatureVerifier` menyediakan mekanisme penandatanganan dan verifikasi deterministik berbasis cryptographic envelope.

---

## 2. Struktur Envelope Tanda Tangan Kriptografis

Setiap resep elektronik (`EPharmacyOrder` dan payload telekonsultasi) memiliki atribut tanda tangan digital yang dimodelkan melalui Value Object `DigitalSignatureEnvelope`:

```json
{
  "signer_id": "DOC-42",
  "sip_number": "SIP/503/123/DISKES/2026",
  "payload_hash": "a6b98e8f8c...",
  "signature_hash": "d4c82b9e11...",
  "signed_at": "2026-11-09T08:30:00Z",
  "algorithm": "HMAC-SHA256"
}
```

### Komponen Enkapsulasi:
- **`signer_id`**: Pengenal unik dokter dalam master data rumah sakit.
- **`sip_number`**: Nomor Surat Izin Praktik resmi dokter yang masih berlaku dan terverifikasi di konsil kedokteran / dinkes.
- **`payload_hash`**: Hash SHA-256 dari kanonikalisasi JSON isi resep (obat, dosis, durasi, identitas pasien).
- **`signature_hash`**: HMAC-SHA256 yang mengikat `signer_id`, `sip_number`, `payload_hash`, dan timestamp UTC.
- **`signed_at`**: Waktu penandatanganan berstandar ISO 8601 UTC.

---

## 3. Alur Penandatanganan (Signing Pipeline)

Dokter yang menyelesaikan telekonsultasi memanggil `IssueElectronicPrescriptionAction`. Alur teknisnya:

```
[Isi Resep & Rencana Terapi]
            │
            ▼
[Kanonikalisasi Payload (Urutkan Kunci Alfabetis)]
            │
            ▼
[Hitung Payload SHA-256 Hash]
            │
            ▼
[Ambil Rahasia Kriptografis / Private Key Dokter]
            │
            ▼
[Generate HMAC-SHA256: H(Secret, Signer + SIP + Hash + Timestamp)]
            │
            ▼
[Bungkus ke DigitalSignatureEnvelope]
            │
            ▼
[Simpan ke hsp_epharmacy_orders.doctor_signature_hash]
```

---

## 4. Alur Verifikasi Anti-Tampering pada Farmasi

Saat apotek menerima order, `DigitalPrescriptionSignatureVerifier::verifySignature` dieksekusi secara otomatis sebelum obat dapat diracik:

1. **Pemeriksaan Integritas Payload**:
   Muatan resep saat ini dikanonikalisasi ulang dan dihitung hash SHA-256-nya. Jika nilainya berbeda bahkan satu bit pun dengan `payload_hash` pada envelope, proses langsung dibatalkan dengan galat `PrescriptionTamperingException`.

2. **Pemeriksaan Tanda Tangan**:
   Sistem merekonstruksi string tanda tangan menggunakan kunci rahasia yang sah. Jika tidak cocok, status order diubah menjadi `REJECTED_SIGNATURE_INVALID`.

3. **Toleransi Pergeseran Waktu (Clock Skew)**:
   Timestamp penandatanganan diverifikasi tidak boleh berada di masa depan dan tidak boleh berumur lebih dari 30 hari untuk resep obat reguler, atau 3 hari untuk sediaan antimikroba/narkotika.

---

## 5. Pertahanan Terhadap Replay Attack

Setiap resep elektronik memiliki kode unik serial (`RX-TLM-XXXXXX`) yang dicatat pada audit log terdistribusi. Setelah status resep berubah menjadi `DISPENSED`, token digital dikunci secara permanen (`is_consumed = true`). Usaha penebusan kedua di apotek cabang lain akan ditolak oleh sistem verifikasi transaksi FEFO.

