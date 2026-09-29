@extends('layouts.app')

@section('title', 'Buat Booking Baru')
@section('subtitle', 'Jadwalkan servis kendaraan Anda')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="glass-card rounded-2xl overflow-hidden">
        {{-- Header --}}
        <div class="px-6 py-5 border-b border-slate-700/50 bg-gradient-to-r from-blue-500/5 to-violet-500/5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-white">Form Booking Servis</h2>
                    <p class="text-xs text-slate-400">Isi data kendaraan dan pilih jenis servis</p>
                </div>
            </div>
        </div>

        {{-- Multi-Step Form with Alpine.js --}}
        <form action="{{ route('bookings.store') }}" method="POST" x-data="bookingForm()" class="p-6">
            @csrf

            {{-- Step Indicator --}}
            <div class="flex items-center justify-center gap-4 mb-8">
                <template x-for="(label, idx) in ['Kendaraan', 'Servis', 'Jadwal']" :key="idx">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all"
                             :class="step > idx + 1 ? 'bg-emerald-500 text-white' : (step === idx + 1 ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30' : 'bg-slate-700 text-slate-400')">
                            <template x-if="step > idx + 1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </template>
                            <template x-if="step <= idx + 1">
                                <span x-text="idx + 1"></span>
                            </template>
                        </div>
                        <span class="text-xs font-medium" :class="step === idx + 1 ? 'text-blue-400' : 'text-slate-500'" x-text="label"></span>
                        <template x-if="idx < 2">
                            <div class="w-8 h-px bg-slate-700"></div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- STEP 1: Data Kendaraan --}}
            <div x-show="step === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Plat Nomor *</label>
                            <input type="text" name="plate_number" x-model="form.plate_number" required
                                   class="w-full px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all text-sm"
                                   placeholder="B 1234 XYZ" value="{{ old('plate_number') }}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Merk Kendaraan *</label>
                            <input type="text" name="vehicle_brand" x-model="form.vehicle_brand" required
                                   class="w-full px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all text-sm"
                                   placeholder="Toyota" value="{{ old('vehicle_brand') }}">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Model</label>
                            <input type="text" name="vehicle_model"
                                   class="w-full px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all text-sm"
                                   placeholder="Avanza" value="{{ old('vehicle_model') }}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Tahun</label>
                            <input type="number" name="vehicle_year"
                                   class="w-full px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all text-sm"
                                   placeholder="2022" min="1900" max="{{ date('Y') + 1 }}" value="{{ old('vehicle_year') }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- STEP 2: Pilih Servis & Keluhan --}}
            <div x-show="step === 2" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-2">Jenis Servis *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($services as $service)
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="service_id" value="{{ $service->id }}" x-model="form.service_id" class="sr-only peer" {{ old('service_id') == $service->id ? 'checked' : '' }}>
                                <div class="p-4 rounded-xl border border-slate-700 bg-slate-800/30 peer-checked:border-blue-500 peer-checked:bg-blue-500/10 hover:border-slate-600 transition-all">
                                    <p class="font-medium text-white text-sm">{{ $service->name }}</p>
                                    <p class="text-xs text-slate-400 mt-1">{{ $service->description }}</p>
                                    <p class="text-sm font-bold text-blue-400 mt-2">Rp {{ number_format($service->price, 0, ',', '.') }}</p>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Keluhan *</label>
                        <textarea name="complaint" x-model="form.complaint" rows="4" required
                                  class="w-full px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all text-sm resize-none"
                                  placeholder="Jelaskan keluhan atau masalah kendaraan Anda...">{{ old('complaint') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- STEP 3: Jadwal --}}
            <div x-show="step === 3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Tanggal Booking *</label>
                            <input type="date" name="booking_date" x-model="form.booking_date" required
                                   min="{{ date('Y-m-d') }}"
                                   class="w-full px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all text-sm"
                                   value="{{ old('booking_date') }}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">Jam (Opsional)</label>
                            <input type="time" name="booking_time"
                                   class="w-full px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all text-sm"
                                   value="{{ old('booking_time') }}">
                        </div>
                    </div>

                    {{-- Summary Preview --}}
                    <div class="p-4 rounded-xl bg-slate-800/30 border border-slate-700/50 mt-4">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-3">Ringkasan Booking</p>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Kendaraan</span>
                                <span class="text-white font-medium" x-text="form.vehicle_brand + ' ' + (form.plate_number || '')"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Tanggal</span>
                                <span class="text-white font-medium" x-text="form.booking_date || '-'"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Navigation Buttons --}}
            <div class="flex items-center justify-between mt-8 pt-6 border-t border-slate-700/50">
                <button type="button" @click="step > 1 ? step-- : null" x-show="step > 1"
                        class="px-5 py-2.5 rounded-xl text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-700/50 transition-all">
                    ← Sebelumnya
                </button>
                <div x-show="step === 1"></div>

                <button type="button" @click="step++" x-show="step < 3"
                        class="px-6 py-2.5 rounded-xl text-sm font-medium bg-blue-500 hover:bg-blue-600 text-white transition-all shadow-lg shadow-blue-500/25">
                    Selanjutnya →
                </button>

                <button type="submit" x-show="step === 3"
                        class="px-6 py-2.5 rounded-xl text-sm font-medium bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white transition-all shadow-lg shadow-emerald-500/25">
                    ✓ Kirim Booking
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function bookingForm() {
    return {
        step: 1,
        form: {
            plate_number: '{{ old("plate_number") }}',
            vehicle_brand: '{{ old("vehicle_brand") }}',
            service_id: '{{ old("service_id") }}',
            complaint: '{{ old("complaint") }}',
            booking_date: '{{ old("booking_date") }}',
        }
    }
}
</script>
@endsection
