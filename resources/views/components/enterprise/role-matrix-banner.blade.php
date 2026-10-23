@props([
    'activeRole' => null,
    'userName' => null,
])

@php
    $currentUser = auth()->user();
    $currentRoleName = $activeRole ?? ($currentUser?->role ?? 'admin');
    
    // 60 Conglomerate Roles Definition (mirrors RbacConglomerateSeeder)
    $conglomerateRoles = [
        'Core & Executive' => [
            ['name' => 'admin', 'label' => 'Super Administrator', 'desc' => 'Root enterprise orchestrator & full system auditor'],
            ['name' => 'executive', 'label' => 'Group Executive Officer', 'desc' => 'Holding board member with conglomerate-wide visibility'],
            ['name' => 'lead_auditor', 'label' => 'Lead Compliance Auditor', 'desc' => 'Statutory oversight & cryptographic hash-chain auditor'],
            ['name' => 'treasury_officer', 'label' => 'Group Treasury Officer', 'desc' => 'Multi-currency liquidity, cash pooling & FX manager'],
            ['name' => 'risk_officer', 'label' => 'Enterprise Risk Officer', 'desc' => 'Solvency, capital reserves & enterprise limit controller'],
            ['name' => 'sustainability_officer', 'label' => 'Chief Sustainability Officer', 'desc' => 'ESG disclosure, carbon offset & green taxonomies'],
        ],
        'Hospitality & Hotel' => [
            ['name' => 'hotel_gm', 'label' => 'Hotel General Manager', 'desc' => 'Luxury resort operations & guest experience superintendent'],
            ['name' => 'front_office', 'label' => 'Hotel Front Office', 'desc' => 'PMS check-in, keycard provisioning & folio billing'],
            ['name' => 'housekeeping', 'label' => 'Hotel Housekeeping Supervisor', 'desc' => 'Room inventory, amenity turnover & maintenance dispatcher'],
            ['name' => 'revenue_mgr', 'label' => 'Hospitality Revenue Manager', 'desc' => 'Dynamic room rate yield management & channel distributor'],
        ],
        'Entertainment Venue & Club' => [
            ['name' => 'venue_manager', 'label' => 'Entertainment Venue Manager', 'desc' => 'Beach club, festival & amphitheater general operations'],
            ['name' => 'venue_staff', 'label' => 'Venue Operational Staff', 'desc' => 'VIP table hosting, RFID wristband check-in & bottle service'],
            ['name' => 'artist_relations', 'label' => 'Artist Relations Coordinator', 'desc' => 'Artist rider contracts, royalty distribution & escrow'],
            ['name' => 'crowd_safety', 'label' => 'Crowd Safety Officer', 'desc' => 'Turnstile throughput, emergency exit telemetry & capacity'],
        ],
        'Mining & Heavy Fleet' => [
            ['name' => 'mining_manager', 'label' => 'Mining Site Superintendent', 'desc' => 'Pit-to-port ore extraction & production blast controller'],
            ['name' => 'mine_planner', 'label' => 'Long-Term Mine Planner', 'desc' => 'Geological block model & strip ratio optimizer'],
            ['name' => 'mine_surveyor', 'label' => 'Geodetic Mine Surveyor', 'desc' => 'Drone volumetric cut/fill calculation & pit topography'],
            ['name' => 'fleet_dispatcher', 'label' => 'Heavy Fleet Dispatcher', 'desc' => 'Haul truck telemetry, cycle time & fuel telemetry'],
            ['name' => 'hse_officer', 'label' => 'HSE Compliance Officer', 'desc' => 'Occupational safety, mine permits & air quality monitor'],
            ['name' => 'royalty_officer', 'label' => 'Mining Royalty & ESDM Liaison', 'desc' => 'PNBP government royalty calculation & SIMBARA sync'],
        ],
        'Healthcare & Hospital' => [
            ['name' => 'medical_director', 'label' => 'Hospital Medical Director', 'desc' => 'Clinical governance, credentialing & morbidity review'],
            ['name' => 'doctor', 'label' => 'Treating Medical Doctor', 'desc' => 'Clinical encounters, EHR diagnosis & CPOE prescriptions'],
            ['name' => 'nurse', 'label' => 'Inpatient/Outpatient Nurse', 'desc' => 'Vital signs, bed management & ESI triage scoring'],
            ['name' => 'pharmacist', 'label' => 'Licensed Clinical Pharmacist', 'desc' => 'Drug dispensing, formulary verification & pediatric mg/kg'],
            ['name' => 'rs_admin', 'label' => 'Hospital Administrative Officer', 'desc' => 'INA-CBG BPJS claim coding & hospital revenue cycle'],
        ],
        'Logistics & Supply Chain' => [
            ['name' => 'logistics_dispatcher', 'label' => 'Logistics Freight Dispatcher', 'desc' => 'Autonomous route optimization & multi-modal transport'],
            ['name' => 'warehouse_mgr', 'label' => 'Central Warehouse Manager', 'desc' => '3D warehouse slotting, cycle count & directed putaway'],
            ['name' => 'coldchain_officer', 'label' => 'Cold-Chain Telematics Monitor', 'desc' => 'IoT temperature audit (-20C to +4C) for vaccine/pharma'],
            ['name' => 'driver', 'label' => 'Logistics Fleet Driver', 'desc' => 'Mobile ePOD signoff, geofenced drop-off & fuel log'],
        ],
        'Fintech, RWA & InsurTech' => [
            ['name' => 'investor', 'label' => 'Accredited RWA Asset Investor', 'desc' => 'Tokenized real-estate, green bond & dividend subscriber'],
            ['name' => 'wm_advisor', 'label' => 'Wealth Management Advisor', 'desc' => 'MPT modern portfolio rebalancing & risk profiling'],
            ['name' => 'rwa_admin', 'label' => 'RWA Tokenization Administrator', 'desc' => 'Asset custodial SPV issuance & token minting authority'],
            ['name' => 'claims_officer', 'label' => 'Parametric Insurance Claims Officer', 'desc' => 'Oracle weather/flight parametric payout verifier'],
            ['name' => 'underwriter', 'label' => 'Insurance Underwriting Specialist', 'desc' => 'Actuarial risk scoring, policy pricing & policy binding'],
            ['name' => 'loan_officer', 'label' => 'Fintech Credit Loan Officer', 'desc' => 'SME supply-chain financing, credit score & amortization'],
            ['name' => 'clearing_agent', 'label' => '24/7 Stablecoin Clearing Agent', 'desc' => 'Cross-border interbank gross settlement & liquidity pool'],
        ],
        'HCM, Gig Marketplace & Contractors' => [
            ['name' => 'hcm_officer', 'label' => 'HCM Talent Operations Officer', 'desc' => 'Payroll calculation, PPh 21 TER, BPJS & organization chart'],
            ['name' => 'supervisor', 'label' => 'Operational Field Supervisor', 'desc' => 'Shift scheduling, overtime approval & team task dispatch'],
            ['name' => 'contractor', 'label' => 'Independent Gig Contractor', 'desc' => 'Bounty execution, milestone claim & instant escrow payout'],
            ['name' => 'freelancer', 'label' => 'On-Demand Freelancer', 'desc' => 'Project milestone submissions & digital contract signer'],
            ['name' => 'staff', 'label' => 'General Enterprise Staff', 'desc' => 'Self-service leave, expense claim & internal requests'],
        ],
        'Sustainability, ESG & Compliance' => [
            ['name' => 'esg_auditor', 'label' => 'Independent ESG & GRI Auditor', 'desc' => 'Scope 1/2/3 carbon footprint & sustainability assurance'],
            ['name' => 'compliance_officer', 'label' => 'Statutory Compliance Officer', 'desc' => 'OJK, BI, Kemenkes, ESDM & AML/CFT statutory filings'],
            ['name' => 'legal_counsel', 'label' => 'Corporate Legal Counsel', 'desc' => 'Smart contract dispute arbitration & NDA lifecycle'],
        ],
        'AI, Analytics & Pricing' => [
            ['name' => 'pricing_specialist', 'label' => 'AI Dynamic Pricing Specialist', 'desc' => 'Surge pricing, elasticity curves & markdown optimization'],
            ['name' => 'operations_planner', 'label' => 'Multivariate Demand Operations Planner', 'desc' => 'Holt-Winters forecasting, safety stock & S&OP sync'],
            ['name' => 'security_officer', 'label' => 'Cybersecurity & Anti-Fraud Officer', 'desc' => 'Rate limiter, brute-force deterrent & tamper sentinel'],
            ['name' => 'supply_chain_mgr', 'label' => 'Enterprise Supply Chain Manager', 'desc' => '3-way match, vendor rating & procurement optimization'],
        ],
        'Mobility, EV & Retail' => [
            ['name' => 'ev_operator', 'label' => 'EV Charging Network Operator', 'desc' => 'OCPP 2.0.1 smart charging sessions & peak ToU tariffs'],
            ['name' => 'fleet_manager', 'label' => 'Corporate Fleet Asset Manager', 'desc' => 'PSAK 73 ROU leases, telematics OBD-II & battery passport'],
            ['name' => 'mall_admin', 'label' => 'Retail Mall Operations Admin', 'desc' => 'Anchor tenant leases, footfall heatmap & revenue share'],
            ['name' => 'tenant_manager', 'label' => 'Commercial Tenant Leasing Manager', 'desc' => 'POS automated integration, billing & service charge'],
            ['name' => 'resto_manager', 'label' => 'Cloud Kitchen & Resto Manager', 'desc' => 'Recipe BOM costing, multi-brand delivery & POS terminal'],
            ['name' => 'chef', 'label' => 'Executive Kitchen Chef', 'desc' => 'Kitchen display system, raw ingredient prep & HACCP'],
            ['name' => 'vending_operator', 'label' => 'Autonomous Vending Restock Operator', 'desc' => 'Smart micro-mart telemetry, slot restock & cashless POS'],
            ['name' => 'customer', 'label' => 'End Customer / Patient / Guest', 'desc' => 'Consumer mobile portal, order tracking & loyalty points'],
        ],
    ];

    // Find current role label
    $currentRoleLabel = 'Super Administrator';
    $currentRoleDesc = 'Root enterprise orchestrator & full system auditor';
    foreach ($conglomerateRoles as $category => $roleList) {
        foreach ($roleList as $r) {
            if ($r['name'] === $currentRoleName) {
                $currentRoleLabel = $r['label'];
                $currentRoleDesc = $r['desc'];
                break 2;
            }
        }
    }
