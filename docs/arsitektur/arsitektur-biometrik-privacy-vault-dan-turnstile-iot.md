# Arsitektur Biometrik Privacy Vault & Turnstile IoT — Fase 117

```mermaid
flowchart TD
    subgraph Tamu & Turnstile Kiosk
        A[Tamu Hadir di Gerbang Turnstile] --> B[Kamera Sensor 3D & Liveness Kiosk]
        B --> C[Uji Liveness Challenge: Kedipan/Senyum/Rotasi]
    end

    subgraph Memory Pipeline Nir-Simpan
        C -->|Lolos Liveness| D[Ekstraksi Vektor Fitur Wajah di Memori RAM]
        D --> E[Penggaraman Kriptografis Salt Per-Venue]
        E --> F[Fungsi Hash Satu Arah HMAC-SHA256]
        D -.->|Musnahkan Segera| DEL[Hapus Vektor Mentah dari RAM]
    end

    subgraph Privacy Vault Database
        F --> G[Bandingkan dengan ven_biometric_credentials]
        G -->|Cocok Persis| H[Buka Kunci Turnstile Gerbang]
        G -->|Mismatch| I[Tolak Akses & Rekam Log Peringatan]
    end

    subgraph Audit & Hak Privasi
        H --> J[Catat Scan Masuk & Tambah Headcount Zona]
        K[Hak Pencabutan / Revoke] -->|Tamu Mengajukan| L[Set Status REVOKED di Vault]
    end
```

## 1. Prinsip Privasi Berbasis Desain (Privacy by Design)
Sistem gerbang biometrik venue dibangun di atas kepatuhan regulasi UU Perlindungan Data Pribadi (UU PDP No. 27/2022) dan GDPR Pasal 9 (Kategori Khusus Data Biometrik):
1. **Zero Raw Biometric Storage:** Vektor landmark wajah atau citra foto wajah pengunjung **DILARANG KERAS** disimpan di media persisten (SSD, HDD, Database, atau Log S3).
2. **Ephemeral Memory Processing:** Komputasi ekstraksi fitur dan hashing dijalankan dalam enclave memori terisolasi yang di-zeroize segera setelah nilai hash templat dihitung.
3. **One-Way Salted Digest:** Basis data hanya menyimpan representasi hash HMAC-SHA256 64-karakter yang tidak memiliki jalur inversi matematis untuk merekonstruksi fitur wajah asli.
4. **Hak Pencabutan Otomatis (Right to be Forgotten):** Pengunjung berhak sewaktu-waktu mencabut persetujuan biometrik melalui portal profil pengguna, yang seketika mematikan kredensial biometrik (`status = REVOKED`) dan mewajibkan penggunaan tiket barcode standar.

