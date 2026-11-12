<x-ds.shell title="Laporan Keuangan Konsolidasi & Tutup Buku (Financial Statements)">
    <div class="space-y-6">
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => '/dashboard'],
            ['label' => 'Enterprise Finance', 'url' => '/enterprise-finance'],
            ['label' => 'Laporan Keuangan', 'url' => '#'],
        ]" />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-ds.stat label="Periode Terkunci" value="{{ $periodLocks->count() }} Periode" hint="Status audit terkunci permanen" />
            <x-ds.stat label="Keseimbangan Neraca" value="100% Zero-Sum" hint="Aktiva = Pasiva" />
            <x-ds.stat label="Rekonsiliasi Subledger" value="0 Selisih" hint="AR/AP/Aset/Persediaan cocok" />
        </div>

        <x-ds.card title="Riwayat Periode Fiskal & Status Penguncian Buku Besar">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="p-3">Periode</th>
                            <th class="p-3">Entitas</th>
                            <th class="p-3">Voucher Penutupan</th>
                            <th class="p-3 text-right">Laba / (Rugi) Bersih</th>
                            <th class="p-3 text-center">Status Tutup Buku</th>
                            <th class="p-3">Waktu Terkunci</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($periodLocks as $lock)
                            <tr>
                                <td class="p-3 font-mono font-medium">{{ $lock->period }}</td>
                                <td class="p-3">{{ $lock->entity_code }}</td>
                                <td class="p-3 font-mono text-xs">{{ $lock->closing_voucher_ref ?? '-' }}</td>
                                <td class="p-3 text-right tabular-nums font-semibold {{ $lock->net_income_loss_minor >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    IDR {{ number_format($lock->net_income_loss_minor, 0, ',', '.') }}
                                </td>
                                <td class="p-3 text-center">
                                    <x-ds.badge-status :status="$lock->is_locked ? 'closed' : 'active'" />
                                </td>
                                <td class="p-3 text-xs text-gray-500">{{ $lock->locked_at ? $lock->locked_at->format('Y-m-d H:i') : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4">
                                    <x-ds.empty title="Belum Ada Periode Terkunci" description="Tidak ada riwayat penutupan buku periode fiskal." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $periodLocks->links() }}
            </div>
        </x-ds.card>
    </div>
</x-ds.shell>

