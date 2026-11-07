# Spesifikasi Kontrak Aditif Lintas Modul (G7-K20, G7-K21, G7-K22)

## 1. Pendahuluan
Sesuai dengan protokol arsitektur Superweb, integrasi antar-modul domain dilarang menggunakan ketergantungan langsung (*direct tight coupling*) terhadap model basis data internal modul lain (Aturan A1 & A9). Seluruh komunikasi lintas batas domain wajib dieksekusi melalui **Kontrak Aditif Terdaftar** (*additive interface contracts*).

Pada Fase 116, modul Venue memperkenalkan dan mengonsumsi tiga kontrak aditif penting:
1. **G7-K20:** `HotelPackageAvailabilityValidator` di modul Hotel,
2. **G7-K21:** `EventShuttleFleetScheduler` di modul Logistics,
3. **G7-K22:** `IdentityVelocityRiskScorer` di modul Core.

---

## 2. Kontrak G7-K20: Validasi Ketersediaan Paket Hotel
### 2.1. Definisi Interface
- **Namespace:** `Modules\Hotel\Contracts\HotelPackageAvailabilityValidator`
- **Implementasi:** `Modules\Hotel\Application\Services\HotelPackageAvailabilityService`
- **Penyedia Layanan:** Terdaftar pada `HotelServiceProvider::register()`

### 2.2. Tanda Tangan Metode
```php
public function validateAvailability(
    int $hotelPackageId,
    \DateTimeImmutable $checkInDate,
    \DateTimeImmutable $checkOutDate,
    int $requiredRoomsCount
): bool;
```

### 2.3. Perilaku Bisnis
Metode ini memeriksa alokasi kuota kamar penginapan mitra di sekitar lokasi acara. Jika jumlah kamar yang tersedia mencukupi untuk rentang tanggal yang diminta, kontrak mengembalikan `true`. Jika kamar penuh, metode mengembalikan `false`, memicu pembatalan alokasi paket dynamic bundling sebelum transaksi finansial ditarik.

---

## 3. Kontrak G7-K21: Penjadwalan Armada Shuttle Acara
### 3.1. Definisi Interface
- **Namespace:** `Modules\Logistics\Contracts\EventShuttleFleetScheduler`
- **Implementasi:** `Modules\Logistics\Application\Services\EventShuttleFleetService`
- **Penyedia Layanan:** Terdaftar pada `LogisticsServiceProvider::register()`

### 3.2. Tanda Tangan Metode
```php
public function scheduleEventShuttle(
    int $eventId,
    int $shuttleRouteId,
    int $passengerCount,
    \DateTimeImmutable $departureTime
): bool;
```

### 3.3. Perilaku Bisnis
Kontrak ini mengunci kursi pada jadwal keberangkatan armada bus pengumpan (*event shuttle*) dari titik kumpul transit menuju gedung pertunjukan. Kontrak menjamin tidak terjadi *overbooking* kapasitas kendaraan penumpang.

---

## 4. Kontrak G7-K22: Skor Risiko Kecepatan Identitas Pengguna
### 4.1. Definisi Interface
- **Namespace:** `Modules\Core\Contracts\IdentityVelocityRiskScorer`
- **Implementasi:** `Modules\Core\Application\Services\IdentityVelocityRiskService`
- **Penyedia Layanan:** Terdaftar pada `CoreServiceProvider::register()`

### 4.2. Tanda Tangan Metode
```php
public function scoreVelocity(
    int $userId,
    string $ipAddress,
    ?string $deviceFingerprint
): int;
```

### 4.3. Perilaku Bisnis
Kontrak ini menganalisis jejak akses global pengguna di seluruh modul sistem Superweb. Skor risiko komposit dikembalikan dalam rentang $0$ (sangat aman) hingga $100$ (indikasi bot berbahaya). Modul Venue memanfaatkan kontrak ini untuk memblokir checkout pasar sekunder jika skor risiko $\ge 80$.

