# Panduan Pengguna: Flight Dispatcher (Fase 177)

Peran **Flight Dispatcher (`flight_dispatcher`)** memegang otoritas keselamatan operasional penerbangan bersama kapten pilot dalam merencanakan rute, menganalisis faktor cuaca, menghitung titik berat muatan (W&B), dan melepaskan penerbangan secara resmi.

---

## 1. Alur Kerja Pelepasan Penerbangan (Flight Dispatch Flow)

Proses pelepasan penerbangan wajib mengikuti urutan gerbang kelaikan:

```
[ Planned Flight ]
       │
       ▼
1. Verifikasi Kelaikan Armada (A2 Gate)
   ├── Status registrasi aktif (bukan grounded)
   ├── Sertifikat kelaikan CoA masih berlaku
   ├── Jam terbang sisa A-Check memadai
   └── Item MEL tertunda dalam batas wajar
       │
       ▼
2. Perhitungan Titik Berat & Muatan (A3 Load & Balance)
   ├── Input berat kosong operasional (OEW)
   ├── Input jumlah penumpang & kargo aktual
   ├── Input bahan bakar takeoff & trip burn
   └── Verifikasi titik berat (% MAC) dalam amplop aman
       │
       ▼
[ Airworthy Cleared ]
```

---

## 2. Formulir Rencana Terbang & Dispatch Desk

Akses melalui rute `/aviation/dispatch`:
1. Pilih penerbangan yang berstatus `planned`.
2. Tinjau registrasi armada pesawat yang ditugaskan.
3. Masukkan parameter timbang:
   - **Berat Kargo (kg):** Diambil dari manifes kargo yang telah diterima gudang.
   - **Bahan Bakar Takeoff (kg):** Memperhitungkan bahan bakar rute, cadangan 45 menit, dan bahan bakar ke bandara alternatif.
4. Tekan tombol **Hitung W&B & Lepaskan Terbang**.
5. Sistem akan:
   - Menghasilkan rekam jejak resmi `AviationWeightBalanceRecord`.
   - Mengubah status penerbangan menjadi `airworthy_cleared`.
   - Menerbitkan manifes resmi penerbangan (`FlightManifestCsvExporter`) untuk ditandatangani oleh Pilot in Command (PIC).

