<x-app-layout>
    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-900/60 backdrop-blur-md p-6 rounded-2xl border border-slate-800 shadow-xl">
            <div>
                <a href="{{ route('resto.outlets.index') }}" class="text-sm text-slate-400 hover:text-white flex items-center gap-1 mb-2">
                    ← Kembali ke Daftar Cabang
                </a>
                <div class="flex items-center gap-3">
                    <span class="px-2.5 py-1 text-xs font-semibold rounded-lg {{ $outlet->isCentralKitchen() ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                        {{ $outlet->type->label() }}
                    </span>
                    <h1 class="text-2xl font-bold text-white tracking-tight">{{ $outlet->name }}</h1>
                    <span class="text-sm text-slate-400 font-mono">({{ $outlet->code }})</span>
                </div>
            </div>
            <div>
                <a href="{{ route('resto.outlets.edit', $outlet) }}" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-medium transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Data Cabang
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Details Card -->
            <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 space-y-4 shadow-lg">
                <h3 class="text-base font-bold text-white border-b border-slate-800 pb-3">Informasi Operasional</h3>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-400">Alamat</dt>
                        <dd class="text-slate-200 font-medium mt-0.5">{{ $outlet->address }}, {{ $outlet->city }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">Telepon</dt>
                        <dd class="text-slate-200 font-medium mt-0.5">{{ $outlet->phone ?: '-' }}</dd>
                    </div>
                    @if($outlet->mall_unit_ref)
                        <div>
                            <dt class="text-xs text-slate-400">Unit Mall Terhubung</dt>
                            <dd class="text-indigo-400 font-semibold mt-0.5">{{ $outlet->mall_unit_ref }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-slate-400">Jam Operasional</dt>
                        <dd class="text-slate-200 font-medium mt-0.5">{{ substr($outlet->opens_at, 0, 5) }} - {{ substr($outlet->closes_at, 0, 5) }} WITA</dd>
                    </div>
                    @if(!$outlet->isCentralKitchen())
                        <div>
                            <dt class="text-xs text-slate-400">Kapasitas Kursi Meja</dt>
                            <dd class="text-slate-200 font-medium mt-0.5">{{ $outlet->seats }} Kursi</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-slate-400">Status Operasi</dt>
                        <dd class="mt-0.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $outlet->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                                {{ $outlet->is_active ? 'Beroperasi Aktif' : 'Tutup Sementara' }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Assigned Staff Card -->
            <div class="lg:col-span-2 bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 space-y-4 shadow-lg">
                <h3 class="text-base font-bold text-white border-b border-slate-800 pb-3">Staff & Karyawan Ditugaskan ({{ $outlet->staffAssignments->count() }})</h3>
                <div class="divide-y divide-slate-800">
                    @forelse($outlet->staffAssignments as $assignment)
                        <div class="py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-slate-800 flex items-center justify-center text-slate-300 font-semibold text-sm">
                                    {{ strtoupper(substr($assignment->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ $assignment->user->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $assignment->user->email }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase tracking-wider">
                                    {{ $assignment->role }}
                                </span>
                                <span class="text-xs {{ $assignment->is_active ? 'text-emerald-400' : 'text-slate-500' }}">
                                    {{ $assignment->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-400">Belum ada staff khusus yang ditugaskan ke outlet ini.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
