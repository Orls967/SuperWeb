# Panduan Pengguna: Operations Controller (Fase 177)

Peran **Operations Controller (`ops_controller`)** bertanggung jawab atas pengawasan pergerakan seluruh jadwal penerbangan, mitigasi disrupsi operasional (IRROPS), dan alokasi armada harian.

---

## 1. Akses & Navigasi Antarmuka

Layar kerja utama Operations Controller dapat diakses melalui rute `/aviation/ops`.

Fitur-fitur utama pada portal kendali:
- **Papan Jadwal Penerbangan (Flight Operations Board):** Menampilkan seluruh penerbangan aktif dalam rentang hari berjalan (H-0 hingga H+1).
- **Indikator Kesiapan Terbang (Airworthiness Status):** Label visual berwarna yang menunjukkan apakah penerbangan telah melewati gerbang pelepasan teknis A2 dan penimbangan W&B A3.
- **Pusat Aksi Cepat (Quick Actions):**
  - Tombol **Boarding:** Membuka gerbang masuk penumpang ketika armada siap di apron.
  - Tombol **Depart Flight:** Mengonfirmasi lepas landas armada dan memicu pengakuan otomatis pendapatan tiket *unearned* ke *earned* pada buku besar.
  - Tombol **Cancel Flight (Disrupsi IRROPS):** Membatalkan penerbangan dan memicu saga pengembalian dana tiket massal (*FlightCancellationMassRefundSaga*).

---

## 2. Prosedur Penanganan Disrupsi Operasional (IRROPS)

Ketika terjadi cuaca buruk, penutupan landas pacu bandara, atau anomali armada:
1. Pilih penerbangan terdampak pada papan kendali operasional.
2. Masukkan alasan pembatalan resmi (misal: `WEATHER_BELOW_MINIMUMS`, `TECHNICAL_AOG`, `NOTAM_AIRPORT_CLOSURE`).
3. Tekan tombol **Batalkan Penerbangan**.
4. Sistem secara otomatis:
   - Mengubah status penerbangan menjadi `cancelled`.
   - Mengubah status seluruh tiket penumpang menjadi `cancelled`.
   - Menghitung hak pengembalian dana penuh 100% tanpa potongan denda (karena pembatalan sepihak maskapai sesuai PM 89/2015).
   - Membukukan kewajiban pengembalian dana pada akun buku besar `avi:refund_payable:IDR`.
   - Menghubungi modul Hotel untuk menerbitkan voucer akomodasi darurat bagi penumpang transit yang terdampak penundaan bermalam.
