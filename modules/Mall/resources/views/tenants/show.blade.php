<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                        {{ $tenant->brand_name }}
                    </h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $tenant->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                        {{ $tenant->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
                <p class="text-sm text-slate-500 mt-1">{{ $tenant->company_name }} &bull; Kategori: {{ $tenant->category->label() }}</p>
            </div>
            <div>
                <a href="{{ route('mall.tenants.index') }}" class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-xl hover:bg-slate-50">
                    &larr; Daftar Tenant
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Profile & Contact Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="font-bold text-base text-slate-900 border-b border-slate-100 pb-3">Profil & Kontak Penanggung Jawab</h3>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs mt-4">
                    <div>
                        <span class="text-slate-400 block mb-1">Nama PIC</span>
                        <span class="font-bold text-slate-800 text-sm block">{{ $tenant->pic_name }}</span>
                    </div>

                    <div>
                        <span class="text-slate-400 block mb-1">WhatsApp / Telepon</span>
                        <span class="font-semibold text-slate-800 font-mono">{{ $tenant->pic_phone }}</span>
                    </div>

                    <div>
                        <span class="text-slate-400 block mb-1">Email Resmi</span>
                        <span class="font-semibold text-slate-800">{{ $tenant->pic_email }}</span>
                    </div>

                    <div>
                        <span class="text-slate-400 block mb-1">NPWP Badan Usaha</span>
                        <span class="font-semibold text-slate-800 font-mono">{{ $tenant->npwp ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- List of Leased Units -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900 text-sm">Riwayat & Daftar Sewa Unit</h3>
                        <p class="text-xs text-slate-500">Unit komersial yang disewa oleh tenant ini</p>
                    </div>
                    <a href="{{ route('mall.leases.create') }}" class="text-xs font-bold text-blue-600 hover:text-blue-700">
                        + Sewa Unit Baru
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                <th class="p-4">No. Kontrak</th>
                                <th class="p-4">Unit & Properti</th>
                                <th class="p-4">Masa Berlaku</th>
                                <th class="p-4 text-right">Sewa Bulanan</th>
                                <th class="p-4 text-center">Status</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($tenant->leases as $lease)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="p-4 font-mono font-bold text-blue-700 text-xs">{{ $lease->lease_number }}</td>
                                    <td class="p-4">
                                        <div class="font-bold text-slate-800">Unit {{ $lease->unit?->unit_number }}</div>
                                        <div class="text-xs text-slate-400">{{ $lease->property?->name }} (Lantai {{ $lease->unit?->floor }})</div>
                                    </td>
                                    <td class="p-4 text-xs text-slate-600">
                                        {{ $lease->start_date->format('d/m/Y') }} s/d {{ $lease->end_date->format('d/m/Y') }}
                                    </td>
                                    <td class="p-4 text-right font-black text-slate-900">
                                        Rp {{ number_format($lease->currentMonthlyRent(), 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $lease->status->badgeClass() }}">
                                            {{ $lease->status->label() }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <a href="{{ route('mall.leases.show', $lease) }}" class="px-3 py-1 rounded-lg border text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Rincian &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-400 italic">Belum ada kontrak sewa untuk tenant ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
