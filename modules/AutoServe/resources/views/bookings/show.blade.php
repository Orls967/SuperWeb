@extends('layouts.app')

@section('title', 'Detail Booking')
@section('subtitle', $booking->booking_code)

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    {{-- ============================================================ --}}
    {{-- BOOKING HEADER --}}
    {{-- ============================================================ --}}
    <div class="glass-card rounded-2xl p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="font-mono text-xl font-bold gradient-text">{{ $booking->booking_code }}</span>
                    @php
                        $statusColors = [
                            'pending' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
                            'confirmed' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                            'in_progress' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                            'completed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                            'invoiced' => 'bg-slate-500/10 text-slate-400 border-slate-500/20',
                        ];
                    @endphp
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border {{ $statusColors[$booking->status] ?? '' }}">
                        @if($booking->status === 'in_progress')
                            <span class="w-2 h-2 rounded-full bg-indigo-400 pulse-dot"></span>
                        @endif
                        {{ strtoupper(str_replace('_', ' ', $booking->status)) }}
                    </span>
                </div>
                <p class="text-sm text-slate-400 mt-1">Dibuat {{ $booking->created_at->diffForHumans() }}</p>
            </div>

            <div class="flex items-center gap-2">
                @if(in_array($booking->status, ['completed', 'invoiced']))
                <a href="{{ route('bookings.invoice', $booking) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20 transition-all text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Cetak Invoice
                </a>
                @endif
                <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl text-sm text-slate-400 hover:text-white hover:bg-slate-700/50 transition-all">← Kembali</a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ============================================================ --}}
        {{-- LEFT: Info Detail --}}
        {{-- ============================================================ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Kendaraan & Customer --}}
            <div class="glass-card rounded-2xl p-6">
                <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-4">Informasi Kendaraan</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-slate-500">Plat Nomor</p>
                        <p class="font-bold text-white font-mono text-lg">{{ $booking->plate_number }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Merk / Model</p>
                        <p class="text-white font-medium">{{ $booking->vehicle_brand }} {{ $booking->vehicle_model }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Tahun</p>
                        <p class="text-slate-300">{{ $booking->vehicle_year ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Customer</p>
                        <p class="text-white font-medium">{{ $booking->customer->name }}</p>
                        <p class="text-xs text-slate-400">{{ $booking->customer->email }}</p>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-700/50">
                    <p class="text-xs text-slate-500 mb-1">Keluhan</p>
                    <p class="text-sm text-slate-300 bg-slate-800/50 rounded-xl p-3">{{ $booking->complaint }}</p>
                </div>

                @if($booking->mechanic_notes)
                <div class="mt-3">
                    <p class="text-xs text-slate-500 mb-1">Catatan Mekanik</p>
                    <p class="text-sm text-slate-300 bg-indigo-500/5 border border-indigo-500/10 rounded-xl p-3">{{ $booking->mechanic_notes }}</p>
                </div>
                @endif
            </div>

            {{-- ============================================================ --}}
            {{-- SMART REPAIR ESCROW: ESTIMASI & PERSETUJUAN --}}
            {{-- ============================================================ --}}
            @include('serve::bookings._estimate-panel', [
                'booking' => $booking,
                'activeEstimate' => $activeEstimate,
                'services' => $services,
                'allSpareparts' => $allSpareparts,
            ])

            {{-- ============================================================ --}}
            {{-- SPAREPART MANAGEMENT (hanya staff, hanya saat in_progress) --}}
            {{-- ============================================================ --}}
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-700/50 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wide">Sparepart Digunakan</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Sparepart yang dipasang pada kendaraan</p>
                    </div>
                </div>

                {{-- List sparepart yang sudah ditambahkan --}}
                @if($booking->spareparts->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-slate-500 uppercase">
                                <th class="px-6 py-3">Sparepart</th>
                                <th class="px-6 py-3">Qty</th>
                                <th class="px-6 py-3">Harga</th>
                                <th class="px-6 py-3">Subtotal</th>
                                @if(auth()->user()->isStaff() && $booking->isInProgress())
                                <th class="px-6 py-3"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            @foreach($booking->spareparts as $sp)
                            <tr class="hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-3">
                                    <p class="text-white font-medium">{{ $sp->name }}</p>
                                    <p class="text-xs text-slate-500 font-mono">{{ $sp->code }}</p>
                                </td>
                                <td class="px-6 py-3 text-slate-300">{{ $sp->pivot->quantity }} {{ $sp->unit }}</td>
                                <td class="px-6 py-3 text-slate-300">Rp {{ number_format($sp->pivot->unit_price, 0, ',', '.') }}</td>
                                <td class="px-6 py-3 text-white font-medium">Rp {{ number_format($sp->pivot->subtotal, 0, ',', '.') }}</td>
                                @if(auth()->user()->isStaff() && $booking->isInProgress())
                                <td class="px-6 py-3">
                                    <form action="{{ route('bookings.removeSparepart', [$booking, $sp]) }}" method="POST" onsubmit="return confirm('Hapus sparepart ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg text-red-400/60 hover:text-red-400 hover:bg-red-500/10 transition-all">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="p-8 text-center">
                    <svg class="w-12 h-12 text-slate-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <p class="text-sm text-slate-500">Belum ada sparepart ditambahkan</p>
                </div>
                @endif

                {{-- ============================================================ --}}
                {{-- DYNAMIC FORM: Tambah Sparepart (Alpine.js) --}}
                {{-- Hanya muncul untuk Staff dan saat status In Progress --}}
                {{-- ============================================================ --}}
                @if(auth()->user()->isStaff() && $booking->isInProgress())
                <div class="px-6 py-4 border-t border-slate-700/50 bg-slate-800/30" x-data="sparepartForm()">
                    <button @click="showForm = !showForm" type="button"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium border-2 border-dashed transition-all"
                            :class="showForm ? 'border-blue-500/30 text-blue-400 bg-blue-500/5' : 'border-slate-700 text-slate-400 hover:border-blue-500/30 hover:text-blue-400'">
                        <svg class="w-4 h-4 transition-transform" :class="showForm ? 'rotate-45' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span x-text="showForm ? 'Tutup Form' : 'Tambah Sparepart'"></span>
                    </button>

                    <div x-show="showForm" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-4">
                        <form action="{{ route('bookings.addSparepart', $booking) }}" method="POST" class="space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Pilih Sparepart</label>
                                    <select name="sparepart_id" x-model="selectedSparepart" @change="updatePrice()" required
                                            class="w-full px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all text-sm">
                                        <option value="">-- Pilih Sparepart --</option>
                                        @foreach($spareparts as $sp)
                                        <option value="{{ $sp->id }}" data-price="{{ $sp->price }}" data-stock="{{ $sp->stock }}" data-unit="{{ $sp->unit }}">
                                            {{ $sp->name }} ({{ $sp->code }}) - Stok: {{ $sp->stock }} {{ $sp->unit }} - Rp {{ number_format($sp->price, 0, ',', '.') }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1.5">Jumlah</label>
                                    <input type="number" name="quantity" x-model="quantity" min="1" :max="maxStock" required
                                           class="w-full px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all text-sm"
                                           placeholder="1">
                                </div>
                            </div>

                            {{-- Price Preview --}}
                            <div x-show="selectedSparepart" class="flex items-center justify-between p-3 rounded-xl bg-blue-500/5 border border-blue-500/10">
                                <span class="text-xs text-slate-400">Estimasi subtotal:</span>
                                <span class="text-sm font-bold text-blue-400" x-text="'Rp ' + (unitPrice * quantity).toLocaleString('id-ID')"></span>
                            </div>

                            <button type="submit" class="w-full px-4 py-3 rounded-xl text-sm font-medium bg-gradient-to-r from-blue-500 to-violet-600 text-white hover:from-blue-600 hover:to-violet-700 transition-all shadow-lg shadow-blue-500/25">
                                + Tambah Sparepart ke Job Order
                            </button>
                        </form>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- RIGHT SIDEBAR: Status & Biaya --}}
        {{-- ============================================================ --}}
        <div class="space-y-6">

            {{-- Status Control (Admin/Mekanik) --}}
            @if(auth()->user()->isStaff())
            <div class="glass-card rounded-2xl p-6">
                <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-4">Kontrol Status</h3>

                {{-- Assign Mekanik (Admin) --}}
                @if(auth()->user()->isAdmin() && $booking->isPending())
                <form action="{{ route('bookings.assign', $booking) }}" method="POST" class="mb-4">
                    @csrf @method('PATCH')
                    <label class="block text-xs text-slate-400 mb-1.5">Assign Mekanik</label>
                    <select name="mechanic_id" required class="w-full px-3 py-2.5 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-sm focus:border-blue-500 mb-3">
                        <option value="">-- Pilih Mekanik --</option>
                        @foreach(\App\Models\User::where('role', 'mekanik')->get() as $mech)
                        <option value="{{ $mech->id }}">{{ $mech->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-medium bg-blue-500 hover:bg-blue-600 text-white transition-all">
                        Assign & Konfirmasi
                    </button>
                </form>
                @endif

                {{-- Status Transition --}}
                @if($booking->mechanic_id && !$booking->isInvoiced())
                <form action="{{ route('bookings.updateStatus', $booking) }}" method="POST">
                    @csrf @method('PATCH')

                    @if($booking->mechanic)
                    <div class="mb-3 flex items-center gap-2 p-2.5 rounded-lg bg-slate-800/50">
                        <div class="w-8 h-8 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-400 text-xs font-bold">
                            {{ strtoupper(substr($booking->mechanic->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-xs font-medium text-white">{{ $booking->mechanic->name }}</p>
                            <p class="text-[10px] text-slate-500">Mekanik</p>
                        </div>
                    </div>
                    @endif

                    <div class="space-y-3">
                        @if($booking->isConfirmed())
                        <input type="hidden" name="status" value="in_progress">
                        <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-medium bg-indigo-500 hover:bg-indigo-600 text-white transition-all">
                            ▶ Mulai Pengerjaan
                        </button>
                        @elseif($booking->isInProgress())
                        <div class="mb-3">
                            <label class="block text-xs text-slate-400 mb-1.5">Catatan Mekanik</label>
                            <textarea name="mechanic_notes" rows="3" class="w-full px-3 py-2.5 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-sm focus:border-blue-500 resize-none" placeholder="Catatan perbaikan...">{{ $booking->mechanic_notes }}</textarea>
                        </div>
                        @if($booking->vehicle)
                        <div class="mb-3">
                            <label class="block text-xs text-slate-400 mb-1.5">
                                Odometer Saat Ini (km) — minimal {{ number_format((int) $booking->vehicle->odometer_km, 0, ',', '.') }}
                            </label>
                            <input type="number" name="odometer_km" required
                                min="{{ (int) $booking->vehicle->odometer_km }}"
                                value="{{ old('odometer_km', (int) $booking->vehicle->odometer_km) }}"
                                class="w-full px-3 py-2.5 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-sm font-mono focus:border-blue-500">
                            @error('odometer_km')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        @endif
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-medium bg-emerald-500 hover:bg-emerald-600 text-white transition-all" onclick="return confirm('Selesaikan job? Stok sparepart akan dipotong otomatis.')">
                            ✓ Selesai & Potong Stok
                        </button>
                        @elseif($booking->isCompleted())
                        <a href="{{ route('bookings.invoice', $booking) }}" class="block w-full px-4 py-2.5 rounded-xl text-sm font-medium bg-gradient-to-r from-emerald-500 to-teal-600 text-white text-center transition-all">
                            🧾 Cetak Invoice
                        </a>
                        @endif
                    </div>
                </form>
                @endif

                {{-- Status Flow Visual --}}
                <div class="mt-5 pt-4 border-t border-slate-700/50">
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-widest mb-3">Status Flow</p>
                    @php
                        $steps = ['pending', 'confirmed', 'in_progress', 'completed', 'invoiced'];
                        $currentIdx = array_search($booking->status, $steps);
                    @endphp
                    <div class="space-y-2">
                        @foreach($steps as $idx => $step)
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold {{ $idx < $currentIdx ? 'bg-emerald-500/20 text-emerald-400' : ($idx === $currentIdx ? 'bg-blue-500 text-white' : 'bg-slate-700 text-slate-500') }}">
                                @if($idx < $currentIdx)
                                    ✓
                                @else
                                    {{ $idx + 1 }}
                                @endif
                            </div>
                            <span class="text-xs {{ $idx === $currentIdx ? 'text-blue-400 font-medium' : ($idx < $currentIdx ? 'text-emerald-400' : 'text-slate-500') }}">
                                {{ ucfirst(str_replace('_', ' ', $step)) }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- Rincian Biaya --}}
            <div class="glass-card rounded-2xl p-6">
                <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-4">Rincian Biaya</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">Jasa Servis</span>
                        <span class="text-white">Rp {{ number_format($booking->service_cost, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">{{ $booking->service->name }}</span>
                        <span class="text-xs text-slate-500">{{ $booking->service->description }}</span>
                    </div>
                    <hr class="border-slate-700/50">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">Total Sparepart</span>
                        <span class="text-white">Rp {{ number_format($booking->sparepart_cost, 0, ',', '.') }}</span>
                    </div>
                    @foreach($booking->spareparts as $sp)
                    <div class="flex justify-between text-xs pl-3">
                        <span class="text-slate-500">{{ $sp->name }} x{{ $sp->pivot->quantity }}</span>
                        <span class="text-slate-400">Rp {{ number_format($sp->pivot->subtotal, 0, ',', '.') }}</span>
                    </div>
                    @endforeach
                    <hr class="border-slate-700/50">
                    <div class="flex justify-between items-center pt-2">
                        <span class="text-sm font-bold text-white">GRAND TOTAL</span>
                        <span class="text-xl font-bold gradient-text">Rp {{ number_format($booking->grand_total, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function sparepartForm() {
    return {
        showForm: false,
        selectedSparepart: '',
        quantity: 1,
        unitPrice: 0,
        maxStock: 999,

        updatePrice() {
            const select = document.querySelector('select[name="sparepart_id"]');
            const option = select.options[select.selectedIndex];
            if (option && option.dataset.price) {
                this.unitPrice = parseFloat(option.dataset.price);
                this.maxStock = parseInt(option.dataset.stock);
                this.quantity = 1;
            } else {
                this.unitPrice = 0;
                this.maxStock = 999;
            }
        }
    }
}
</script>
@endsection
