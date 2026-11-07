<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Telemetri Perangkat Turnstile & Pintu Gerbang IoT') }}
            </h2>
            <div class="flex space-x-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    Modbus RTU & TCP/IP Online
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Status Gerbang Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <span class="font-bold text-gray-800 text-sm">Lajur Gerbang Utama (L1–L4)</span>
                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-700">NORMAL</span>
                </div>
                <div class="space-y-2 text-xs text-gray-600">
                    <div class="flex justify-between"><span>Throughput:</span><span class="font-mono font-bold">18 orang/menit</span></div>
                    <div class="flex justify-between"><span>Tegangan Solenoid:</span><span class="font-mono">24.1 VDC</span></div>
                    <div class="flex justify-between"><span>Optical Barrier:</span><span class="text-emerald-600 font-semibold">Bebas Halangan</span></div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <span class="font-bold text-gray-800 text-sm">Lajur Gerbang VIP (V1–V2)</span>
                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-700">OPTIMAL</span>
                </div>
                <div class="space-y-2 text-xs text-gray-600">
                    <div class="flex justify-between"><span>Throughput:</span><span class="font-mono font-bold">4 orang/menit</span></div>
                    <div class="flex justify-between"><span>Kecepatan Solenoid:</span><span class="font-mono">1.800 ms</span></div>
                    <div class="flex justify-between"><span>Validasi RFID:</span><span class="text-emerald-600 font-semibold">100% Granted</span></div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <span class="font-bold text-gray-800 text-sm">Pintu Darurat Egress (E1–E2)</span>
                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-indigo-100 text-indigo-700">STANDBY INTERLOCKED</span>
                </div>
                <div class="space-y-2 text-xs text-gray-600">
                    <div class="flex justify-between"><span>Mode Rel Egress:</span><span class="font-mono font-bold">Magnetic Hold</span></div>
                    <div class="flex justify-between"><span>Fail-Safe Egress:</span><span class="text-indigo-600 font-semibold">Siaga Rilis Otomatis</span></div>
                    <div class="flex justify-between"><span>Heartbeat Terakhir:</span><span class="font-mono">3 detik lalu</span></div>
                </div>
            </div>
        </div>

        <!-- Tombol Uji & Kontrol Darurat -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-bold text-gray-800 mb-2">Simulasi Interlock Protokol Evakuasi & Override Gerbang</h3>
            <p class="text-xs text-gray-500 mb-4">Dalam kondisi darurat kerumunan (crowd crush alarm), sistem akan melepaskan interlock elektromagnetik agar seluruh turnstile dapat berputar bebas ke arah luar.</p>
            <div class="flex space-x-4">
                <button type="button" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm font-semibold shadow-sm">
                    Aktifkan Free-Spin Egress Seluruh Turnstile
                </button>
                <button type="button" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-semibold border border-slate-300">
                    Kirim Pulse Ping Uji Heartbeat
                </button>
            </div>
        </div>
    </div>
</x-app-layout>