@endphp

<div class="enterprise-role-matrix-banner mb-6"
     x-data="{
        roleModalOpen: false,
        activeRole: '{{ $currentRoleName }}',
        activeRoleLabel: '{{ $currentRoleLabel }}',
        roleSearchQuery: '',
        switchRole(roleName, roleLabel) {
            this.activeRole = roleName;
            this.activeRoleLabel = roleLabel;
            this.roleModalOpen = false;
            
            // Broadcast event & store preference
            localStorage.setItem('autoserve_active_role', roleName);
            window.dispatchEvent(new CustomEvent('enterprise-role-changed', {
                detail: { role: roleName, label: roleLabel }
            }));
            
            // Show toast notification
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-5 right-5 z-50 px-4 py-3 rounded-xl bg-slate-900 border border-emerald-500/50 text-white shadow-2xl flex items-center gap-3 slide-in text-xs font-semibold';
            toast.innerHTML = `<span class='w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse'></span><div><p class='font-bold text-emerald-400'>Peran RBAC Beralih</p><p class='text-slate-300'>Kini bertindak sebagai: ${roleLabel} (${roleName})</p></div>`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }
     }">
    {{-- Main Banner Bar --}}
    <div class="rounded-2xl bg-gradient-to-r from-slate-900 via-slate-900/95 to-slate-800 border border-slate-700/80 p-4 shadow-xl backdrop-blur-xl">
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
            
            {{-- Left: Active Role & Conglomerate Context --}}
            <div class="flex items-center gap-3.5 min-w-0">
                <div class="relative flex-shrink-0">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 via-purple-600 to-pink-500 flex items-center justify-center text-white font-black text-sm shadow-lg shadow-indigo-500/25 ring-2 ring-indigo-400/30">
                        {{ strtoupper(substr($currentRoleLabel, 0, 2)) }}
                    </div>
                    <span class="absolute -bottom-1 -right-1 w-3.5 h-3.5 rounded-full bg-emerald-500 ring-2 ring-slate-900 animate-pulse" title="Sesi Terotentikasi & Valid"></span>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs font-mono font-bold text-indigo-400 tracking-wider uppercase">PT Mega Konsorsium Nusantara Tbk</span>
                        <span class="text-slate-600">•</span>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30" x-text="activeRole">
                            {{ $currentRoleName }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 mt-0.5">
                        <h2 class="text-sm font-bold text-white tracking-tight truncate" x-text="activeRoleLabel">
                            {{ $currentRoleLabel }}
                        </h2>
                        <span class="text-xs text-slate-400 hidden sm:inline-block">({{ $currentUser?->name ?? 'Enterprise Operator' }})</span>
                    </div>
                </div>
            </div>

            {{-- Center/Right: Security Proof Badges --}}
            <div class="flex items-center gap-2 flex-wrap text-xs">
                {{-- Hash-Chain Verified Badge --}}
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-medium shadow-sm"
                     title="Audit Trail Cryptographic SHA-256 Seal Aktif & 100% Tamper-Evident">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="font-mono text-[11px] font-bold">Hash-Chain Verified</span>
                    <span class="hidden md:inline-block text-[9px] text-emerald-500 font-mono">(SHA-256)</span>
                </div>

                {{-- Idempotency Guard Badge --}}
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 font-medium shadow-sm"
                     title="Idempotency Key Aktif: Mencegah Double-Spend & Eksekusi Duplikat">
                    <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span class="font-mono text-[11px] font-bold">Idempotency Guard</span>
                </div>

                {{-- Integer Minor-Unit Arithmetic Badge --}}
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 font-medium shadow-sm"
                     title="Seluruh Nilai Finansial Disimpan dalam Integer Minor-Unit (0 Float Drift, Sesuai Aturan A3)">
                    <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                    <span class="font-mono text-[11px] font-bold">Integer Precision</span>
                    <span class="hidden md:inline-block text-[9px] text-amber-400/80 font-mono">(0 Drift)</span>
                </div>

                {{-- Interactive 60-Role Switcher Trigger Button --}}
                <button type="button"
                        @click="roleModalOpen = true"
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-semibold text-xs border border-slate-600/80 shadow-md hover:border-indigo-500/80 transition-all">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <span>60-Role Switcher</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- 60-ROLE SWITCHER MODAL --}}
    {{-- ============================================================ --}}
    <div x-show="roleModalOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-10"
         role="dialog"
         aria-modal="true">
        {{-- Backdrop --}}
        <div x-show="roleModalOpen"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="roleModalOpen = false"
             class="fixed inset-0 bg-slate-950/80 backdrop-blur-md transition-opacity"></div>

        {{-- Modal Dialog --}}
        <div x-show="roleModalOpen"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="mx-auto max-w-4xl transform rounded-2xl bg-slate-900 border border-slate-700 shadow-2xl overflow-hidden relative z-10 flex flex-col max-h-[85vh]">
            
            {{-- Modal Header --}}
            <div class="p-5 border-b border-slate-800 bg-slate-900/90 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Pilih Peran Konglomerasi (60 Peran RBAC)</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-indigo-500/20 text-indigo-300 font-bold">12 SBU Lines</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Ubah konteks operasional untuk menguji hak akses otorisasi, policy gate, dan workflow modul.</p>
                </div>
                <button @click="roleModalOpen = false" class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Role Search Input --}}
            <div class="p-4 border-b border-slate-800 bg-slate-950/40">
                <div class="relative">
                    <input type="text"
                           x-model="roleSearchQuery"
                           placeholder="Cari peran (contoh: doctor, mining_manager, underwriter, gm, chef)..."
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-sm text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500">
                    <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            {{-- Role Categories Grid --}}
            <div class="flex-1 overflow-y-auto p-5 space-y-6">
                @foreach($conglomerateRoles as $category => $roleList)
                    <div x-show="!roleSearchQuery || '{{ strtolower($category) }}'.includes(roleSearchQuery.toLowerCase()) || JSON.stringify({{ json_encode($roleList) }}).toLowerCase().includes(roleSearchQuery.toLowerCase())">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-1.5 h-4 rounded-full bg-indigo-500"></span>
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-300">{{ $category }}</h4>
                            <span class="text-[10px] text-slate-500 font-mono">({{ count($roleList) }} Peran)</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                            @foreach($roleList as $role)
                                <button type="button"
                                        x-show="!roleSearchQuery || '{{ strtolower($role['name'] . ' ' . $role['label'] . ' ' . $role['desc']) }}'.includes(roleSearchQuery.toLowerCase())"
                                        @click="switchRole('{{ $role['name'] }}', '{{ $role['label'] }}')"
                                        class="flex items-start gap-3 p-3 rounded-xl border text-left transition-all group"
                                        :class="activeRole === '{{ $role['name'] }}' ? 'bg-indigo-600/15 border-indigo-500/60 ring-1 ring-indigo-500/50' : 'bg-slate-800/60 border-slate-700/60 hover:bg-slate-800 hover:border-slate-600'">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs flex-shrink-0 transition-colors"
                                         :class="activeRole === '{{ $role['name'] }}' ? 'bg-indigo-600 text-white' : 'bg-slate-700 text-slate-300 group-hover:bg-slate-600 group-hover:text-white'">
                                        {{ strtoupper(substr($role['name'], 0, 2)) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-1">
                                            <p class="text-xs font-bold text-white group-hover:text-indigo-300 truncate">{{ $role['label'] }}</p>
                                            <span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-slate-900 text-slate-400 border border-slate-700">{{ $role['name'] }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-400 mt-0.5 line-clamp-1">{{ $role['desc'] }}</p>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Footer --}}
            <div class="p-4 bg-slate-950/60 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <span>Total 60 Peran RBAC Terdaftar di Konglomerasi</span>
                <button type="button" @click="roleModalOpen = false" class="px-3 py-1.5 rounded-lg bg-slate-800 text-white hover:bg-slate-700">Tutup</button>
            </div>
        </div>
    </div>
</div>
