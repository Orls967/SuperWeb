# Arsitektur Keamanan, Kriptografi & Audit Trail: Fase 114

## 1. Prinsip Integritas Transaksi & Sertifikat Matematis
Pada Fase 114, seluruh keputusan komersial bernilai tinggi (pelepasan kuota kamar blok, pemotongan stok resep BEO, persetujuan/penolakan margin guardrail, dan penetapan termin pembayaran) dilindungi oleh sertifikat digital deterministik berbasis hashing kriptografi SHA-256:

### 1.1 Struktur Signature Sertifikat
Setiap objek sertifikat menghasilkan hash integritas unik menggunakan rumus deterministik:
$$\text{Signature} = \text{hash}(\text{'sha256'}, K_{\text{secret}} \mathbin{\Vert} \text{PayloadCanonicalJson})$$

Di mana:
- $K_{\text{secret}}$ adalah kunci rahasia internal aplikasi (`app.key`).
- $\text{PayloadCanonicalJson}$ adalah representasi JSON kanonikal terurut (*sorted keys, unescaped slashes, unescaped unicode*) dari parameter masukan dan hasil keluaran algoritma.

```
       ┌────────────────────────┐
       │   Algoritma Domain     │
       │  (H14, H15, H16, H17)  │
       └───────────┬────────────┘
                   │
                   ▼
       ┌────────────────────────┐
       │ Canonical Serialization│
       └───────────┬────────────┘
                   │
                   ▼
       ┌────────────────────────┐
       │ SHA-256 HMAC Signature │
       └───────────┬────────────┘
                   │
                   ▼
       ┌────────────────────────┐
       │  Database Audit Record │
       │  (htl_audit_records)   │
       └────────────────────────┘
```

---

## 2. Pengendalian Akses Berbasis Peran (RBAC Matrix)
Pemisahan tugas (*Separation of Duties / SoD*) ditegakkan secara ketat pada modul MICE dan Banquet untuk mencegah penipuan komersial (*kickback / fraudulent discounting*):

| Hak Akses / Operasi | Sales Manager | Banquet Chef | Front Desk | Revenue Mgr | General Mgr | System Cron |
|---|---|---|---|---|---|---|
| Buat Draf Proposal MICE | [x] | [ ] | [ ] | [ ] | [ ] | [ ] |
| Override Margin Guardrail < 40% | [ ] | [ ] | [ ] | [ ] | [x] | [ ] |
| Konfirmasi Pemesanan Kamar Blok | [x] | [ ] | [ ] | [x] | [x] | [ ] |
| Pelepasan Allotment Otomatis (H-30/14/7) | [ ] | [ ] | [ ] | [ ] | [ ] | [x] |
| Input Rooming List & Group Check-in | [ ] | [ ] | [x] | [ ] | [ ] | [ ] |
| Eksekusi Pemotongan Stok Bahan Dapur | [ ] | [x] | [ ] | [ ] | [ ] | [ ] |
| Penerbitan Credit Memo Sengketa | [ ] | [ ] | [ ] | [x] | [x] | [ ] |
| Penutupan Master Folio & Posting AR | [ ] | [ ] | [x] | [ ] | [x] | [ ] |

---

## 3. Ketahanan Audit Trail (Tamper-Evident Logging)
1. Seluruh rekaman perubahan status pada `htl_event_proposals`, `htl_room_blocks`, dan `htl_banquet_orders` mencatat:
   - `created_at` dan `updated_at` dengan presisi mikrodetik.
   - `operator_user_reference`: ID pengguna penanggung jawab.
   - `ip_address` dan `user_agent`.
   - `previous_payload_snapshot` dan `new_payload_snapshot`.
   - `certificate_signature_hash`.
2. Jika ada pihak yang mencoba mengubah kuota kamar atau margin harga langsung di database secara manual (*database manipulation tampering*), verifier algoritma akan mendeteksi ketidaksesuaian hash (*checksum mismatch*) dan membunyikan alarm peringatan keamanan pada siklus audit sistem harian.
