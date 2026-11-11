<x-ds.shell title="Matriks Pemisahan Tugas & Tata Kelola Peran (SoD Matrix)">
    <div class="space-y-6">
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => '/dashboard'],
            ['label' => 'Enterprise Finance', 'url' => '/enterprise-finance'],
            ['label' => 'Matriks SoD', 'url' => '#'],
        ]" />

        @if(session('status'))
            <x-ds.toast type="success" :message="session('status')" />
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-ds.card title="Katalog 12 Aturan Baku Konflik Pemisahan Tugas">
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @foreach($rules as $r)
                        <div class="p-3 border rounded text-xs space-y-1">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-gray-800">{{ $r['id'] }}: {{ $r['title'] }}</span>
                                <x-ds.badge :variant="$r['severity']->value === 'CRITICAL' ? 'danger' : 'warning'">
                                    {{ $r['severity']->value }}
                                </x-ds.badge>
                            </div>
                            <div class="text-gray-500">{{ $r['description'] }}</div>
                            <div class="font-mono text-indigo-600">Peran 1: {{ $r['first_role'] }} | Peran 2: {{ $r['second_role'] }}</div>
                        </div>
                    @endforeach
                </div>
            </x-ds.card>

            <x-ds.card title="Penerbitan Dispensasi SoD Berbatas Waktu">
                <form action="{{ route('enterprise_finance.sod.exception') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="sod_rule_select" class="block text-sm font-medium text-gray-700">Kode Aturan SoD</label>
                        <select id="sod_rule_select" name="sod_rule_id" class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm" required>
                            @foreach($rules as $r)
                                <option value="{{ $r['id'] }}">{{ $r['id'] }} - {{ $r['title'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="sod_user_id" class="block text-sm font-medium text-gray-700">ID Pengguna Operator</label>
                        <input id="sod_user_id" type="number" name="user_id" value="101" class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm" required />
                    </div>

                    <div>
                        <label for="sod_expires_days" class="block text-sm font-medium text-gray-700">Masa Berlaku Dispensasi (Hari)</label>
                        <input id="sod_expires_days" type="number" name="expires_days" value="14" min="1" max="90" class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm" required />
                    </div>

                    <div>
                        <label for="sod_justification" class="block text-sm font-medium text-gray-700">Justifikasi Bisnis Otorisasi</label>
                        <textarea id="sod_justification" name="justification" rows="2" class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm" required>Penggantian darurat cuti operasional tim pengadaan dan akuntansi.</textarea>
                    </div>

                    <x-ds.button type="submit" variant="primary">Terbitkan Dispensasi SoD</x-ds.button>
                </form>
            </x-ds.card>
        </div>

        <x-ds.card title="Daftar Dispensasi SoD Aktif">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="p-3">Aturan SoD</th>
                            <th class="p-3">Pengguna</th>
                            <th class="p-3">Pemberi Izin</th>
                            <th class="p-3">Justifikasi</th>
                            <th class="p-3">Kedaluwarsa</th>
                            <th class="p-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($exceptions as $ex)
                            <tr>
                                <td class="p-3 font-mono font-medium">{{ $ex->sod_rule_id }}</td>
                                <td class="p-3">User #{{ $ex->user_id }}</td>
                                <td class="p-3">User #{{ $ex->granted_by }}</td>
                                <td class="p-3 text-xs text-gray-600">{{ $ex->justification }}</td>
                                <td class="p-3 text-xs">{{ $ex->expires_at->format('Y-m-d H:i') }}</td>
                                <td class="p-3 text-center">
                                    <x-ds.badge-status :status="$ex->is_active ? 'active' : 'closed'" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4">
                                    <x-ds.empty title="Nihil Dispensasi" description="Tidak ada dispensasi pemisahan tugas aktif saat ini." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $exceptions->links() }}
            </div>
        </x-ds.card>
    </div>
</x-ds.shell>
