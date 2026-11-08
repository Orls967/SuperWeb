# Runbook Operasional: Resolusi Sengketa Tagihan & Rekonsiliasi Master Folio MICE

## 1. Konteks Layanan
Runbook ini memandu Tim Kredit Korporat, Front Office Cashier, dan Event Coordinator dalam menuntaskan sengketa pos tagihan (*charge disputes*) pada master folio konferensi atau pernikahan sebelum dipindahkan ke piutang korporat (*City Ledger Accounts Receivable*):
- Service Terkait: `Modules\Hotel\Application\Services\HotelMiceLedgerReconciliationService`
- Action Terkait: `Modules\Hotel\Application\Actions\AuditMiceEventReconciliationAction`

## 2. Struktur Master Folio Multi-Sub
Sebuah acara MICE memiliki struktur rekening tagihan bertingkat:
- **Master Folio A (Billing Utama Organizer):** Biaya sewa ruang ballroom, paket jamuan makan utama BEO, alokasi kamar panitia inti, dan paket AV standar.
- **Sub-Folio B (Exhibition & Utilities):** Biaya sewa stan booth expo, tagihan kelebihan daya listrik, dan tiket kebersihan kontraktor pameran.
- **Sub-Folio C (Individual Delegate Incidentals):** Tagihan laundry, minibar, spa, dan layanan kamar individual yang dibebankan kepada tamu pribadi saat checkout, BUKAN dibebankan ke panitia.

## 3. Prosedur Investigasi Sengketa Tagihan (Dispute Investigation Flow)
```
[Organizer Mengajukan Keberatan atas Item Tagihan]
                       │
                       ▼
         [Identifikasi Kategori Sengketa]
                       │
      ┌────────────────┼────────────────┐
      ▼                ▼                ▼
[Konsumsi Ekstra] [Kamar No-Show]  [Kerusakan Fasilitas]
      │                │                │
      ▼                ▼                ▼
[Cek BEO Rider &  [Cek Bukti SMS/ [Cek Formulir Berita
 Tanda Tangan     Email Konfirmasi Acara Serah Terima
 Banquet Captain]  Reservasi H-1]  Teknisi Eng & Foto]
      │                │                │
      └────────────────┼────────────────┘
                       │
                       ▼
       [Negosiasi Komersial DOSM & Klien]
                       │
      ┌────────────────┴────────────────┐
      ▼                                 ▼
[Keberatan Diterima]           [Tagihan Sah & Valid]
      │                                 │
      ▼                                 ▼
[Terbitkan Credit Memo         [Jelaskan Regulasi Kontrak
 & Jurnal Pembalik]             & Tunjukkan Tanda Tangan SPK]
      │                                 │
      └────────────────┬────────────────┘
                       │
                       ▼
        [Pelunasan Saldo Akhir Folio: Bank/AR]
                       │
                       ▼
         [Penerbitan Surat Bebas Tagihan]
```

## 4. Format Jurnal Akuntansi Penyesuaian Sengketa
Jika disetujui diskon/kredit atas sengketa AC ballroom yang kurang dingin:
- **Debit:** Akun Penyesuaian Pendapatan MICE / Diskon Komersial (Akun `4-4190`)
- **Kredit:** Piutang Master Folio MICE (Akun `1-1200`)
- Keterangan: *Pemberian goodwill adjustment 5% sewa ballroom sesuai kesepakatan GM & Panitia Konferensi.*

