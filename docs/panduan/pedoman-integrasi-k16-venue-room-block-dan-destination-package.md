# Pedoman Integrasi G7-K16 RoomBlockReserver & Paket Destinasi Venue

## 1. Latar Belakang & Mandat Kontrak K16
Dalam arsitektur lintas modul Superweb Kereta G7, kontrak antarmuka **G7-K16 (`Modules\Hotel\Contracts\RoomBlockReserver`)** menjadi jembatan penghubung utama antara Modul Venue (G7-P5) dan Modul Perhotelan (G7-P6).

Kontrak ini memfasilitasi pemesanan dan penguncian blok kamar hotel dalam jumlah besar (*Group Room Allotment / Event Room Blocking*) yang dibundel langsung dengan tiket pertunjukan, festival konser musik, atau perjamuan di Venue.

```
+----------------------------------------------------------------------------------+
|                   ALUR INTEGRASI KONTRAK G7-K16 ANTAR MODUL                      |
+----------------------------------------------------------------------------------+
|  [Venue Event Organizer]   --> Memanggil G7-K16 RoomBlockReserver                |
|  [RoomBlockReserverService]--> Mengunci Kuota Kamar di htl_room_block_allotments |
|  [Inventory Night Lock]    --> Menandai Kamar 'RESERVED_FOR_EVENT' di CRS        |
|  [Konfirmasi Tiket + Stay] --> Merilis Kode Booking Terpadu ke Tamu Konser       |
+----------------------------------------------------------------------------------+
```

---

## 2. Definisi Kontrak & Tanda Tangan Antarmuka
Kontrak antarmuka `RoomBlockReserver` didefinisikan secara ketat pada namespace `Modules\Hotel\Contracts\RoomBlockReserver`:

```php
namespace Modules\Hotel\Contracts;

interface RoomBlockReserver
{
    /**
     * Memesan dan mengunci alokasi blok kamar untuk event konser / venue.
     *
     * @param string $eventId Identifier event venue pemesan.
     * @param string $propertyId Identifier properti resort hotel.
     * @param string $roomTypeCode Kode tipe kamar yang diblokir.
     * @param string $checkInDate Tanggal kedatangan tamu (YYYY-MM-DD).
     * @param string $checkOutDate Tanggal kepulangan tamu (YYYY-MM-DD).
     * @param int $roomCount Jumlah kamar yang dipesan dalam blok allotment.
     * @param int $ratePerNightIdr Tarif khusus grup per malam.
     * @return array{block_id: string, status: string, total_amount_idr: int, expires_at: string}
     */
    public function reserveBlock(
        string $eventId,
        string $propertyId,
        string $roomTypeCode,
        string $checkInDate,
        string $checkOutDate,
        int $roomCount,
        int $ratePerNightIdr
    ): array;

    /**
     * Melepaskan sisa blok kamar yang tidak terjual menjelang batas cut-off event.
     */
    public function releaseBlockAllotment(string $blockId): bool;
}
```

---

## 3. Kompatibilitas Menyeluruh dengan Konsumen Modul Venue
Sebagai pemilik domain Perhotelan, pengembang Modul Hotel **DILARANG KERAS** mengubah *method signature* atau menghapus implementasi `RoomBlockReserverService`.

Bila di kemudian hari sistem mengadopsi mesin pelepasan alokasi otomatis (*BlockAllotmentReleaser Engine / Fase 114*), pemanggilan antarmuka K16 tetap dialihkan ke mesin baru secara transparan (*backward-compatible wrapper*) tanpa memutus jalannya pengujian dan alur kerja di Modul Venue.

---

## 4. Mekanisme Cut-Off Date & Pelepasan Kamar Otomatis
1. **Periode Proteksi:** Blok kamar dijamin ketersediaannya hingga $D-14$ sebelum tanggal acara.
2. **Batas Cut-off:** Pada pukul 23:59:59 di hari ke-14 sebelum acara, seluruh kuota kamar yang belum dikaitkan dengan tiket konser tamu secara otomatis dilepaskan kembali ke *Central Reservation Inventory*.
3. **Pemberitahuan Otomatis:** Sistem mengirimkan notifikasi rekapitulasi okupansi blok ke Event Director Venue dan General Manager Hotel.
