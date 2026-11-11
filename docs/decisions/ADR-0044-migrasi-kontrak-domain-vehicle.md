# ADR-0044: Migrasi Kontrak Domain Cross-Module Vehicle (W8-D44)

## Status
Diterima (Accepted)

## Konteks
Aturan arsitektur A1 (*CrossModuleDomainImportRule*) melarang modul bisnis mengimpor model internal `Domain` milik modul lain (`Modules\{Lain}\Domain\*`). Pada baseline audit awal Gelombang 8, terdapat 14 pelanggaran A1 yang seluruhnya disebabkan oleh import langsung ke kelas `Modules\Core\Domain\Models\Vehicle` pada empat modul bisnis:
1. `AutoServe` (4 berkas: `CreatePredictiveMaintenanceBooking`, `AutoServeFleetMaintenanceBooking`, `Booking`, `BookingController`)
2. `Logistics` (2 berkas: `DeliverVehicleByCarrierAction`, `Truck`)
3. `Mall` (4 berkas: `CheckInVehicleAction`, `RegisterParkingMemberAction`, `ParkingController`, `MallSeeder`)
4. `Store` (4 berkas: `ListVehicleForSaleAction`, `PurchaseC2cVehicleAction`, `UpdateOrderStatusAction`, `C2cController`)

Ketergantungan langsung ini menyebabkan modul-modul bisnis terikat erat pada detail implementasi tabel `core_vehicles`, mutasi kolom, dan trait internal Core.

## Keputusan
1. **Pemisahan Kontrak Lintas Modul (`Modules\Core\Contracts`)**:
   Menyediakan kontrak resmi pada namespace `Modules\Core\Contracts` yang dapat diakses oleh modul mana pun:
   - `Modules\Core\Contracts\VehicleDirectory`: Kontrak layanan query data kendaraan berbasis snapshot DTO.
     - `findById(int $id): ?VehicleSnapshot`
     - `findByPlateNumber(string $plateNumber): ?VehicleSnapshot`
     - `snapshotsByIds(array $ids): array`
     - `activeForUser(int $userId): array`
   - `Modules\Core\Contracts\VehicleSnapshot`: Value Object immutable dengan tipe data terjamin (`id`, `uuid`, `userId`, `carId`, `plateNumber`, `vin`, `color`, `odometerKm`, `status`, `acquiredAt`).
   - `Modules\Core\Contracts\VehicleModelResolver`: Resolver dinamis untuk kebutuhan relasi Eloquent (`belongsTo`, `findOrFail`, `query`) tanpa direct coupling ke `Modules\Core\Domain\Models\Vehicle`.
2. **Penguatan Model Kontrak Dasar (`Modules\Core\Contracts\Vehicle`)**:
   Kelas `Modules\Core\Contracts\Vehicle` (yang telah ada sebagai kelas dasar yang dieksekusi oleh `Modules\Core\Domain\Models\Vehicle`) diperkaya dengan deklarasi relasi `user()`, `car()`, dan scope aktif agar interoperabel penuh saat modul menggunakan type-hint kontrak.
3. **Migrasi 14 Berkas Pemanggil**:
   Seluruh 14 berkas di AutoServe, Logistics, Mall, dan Store dialihkan dari `use Modules\Core\Domain\Models\Vehicle;` menjadi menggunakan `Modules\Core\Contracts\Vehicle`, `VehicleDirectory`, atau `VehicleModelResolver`.
4. **Verifikasi Kompatibilitas**:
   Pengujian integrasi dan fitur yang ada pada keempat modul bisnis tersebut wajib tetap berjalan 100% hijau tanpa degradasi fungsionalitas, sementara pelanggaran A1 pada `arch:scan` turun menjadi tepat 0.

## Konsekuensi
- Aturan arsitektur A1 mencapai status zero violation ($14 \to 0$).
- Lapisan domain modul bisnis terlindungi dari perubahan internal skema tabel kendaraan Core.
- Memungkinkan caching transparan snapshot kendaraan pada layer kontrak tanpa memodifikasi kode pemanggil di modul bisnis.

