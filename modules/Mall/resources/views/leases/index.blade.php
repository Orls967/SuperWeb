<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Kontrak Sewa Mall (Leasing)
                </h2>
                <p class="text-sm text-slate-500 mt-1">Kelola perjanjian sewa unit tenant, model sewa omzet vs tetap, dan deposit jaminan</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('mall.leases.create') }}" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Buat Kontrak Sewa Baru</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            <!-- Filter Bar -->
            <div class="p-4 bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('mall.leases.index') }}" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <div>
                        <select name="property_id" onchange="this.form.submit()" class="rounded-xl border-slate-200 text-xs font-medium focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Semua Properti Mall</option>
                            @foreach($properties as $p)
                                <option value="{{ $p->id }}" {{ $selectedPropertyId === $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <select name="status" onchange="this.form.submit()" class="rounded-xl border-slate-200 text-xs font-medium focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Semua Status Kontrak</option>
                            <option value="draft" {{ $selectedStatus === 'draft' ? 'selected' : '' }}>Konsep (Draft)</option>
                            <option value="active" {{ $selectedStatus === 'active' ? 'selected' : '' }}>Aktif</option>
                            <option value="suspended" {{ $selectedStatus === 'suspended' ? 'selected' : '' }}>Ditangguhkan</option>
                            <option value="terminated" {{ $selectedStatus === 'terminated' ? 'selected' : '' }}>Diterminasi</option>
                            <option value="expired" {{ $selectedStatus === 'expired' ? 'selected' : '' }}>Kedaluwarsa</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 pl-2">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="expiring_soon" value="1" onchange="this.form.submit()" {{ $expiringOnly ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-xs font-semibold text-slate-700">Hanya Expiring Soon (< 90 Hari)</span>
                        </label>
                    </div>
                </form>
            </div>

            <!-- Lease Agreements Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50/70 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                <th class="p-4">No. Kontrak & Properti</th>
                                <th class="p-4">Tenant Mitra</th>
                                <th class="p-4">Unit & Luas</th>
                                <th class="p-4">Masa Berlaku</th>
                                <th class="p-4 text-right">Sewa Bulanan (Base)</th>
                                <th class="p-4 text-center">Deposit</th>
                                <th class="p-4 text-center">Status</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($leases as $l)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="p-4">
                                        <div class="font-mono font-bold text-blue-700">{{ $l->lease_number }}</div>
                                        <div class="text-[11px] text-slate-400 mt-0.5">{{ $l->property?->name }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-slate-900">{{ $l->tenant?->brand_name }}</div>
                                        <div class="text-xs text-slate-400">{{ $l->tenant?->company_name }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-slate-800">Unit {{ $l->unit?->unit_number }}</div>
                                        <div class="text-xs text-slate-500">Lantai {{ $l->unit?->floor }} &bull; {{ $l->unit?->area_sqm }} m²</div>
                                    </td>
                                    <td class="p-4 text-xs">
                                        <div class="text-slate-700 font-medium">{{ $l->start_date->format('d/m/Y') }} s/d {{ $l->end_date->format('d/m/Y') }}</div>
                                        @if($l->isExpiredSoon())
                                            <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                                Expiring Soon
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-right font-black text-slate-900">
                                        Rp {{ number_format($l->currentMonthlyRent(), 0, ',', '.') }}
                                        <div class="text-[10px] font-normal text-slate-400 font-sans">+ SC: Rp {{ number_format($l->service_charge_monthly, 0, ',', '.') }}</div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold
                                            {{ $l->deposit_status->value === 'held' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $l->deposit_status->label() }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold border {{ $l->status->badgeClass() }}">
                                            {{ $l->status->label() }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <a href="{{ route('mall.leases.show', $l) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-all">
                                            Rincian &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-10 text-center text-slate-400 italic">Belum ada kontrak sewa unit mall.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($leases->hasPages())
                    <div class="p-4 border-t border-slate-100">
                        {{ $leases->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
