<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">Langganan Parkir Bulanan</h2>
            <p class="text-sm text-slate-500 mt-1">
                Kendaraan diambil dari My Garage, perpanjangan otomatis didebit dari dompet dengan pengingat H-3
            </p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- Form pendaftaran --}}
                <div class="lg:col-span-5 bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="font-bold text-slate-800 mb-1">Daftar Langganan Baru</h3>
                    <p class="text-xs text-slate-500 mb-4">
                        Rp {{ number_format($monthlyPrice, 0, ',', '.') }} / bulan per kendaraan, bebas biaya parkir
                        di seluruh zona {{ $property->name }}.
                    </p>

                    @if($garageVehicles->isEmpty())
                        <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 text-xs">
                            Belum ada kendaraan berplat nomor di My Garage.
                            <a href="{{ route('autodex.index') }}" class="font-bold underline">Tambahkan kendaraan</a>
                            terlebih dahulu.
                        </div>
                    @else
                        <form action="{{ route('mall.parking.members.store') }}" method="POST" class="space-y-4">
                            @csrf
                            <input type="hidden" name="property_id" value="{{ $property->id }}">

                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1.5">Kendaraan dari My Garage</label>
                                <select name="vehicle_id" required
                                        class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    @foreach($garageVehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>
                                            {{ $vehicle->plate_number }} — {{ $vehicle->car?->full_name ?? 'Kendaraan' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('vehicle_id')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1.5">Durasi</label>
                                <select name="months" class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    @foreach([1, 3, 6, 12] as $m)
                                        <option value="{{ $m }}" @selected(old('months', 1) == $m)>
                                            {{ $m }} bulan — Rp {{ number_format($monthlyPrice * $m, 0, ',', '.') }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('months')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                            </div>

                            <label class="flex items-center gap-2 text-xs text-slate-600">
                                <input type="checkbox" name="auto_renew" value="1" checked
                                       class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                Perpanjang otomatis saat jatuh tempo
                            </label>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1.5">PIN Dompet (6 digit)</label>
                                <input type="password" name="pin" inputmode="numeric" maxlength="6" required
                                       class="w-full rounded-xl border-slate-300 text-lg font-mono tracking-[0.5em] focus:border-blue-500 focus:ring-blue-500">
                                @error('pin')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                            </div>

                            <button type="submit"
                                    class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-sm font-bold shadow-md shadow-blue-500/20 transition-all">
                                Bayar & Aktifkan Langganan
                            </button>
                        </form>
                    @endif
                </div>

                {{-- Daftar langganan --}}
                <div class="lg:col-span-7 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200">
                        <h3 class="font-bold text-slate-800">
                            {{ $isStaff ? 'Seluruh Langganan Member' : 'Langganan Saya' }}
                        </h3>
                    </div>

                    @if($members->isEmpty())
                        <p class="px-6 py-10 text-sm text-slate-400 text-center">Belum ada langganan parkir terdaftar.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="text-left px-6 py-3 font-bold">Nomor Member</th>
                                        <th class="text-left px-4 py-3 font-bold">Plat</th>
                                        @if($isStaff)
                                            <th class="text-left px-4 py-3 font-bold">Pemilik</th>
                                        @endif
                                        <th class="text-left px-4 py-3 font-bold">Berlaku s/d</th>
                                        <th class="text-right px-4 py-3 font-bold">Tarif</th>
                                        <th class="text-center px-6 py-3 font-bold">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($members as $member)
                                        @php $daysLeft = $member->daysRemaining(); @endphp
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-6 py-3 font-mono text-slate-600">{{ $member->member_number }}</td>
                                            <td class="px-4 py-3 font-mono font-bold text-slate-800">{{ $member->plate_number }}</td>
                                            @if($isStaff)
                                                <td class="px-4 py-3 text-slate-600">{{ $member->user?->name ?? '—' }}</td>
                                            @endif
                                            <td class="px-4 py-3 text-slate-600">
                                                {{ $member->end_date->format('d/m/Y') }}
                                                @if($member->isValid() && $daysLeft <= 7)
                                                    <span class="block text-[10px] text-amber-600 font-bold">
                                                        {{ $daysLeft }} hari lagi
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right font-mono text-slate-700">
                                                {{ $member->formatted_price }}
                                                <span class="block text-[9px] text-slate-400">
                                                    {{ $member->auto_renew ? 'auto-renew' : 'manual' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-3 text-center">
                                                @php
                                                    $badge = $member->isValid()
                                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                        : 'bg-slate-100 text-slate-500 border-slate-200';
                                                @endphp
                                                <span class="px-2 py-0.5 rounded-full border text-[10px] font-bold {{ $badge }}">
                                                    {{ $member->status->label() }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-4 border-t border-slate-200">
                            {{ $members->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
