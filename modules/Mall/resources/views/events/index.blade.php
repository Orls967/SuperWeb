<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Sewa Atrium & Manajemen Event Mall
                </h2>
                <p class="text-sm text-slate-500 mt-1">Pengelolaan area pameran, atrium utama, bazaar booth kuliner, dan kalender kegiatan Duta Mall</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="px-3.5 py-1.5 bg-blue-50 text-blue-700 text-xs font-bold rounded-xl border border-blue-200">
                    Periode: {{ $monthYear }}
                </span>
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

            @if($errors->any())
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Area Event Tersedia</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-2">{{ $spaces->count() }} Lokasi</h3>
                    <p class="text-xs text-slate-500 mt-1">Atrium utama & koridor bazaar</p>
                </div>

                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Event Bulan Ini</p>
                    <h3 class="text-2xl font-black text-indigo-600 mt-2">{{ $bookings->count() }} Kegiatan</h3>
                    <p class="text-xs text-slate-500 mt-1">Terjadwal di kalender</p>
                </div>

                <div class="p-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Pendapatan Sewa</p>
                    <h3 class="text-2xl font-black text-emerald-600 mt-2">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</h3>
                    <p class="text-xs text-slate-500 mt-1">Penerimaan sewa atrium terkonfirmasi</p>
                </div>
            </div>

            <!-- Two Columns: Daftar Area & Form Booking -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Kolom Kiri: Daftar Event Spaces (1 col) -->
                <div class="lg:col-span-1 space-y-4">
                    <h3 class="font-extrabold text-slate-800 text-base">Lokasi & Tarif Area Event</h3>
                    @foreach($spaces as $s)
                        <div class="p-4 bg-white rounded-2xl shadow-sm border border-slate-200 hover:border-blue-400 transition-all">
                            <div class="flex items-center justify-between">
                                <h4 class="font-black text-slate-800 text-sm">{{ $s->name }}</h4>
                                <span class="px-2 py-0.5 text-xs font-bold rounded-md bg-blue-50 text-blue-700 font-mono">{{ $s->code }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">{{ $s->description }}</p>
                            <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                                <span class="text-slate-500">Luas: <strong class="text-slate-700">{{ $s->area_sqm }} m²</strong></span>
                                <span class="text-emerald-600 font-extrabold">Rp {{ number_format($s->daily_rate, 0, ',', '.') }} / hari</span>
                            </div>
                            @if($s->max_booths > 0)
                                <p class="text-xs text-slate-400 mt-1">Kapasitas maksimal: {{ $s->max_booths }} booth bazaar</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Kolom Kanan: Form Booking Baru (2 col) -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2.5 bg-indigo-50 text-indigo-600 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-slate-800 text-lg">Pengajuan Sewa Area Event / Atrium</h3>
                            <p class="text-xs text-slate-500">Sistem otomatis memeriksa bentrok jadwal dengan kegiatan lain</p>
                        </div>
                    </div>

                    <form action="{{ route('mall.events.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Pilih Lokasi Area *</label>
                                <select name="event_space_id" required class="w-full text-sm rounded-xl border-slate-200 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">-- Pilih Area Atrium --</option>
                                    @foreach($spaces as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }} (Rp {{ number_format($s->daily_rate, 0, ',', '.') }}/hari)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Jenis Kegiatan *</label>
                                <select name="event_type" required class="w-full text-sm rounded-xl border-slate-200 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="bazaar">Bazaar & Kuliner</option>
                                    <option value="exhibition">Pameran / Ekshibisi</option>
                                    <option value="concert">Konser & Musik</option>
                                    <option value="product_launch">Peluncuran Produk (Product Launch)</option>
                                    <option value="community">Acara Komunitas</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nama Acara / Event *</label>
                            <input type="text" name="event_name" placeholder="Misal: Duta Mall Culinary Expo 2026" required class="w-full text-sm rounded-xl border-slate-200 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tanggal Mulai *</label>
                                <input type="date" name="start_date" required class="w-full text-sm rounded-xl border-slate-200 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tanggal Selesai *</label>
                                <input type="date" name="end_date" required class="w-full text-sm rounded-xl border-slate-200 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Jumlah Booth (Bazaar)</label>
                                <input type="number" name="booth_count" value="0" min="0" class="w-full text-sm rounded-xl border-slate-200 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Catatan Tambahan</label>
                            <textarea name="notes" rows="2" placeholder="Kebutuhan listrik tambahan, panggung, sound system, dll." class="w-full text-sm rounded-xl border-slate-200 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>

                        <button type="submit" class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-xl text-sm font-bold shadow-md shadow-indigo-500/20 transition-all flex items-center justify-center gap-2">
                            <span>Ajukan Pemesanan Area Event</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Kalender & Jadwal Event Terjadwal -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-extrabold text-slate-800 text-lg">Jadwal Event & Ekshibisi Terdaftar</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Daftar booking sewa area atrium dan panggung pameran</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-400 border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4">Nomor Booking</th>
                                <th class="px-6 py-4">Nama Acara</th>
                                <th class="px-6 py-4">Lokasi Area</th>
                                <th class="px-6 py-4">Penyelenggara</th>
                                <th class="px-6 py-4">Rentang Tanggal</th>
                                <th class="px-6 py-4 text-right">Total Biaya</th>
                                <th class="px-6 py-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($bookings as $b)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4 font-mono font-bold text-slate-800">{{ $b->booking_number }}</td>
                                    <td class="px-6 py-4 font-bold text-slate-900">{{ $b->event_name }} <span class="block text-xs font-normal text-slate-400">{{ $b->event_type->label() }}</span></td>
                                    <td class="px-6 py-4 font-medium">{{ $b->space?->name }}</td>
                                    <td class="px-6 py-4">{{ $b->customer?->name ?? 'Anonim' }}</td>
                                    <td class="px-6 py-4 text-xs font-mono text-slate-500">{{ $b->start_date->format('d M') }} - {{ $b->end_date->format('d M Y') }}</td>
                                    <td class="px-6 py-4 text-right font-bold text-emerald-600">Rp {{ number_format($b->total_amount, 0, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg {{ $b->status->value === 'confirmed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                            {{ $b->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-slate-400">Belum ada booking kegiatan event pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
