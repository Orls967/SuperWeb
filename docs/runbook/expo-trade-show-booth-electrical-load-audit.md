# Runbook Operasional: Audit Beban Daya Listrik & Jaringan Stan Pameran Ekshibisi

## 1. Konteks Layanan
Runbook ini memandu Chief Engineer, Convention Hall Coordinator, dan IT Infrastructure Lead dalam memverifikasi beban listrik peralatan peserta expo, mencegah trip MCB trafo utama, dan menjamin kestabilan bandwidth internet:
- Service Terkait: `Modules\Hotel\Application\Services\HotelExhibitionTradeShowConnectorService`
- Action Terkait: `Modules\Hotel\Application\Actions\ReserveExhibitionTradeShowBoothAction`

## 2. Standar Alokasi Daya & Perlindungan Sirkuit
1. Setiap booth standar tipe Shell Scheme (3x3m) dibatasi daya 450 VA (maksimal arus 2 Ampere pada tegangan 220 Volt).
2. Setiap sub-panel lantai (*underfloor distribution box*) melayani maksimal 6 stan berdekatan.
3. Total beban pada satu sub-panel tidak boleh melebihi 80% dari rating pemutus daya (*circuit breaker threshold limit*):
   $$I_{\text{max\_safe}} = 0.80 \times I_{\text{breaker\_rating}}$$

## 3. Alur Inspeksi Teknis Hari Pertama Pasang (Load-in Day)
```
[Kontraktor Stan Menyelesaikan Perakitan Rangka]
                       │
                       ▼
[Pemeriksaan Daftar Peralatan Listrik (Equipment Load List)]
                       │
     ┌─────────────────┴─────────────────┐
     ▼                                   ▼
[Total Beban <= Kuota]            [Total Beban Melebihi Kuota]
     │                                   │
     ▼                                   ▼
[Ukur Hambatan Isolasi (Megger)]  [Hentikan Penyambungan Kabel]
     │                                   │
     ▼                                   ▼
[Segel MCB Diberi Tag Kuning]     [Exhibitor Wajib Membayar Add-on Daya]
     │                                   │
     ▼                                   ▼
[Daya Listrik Dinyalakan]         [Upgrade Kapasitas MCB Sub-Panel]
     │                                   │
     └─────────────────┬─────────────────┘
                       │
                       ▼
        [Verifikasi Sinyal Wi-Fi / Koneksi LAN]
                       │
                       ▼
          [Tanda Tangan Berita Acara Layak Pakai]
```

## 4. Matriks Tarif Denda Pelanggaran Daya
| Jenis Pelanggaran | Risiko Operasional | Tindakan & Sanksi Denda |
|---|---|---|
| Memasang sambungan colokan tanpa izin (*unauthorized multi-tap jumper*) | Kabel panas berlebih, risiko percikan api (*fire hazard*) | Pemutusan seketika daya stan dan denda IDR 1.500.000 per titik colokan ilegal. |
| Membawa mesin pendingin/pemanas di atas 2.000W pada paket 450 VA | Trip pemutus arus lokal, memadamkan stan tetangga | Pemutusan arus hingga peserta membeli paket daya 3 kVA resmi. |
| Memancarkan router Wi-Fi ilegal yang mengganggu frekuensi sistem POS hotel | Gangguan mesin kasir banquet & tiket seminar | Penyitaan sementara perangkat router hingga pameran selesai. |

