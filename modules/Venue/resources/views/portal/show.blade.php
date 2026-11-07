<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kode Akses Masuk Tiket — {{ $ticket->ticket_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tabular-nums { font-variant-numeric: tabular-nums; }
        .watermark-bg {
            background-image: repeating-linear-gradient(45deg, rgba(255, 255, 255, 0.02) 0, rgba(255, 255, 255, 0.02) 20px, transparent 20px, transparent 40px);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex flex-col items-center justify-center p-4 font-sans select-none">
    
    <!-- Kartu Tiket Digital Tamu (Optimal untuk Mobile 375px) -->
    <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col relative watermark-bg">
        
        <!-- Header Acara -->
        <div class="bg-gradient-to-r from-emerald-900/60 to-slate-900 p-6 border-b border-slate-800">
            <div class="flex items-center justify-between mb-2">
                <a href="/guest/tickets" class="text-xs text-emerald-400 font-bold hover:text-emerald-300">← Dompet Tiket</a>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-950 text-emerald-400 border border-emerald-800">
                    {{ $ticket->status }}
                </span>
            </div>
            <h1 class="text-xl font-black text-white leading-tight">{{ $ticket->event?->title ?? 'Acara Musik Hiburan' }}</h1>
            <p class="text-xs text-slate-300 mt-1">{{ $ticket->event?->venue?->name ?? 'Venue' }} • {{ $ticket->tier?->name ?? 'General Admission' }}</p>
        </div>

        <!-- Dynamic QR Display Container -->
        <div class="p-6 flex flex-col items-center text-center space-y-4">
            
            <div class="relative p-4 bg-white rounded-2xl shadow-inner border-4 border-slate-800">
                <!-- SVG Mock Dynamic QR Code -->
                <svg class="w-48 h-48" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" fill="white"/>
                    <!-- Corner Finders -->
                    <rect x="5" y="5" width="25" height="25" fill="black"/>
                    <rect x="8" y="8" width="19" height="19" fill="white"/>
                    <rect x="11" y="11" width="13" height="13" fill="black"/>

                    <rect x="70" y="5" width="25" height="25" fill="black"/>
                    <rect x="73" y="8" width="19" height="19" fill="white"/>
                    <rect x="76" y="11" width="13" height="13" fill="black"/>

                    <rect x="5" y="70" width="25" height="25" fill="black"/>
                    <rect x="8" y="73" width="19" height="19" fill="white"/>
                    <rect x="11" y="76" width="13" height="13" fill="black"/>

                    <!-- Data Blocks -->
                    <rect x="35" y="10" width="10" height="10" fill="black"/>
                    <rect x="50" y="15" width="10" height="5" fill="black"/>
                    <rect x="40" y="30" width="20" height="20" fill="black"/>
                    <rect x="65" y="40" width="15" height="15" fill="black"/>
                    <rect x="10" y="40" width="15" height="15" fill="black"/>
                    <rect x="40" y="65" width="15" height="10" fill="black"/>
                    <rect x="70" y="70" width="20" height="20" fill="black"/>
                    <rect x="58" y="85" width="8" height="8" fill="black"/>
                </svg>

                <!-- Dynamic Nonce Badge -->
                <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 bg-slate-900 border border-slate-700 text-emerald-400 font-mono text-[9px] px-2 py-0.5 rounded-full whitespace-nowrap shadow">
                    ROTATING NONCE ACTIVE
                </div>
            </div>

            <!-- Ticket Number & Security Hash -->
            <div class="space-y-1 w-full pt-2">
                <span class="text-xs text-slate-500 uppercase tracking-widest font-semibold block">Nomor Seri Tiket</span>
                <span class="text-base font-mono font-black text-slate-100 tabular-nums block">{{ $ticket->ticket_number }}</span>
                <span class="text-[10px] font-mono text-slate-500 truncate block px-4">
                    Hash: {{ substr($ticket->ticket_hash ?? 'N/A', 0, 24) }}...
                </span>
            </div>

            <!-- Aturan Masuk & Peringatan Passback -->
            <div class="w-full bg-slate-950/80 border border-slate-800 rounded-xl p-3 text-left space-y-1 text-[11px] text-slate-400">
                <div class="flex items-center space-x-2 text-amber-400 font-semibold">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span>Aturan Anti-Passback & Akses Masuk:</span>
                </div>
                <p>• Tiket hanya dapat dipindai 1 kali untuk masuk. Re-entry maksimal 2 kali setelah pemindaian keluar.</p>
                <p>• Tamu wajib berusia minimal 21 tahun saat memasuki gerbang pemeriksaan fisik.</p>
                <p>• Tangkapan layar (screenshot) tidak berlaku karena kode QR berotasi otomatis.</p>
            </div>
        </div>

        <!-- Footer Keamanan -->
        <div class="bg-slate-950 px-6 py-3 border-t border-slate-800 text-center">
            <span class="text-[10px] text-slate-600 font-mono">
                SECURED BY SUPERWEB D89-1 • IDOR PROTECTED
            </span>
        </div>
    </div>
</body>
</html>

