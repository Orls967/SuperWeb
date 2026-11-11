<x-ds.shell title="Uji Kontrol Internal Pelaporan Keuangan (RCM ICFR)">
    <div class="space-y-6">
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => '/dashboard'],
            ['label' => 'Enterprise Finance', 'url' => '/enterprise-finance'],
            ['label' => 'Kontrol Internal RCM', 'url' => '#'],
        ]" />

        @if(session('status'))
            <x-ds.toast type="success" :message="session('status')" />
        @endif

        <div class="flex justify-between items-center">
            <div class="text-sm text-gray-500">Katalog Titik Kendali ICFR & Pengujian Harian Terjadwal</div>
            <form action="{{ route('enterprise_finance.controls.run') }}" method="POST">
                @csrf
                <x-ds.button type="submit" variant="primary" size="sm">Jalankan Uji Kontrol Sekarang</x-ds.button>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @foreach($points as $pt)
                <div class="p-4 border rounded bg-white shadow-sm space-y-2">
                    <div class="font-mono text-xs text-indigo-600 font-bold">{{ $pt->id }}</div>
                    <div class="text-sm font-semibold text-gray-800">{{ $pt->name }}</div>
                    <div class="text-xs text-gray-500">{{ $pt->description }}</div>
                    <x-ds.badge :variant="$pt->automated ? 'success' : 'neutral'">
                        {{ $pt->automated ? 'OTOMATIS' : 'MANUAL' }}
                    </x-ds.badge>
                </div>
            @endforeach
        </div>

        <x-ds.card title="Riwayat Hasil Pengujian Kontrol Harian (Test Runs)">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="p-3">Waktu Uji</th>
                            <th class="p-3">Titik Kendali</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3 text-right">Sampel</th>
                            <th class="p-3 text-right">Deviasi</th>
                            <th class="p-3">Tiket CoreTask</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($testRuns as $tr)
                            <tr>
                                <td class="p-3 text-xs">{{ $tr->tested_at->format('Y-m-d H:i') }}</td>
                                <td class="p-3 font-mono text-xs font-medium">{{ $tr->control_id }}</td>
                                <td class="p-3 text-center">
                                    <x-ds.badge-status :status="strtolower($tr->status)" />
                                </td>
                                <td class="p-3 text-right tabular-nums">{{ $tr->sample_count }}</td>
                                <td class="p-3 text-right tabular-nums font-semibold {{ $tr->deviation_count > 0 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $tr->deviation_count }}
                                </td>
                                <td class="p-3 font-mono text-xs text-gray-500">{{ $tr->task_id ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4">
                                    <x-ds.empty title="Belum Ada Hasil Uji Kontrol" description="Jalankan pengujian otomatis harian untuk memvalidasi kontrol." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $testRuns->links() }}
            </div>
        </x-ds.card>
    </div>
</x-ds.shell>

