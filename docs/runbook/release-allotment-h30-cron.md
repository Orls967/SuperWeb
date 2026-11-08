# Runbook Operasional: Eksekusi Terjadwal Pelepasan Kamar Blok (Cutoff H-30/H-14/H-7)

## 1. Konteks & Deskripsi Layanan
Runbook ini memandu Tim IT Operations, Revenue Manager, dan Night Auditor dalam mengawasi, menjalankan secara manual (*manual trigger*), serta menanggulangi kegagalan pada proses cronjob pelepasan inventori kamar blok MICE:
- Perintah Artisan: `php artisan hotel:release-room-blocks`
- Jadwal Otomatis: Setiap hari pukul 02:15 UTC (sebelum rangkaian Night Audit utama).
- Kelas Eksekutif: `Modules\Hotel\Application\Commands\HotelReleaseRoomBlockAllotmentsCommand`

## 2. Diagram Alur Keputusan Operasional
```
[Start Cronjob 02:15]
         │
         ▼
[Ambil semua blok MICE berstatus CONTRACTED]
         │
         ▼
[Hitung Days Until Event = EventDate - Today]
         │
    ┌────┴────────────────────────┬────────────────────────┬────────────────────────┐
    ▼                             ▼                        ▼                        ▼
Δ > 30 Hari                 14 < Δ <= 30             7 < Δ <= 14                Δ <= 7 Hari
[Abaikan, Status SAFE]      [Pelepasan H-30: 25%]    [Pelepasan H-14: 50%]    [Final Release: 100%]
                                  │                        │                        │
                                  └────────────────────────┴────────────────────────┘
                                                           │
                                                           ▼
                                            [Panggil BlockAllotmentReleaser]
                                                           │
                                                           ▼
                                            [Terbitkan Sertifikat Hash SHA-256]
                                                           │
                                                           ▼
                                            [Update Kuota di htl_room_blocks]
                                                           │
                                                           ▼
                                            [Kirim Notifikasi Webhook ke Organizer]
                                                           │
                                                           ▼
                                                      [Selesai]
```

## 3. Prosedur Eksekusi Manual
Jika cronjob server gagal berjalan karena pemeliharaan infrastruktur, jalankan secara manual:

```bash
# Jalankan dry-run untuk simulasi tanpa mengubah database:
php artisan hotel:release-room-blocks --dry-run

# Jalankan eksekusi penuh untuk properti tertentu:
php artisan hotel:release-room-blocks --property=HTL-BALI-01

# Jalankan untuk seluruh properti secara batch:
php artisan hotel:release-room-blocks --force
```

## 4. Matriks Investigasi Masalah (Troubleshooting Matrix)
| Gejala | Penyebab Umum | Tindakan Penanganan |
|---|---|---|
| Perintah berhenti dengan galat `PessimisticLockException` | Transaksi paralel oleh staf reservasi saat menginput rooming list | Jalankan ulang dengan opsi retry `--retry-attempts=3` atau jalankan di luar jam operasional reservasi. |
| Jumlah kamar yang dilepas 0 padahal $\Delta = 14$ hari | Organizer telah melakukan pickup 100% dari kuota kontrak | Verifikasi kolom `confirmed_pickup_rooms` pada tabel `htl_room_blocks`. Jika sama dengan alokasi, status normal. |
| Organizer komplain kamar berkurang tanpa pemberitahuan | Email peringatan pra-cutoff H-35 gagal terkirim (bounce) | Periksa antrean `mail_logs` dan berikan tautan audit trail sertifikat pelepasan digital. |
| Invariant breach: $K_{\text{new}} < P_{\text{pickup}}$ | Kesalahan perhitungan manual di luar algoritma | Segera jalankan perintah verifikasi integritas: `php artisan hotel:audit-mice-contracts --verify-invariants`. |

## 5. Prosedur Eskalasi & Rollback
Jika terjadi pelepasan kamar yang salah akibat kesalahan tanggal acara yang diinput oleh sales:
1. Hubungi Revenue Manager dan GM untuk otorisasi koreksi.
2. Buka form **Room Block Emergency Allocation** di PMS.
3. Masukkan jumlah kamar pengganti yang harus ditarik kembali dari inventori publik ke kuota blok grup.
4. Catat nomor tiket insiden dan sertifikat kompensasi di log audit.
