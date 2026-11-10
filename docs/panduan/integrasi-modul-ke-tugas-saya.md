# Panduan Pengembang: Menghubungkan Alur Kerja Modul ke Kotak Masuk "Tugas Saya" (§19.1)

**Fase:** 194 — Platform: Unified Task Inbox Integration Guide  
**Sasaran:** Pengembang Modul Domain di Seluruh 30 Lini Bisnis  
**Layanan Inti:** `Modules\Core\Application\Actions\TaskInbox\TaskInboxService`

---

## 1. Konsep Penghubungan Alur Kerja

Setiap kali modul bisnis menghasilkan transaksi yang membutuhkan persetujuan manusia (*human-in-the-loop approval*), peninjauan (*review*), atau tindakan operasional (*actionable dispatch*), modul tersebut harus mendaftarkan tugas ke Kotak Masuk "Tugas Saya".

### Skenario Umum Penggunaan:
1. **Pengadaan Barang (Procurement):** Pembuatan Purchase Order senilai di atas Rp 50.000.000 membutuhkan persetujuan `finance_manager`.
2. **Perbaikan Bengkel (AutoServe):** Estimasi biaya tambahan pada Work Order membutuhkan persetujuan pelanggan atau `service_manager`.
3. **Pemesanan Hotel (Hotel):** Permintaan relokasi tamu walk-overbooking membutuhkan otorisasi `hotel_front_office_manager`.
4. **Rilis Design System (Core):** Komponen baru yang berstatus `candidate` membutuhkan persetujuan dari `design_system_owner`.

---

## 2. API Pembuatan Tugas (`TaskInboxService::createTask`)

Untuk mendaftarkan tugas baru dari modul Anda:

```php
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;

$inboxService = app(TaskInboxService::class);

$task = $inboxService->createTask([
    'title' => 'Persetujuan Work Order Perbaikan Mesin #WO-2026-081',
    'module' => 'AutoServe',
    'task_type' => 'APPROVAL',
    'priority' => 'HIGH',
    'assigned_role' => 'workshop_manager',
    'assigned_user_id' => null, // null jika dapat diambil oleh siapa saja dengan role ini
    'action_url' => '/autoserve/work-orders/WO-2026-081',
    'due_date' => now()->addHours(24)->toDateTimeString(),
    'metadata' => [
        'work_order_id' => 'WO-2026-081',
        'customer_name' => 'PT Maju Bersama',
        'estimated_idr' => 12500000,
    ],
    'idempotency_key' => 'task-create:autoserve:WO-2026-081:approval',
]);
```

---

## 3. Menangani Callback Aksi Persetujuan

Ketika pengguna menekan tombol "Setujui" atau "Tolak" di halaman `/tugas-saya`, `TaskInboxController` mengeksekusi metode `completeTask()` atau `rejectTask()` pada `TaskInboxService`.

Untuk mengaitkan aksi tersebut kembali ke domain bisnis Anda:
1. Daftarkan event listener yang mendengarkan `Modules\Core\Domain\Events\TaskStatusTransitionedEvent`.
2. Pada listener modul Anda, periksa apakah `$event->module === 'NamaModulAnda'`.
3. Eksekusi Action domain yang sesuai (misal: `ApprovePurchaseOrderAction` atau `RejectWorkOrderAction`).

Contoh implementasi listener:
```php
namespace Modules\AutoServe\Listeners;

use Modules\Core\Domain\Events\TaskStatusTransitionedEvent;
use Modules\AutoServe\Application\Actions\ApproveWorkOrderAction;

class HandleTaskInboxDecisionListener
{
    public function handle(TaskStatusTransitionedEvent $event): void
    {
        if ($event->module !== 'AutoServe') {
            return;
        }

        if ($event->toStatus === 'COMPLETED' && $event->taskType === 'APPROVAL') {
            $woId = $event->metadata['work_order_id'] ?? null;
            if ($woId) {
                app(ApproveWorkOrderAction::class)->execute($woId, $event->actorId);
            }
        }
    }
}
```

---

## 4. Standar Prioritas & Tenggat Waktu (SLA)

| Tingkat Prioritas | Nilai Enum | SLA Bawaan | Warna Lencana | Skenario Penggunaan |
|---|---|---|---|---|
| Kritis | `CRITICAL` | 4 Jam | Merah | Overbooking hotel, insiden keselamatan pelayaran, kegagalan server |
| Tinggi | `HIGH` | 24 Jam | Oranye | Persetujuan pesanan bernilai tinggi, penawaran kontrak legal |
| Sedang | `MEDIUM` | 72 Jam | Biru | Verifikasi berkas mitra baru, penjadwalan pemeliharaan preventif |
| Rendah | `LOW` | 7 Hari | Abu-abu | Tinjauan kepatuhan rutin tahunan, survei kepuasan pelanggan |
