<x-ds.shell title="Tata Kelola & Pengendalian Anggaran (Budget Encumbrance)">
    <div class="space-y-6">
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => '/dashboard'],
            ['label' => 'Enterprise Finance', 'url' => '/enterprise-finance'],
            ['label' => 'Anggaran & Encumbrance', 'url' => '#'],
        ]" />

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-ds.stat label="Total Alokasi Pagu" value="IDR 10.000.000.000" hint="Pagu tahun berjalan" />
            <x-ds.stat label="Komitmen Tertahan (Reserved)" value="IDR 2.450.000.000" hint="Terkunci PO Procurement" />
            <x-ds.stat label="Realisasi Belanja (Spent)" value="IDR 4.100.000.000" hint="Faktur terverifikasi" />
            <x-ds.stat label="Sisa Pagu Bebas (Available)" value="IDR 3.450.000.000" hint="Tersedia untuk komitmen baru" />
        </div>

        @if(session('status'))
            <x-ds.toast type="success" :message="session('status')" />
        @endif

        <x-ds.card title="Daftar Mata Anggaran Departemen">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="p-3">Kode Anggaran</th>
                            <th class="p-3">Entitas / Cost Center</th>
                            <th class="p-3">Akun Beban</th>
                            <th class="p-3 text-right">Pagu Dialokasikan</th>
                            <th class="p-3 text-right">Komitmen (Reserved)</th>
                            <th class="p-3 text-right">Realisasi (Spent)</th>
                            <th class="p-3 text-center">Status</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($budgets as $bgt)
                            <tr>
                                <td class="p-3 font-mono font-medium">{{ $bgt->budget_code }}</td>
                                <td class="p-3">{{ $bgt->cost_center_code }}</td>
                                <td class="p-3">{{ $bgt->account_code }}</td>
                                <td class="p-3 text-right tabular-nums">IDR {{ number_format($bgt->allocated_amount_idr, 0, ',', '.') }}</td>
                                <td class="p-3 text-right tabular-nums text-yellow-600">IDR {{ number_format($bgt->encumbered_amount_idr, 0, ',', '.') }}</td>
                                <td class="p-3 text-right tabular-nums text-blue-600">IDR {{ number_format($bgt->spent_amount_idr, 0, ',', '.') }}</td>
                                <td class="p-3 text-center">
                                    <x-ds.badge-status :status="strtolower($bgt->status ?? 'active')" />
                                </td>
                                <td class="p-3 text-center">
                                    <a href="/enterprise-finance/budgets/{{ $bgt->id }}/commitments" class="text-indigo-600 hover:underline text-xs">Lihat Komitmen</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-4">
                                    <x-ds.empty title="Belum Ada Anggaran" description="Tidak ada data mata anggaran departemen yang terdaftar." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $budgets->links() }}
            </div>
        </x-ds.card>
    </div>
</x-ds.shell>

