<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Domain\Models\Role;

class RbacConglomerateSeeder extends Seeder
{
    /**
     * Seed all 60+ conglomerate roles across the 12 business lines
     */
    public function run(): void
    {
        $roles = [
            // Core & Executive
            ['name' => 'admin', 'label' => 'Super Administrator'],
            ['name' => 'executive', 'label' => 'Group Executive Officer'],
            ['name' => 'lead_auditor', 'label' => 'Lead Compliance Auditor'],
            ['name' => 'treasury_officer', 'label' => 'Group Treasury Officer'],
            ['name' => 'risk_officer', 'label' => 'Enterprise Risk Officer'],
            ['name' => 'sustainability_officer', 'label' => 'Chief Sustainability Officer'],

            // Hospitality & Hotel
            ['name' => 'hotel_gm', 'label' => 'Hotel General Manager'],
            ['name' => 'front_office', 'label' => 'Hotel Front Office'],
            ['name' => 'housekeeping', 'label' => 'Hotel Housekeeping Supervisor'],
            ['name' => 'revenue_mgr', 'label' => 'Hospitality Revenue Manager'],

            // Entertainment Venue & Beach Club
            ['name' => 'venue_manager', 'label' => 'Entertainment Venue Manager'],
            ['name' => 'venue_staff', 'label' => 'Venue Operational Staff'],
            ['name' => 'artist_relations', 'label' => 'Artist Relations Coordinator'],
            ['name' => 'crowd_safety', 'label' => 'Crowd Safety Officer'],

            // Mining & Heavy Fleet
            ['name' => 'mining_manager', 'label' => 'Mining Site Superintendent'],
            ['name' => 'mine_planner', 'label' => 'Long-Term Mine Planner'],
            ['name' => 'mine_surveyor', 'label' => 'Geodetic Mine Surveyor'],
            ['name' => 'fleet_dispatcher', 'label' => 'Heavy Fleet Dispatcher'],
            ['name' => 'hse_officer', 'label' => 'Health Safety & Environment Officer'],
            ['name' => 'royalty_officer', 'label' => 'Mining Royalty & ESDM Liaison'],

            // Healthcare & Hospital
            ['name' => 'medical_director', 'label' => 'Hospital Medical Director'],
            ['name' => 'doctor', 'label' => 'Treating Medical Doctor'],
            ['name' => 'nurse', 'label' => 'Inpatient / Outpatient Nurse'],
            ['name' => 'pharmacist', 'label' => 'Licensed Clinical Pharmacist'],
            ['name' => 'rs_admin', 'label' => 'Hospital Administrative Officer'],

            // Logistics & Supply Chain
            ['name' => 'logistics_dispatcher', 'label' => 'Logistics Freight Dispatcher'],
            ['name' => 'warehouse_mgr', 'label' => 'Central Warehouse Manager'],
            ['name' => 'coldchain_officer', 'label' => 'Cold-Chain Telematics Monitor'],
            ['name' => 'driver', 'label' => 'Logistics Fleet Driver'],

            // Fintech, RWA & InsurTech
            ['name' => 'investor', 'label' => 'Accredited RWA Asset Investor'],
            ['name' => 'wm_advisor', 'label' => 'Wealth Management Advisor'],
            ['name' => 'rwa_admin', 'label' => 'RWA Tokenization Administrator'],
            ['name' => 'claims_officer', 'label' => 'Parametric Insurance Claims Officer'],
            ['name' => 'underwriter', 'label' => 'Insurance Underwriting Specialist'],
            ['name' => 'loan_officer', 'label' => 'Fintech Credit Loan Officer'],
            ['name' => 'clearing_agent', 'label' => '24/7 Stablecoin Clearing Agent'],

            // HCM, Gig Marketplace & Contractors
            ['name' => 'hcm_officer', 'label' => 'HCM Talent Operations Officer'],
            ['name' => 'supervisor', 'label' => 'Operational Field Supervisor'],
            ['name' => 'contractor', 'label' => 'Independent Gig Contractor'],
            ['name' => 'freelancer', 'label' => 'On-Demand Freelancer'],
            ['name' => 'staff', 'label' => 'General Enterprise Staff'],

            // Sustainability, ESG & Compliance
            ['name' => 'esg_auditor', 'label' => 'Independent ESG & GRI Auditor'],
            ['name' => 'compliance_officer', 'label' => 'Statutory Compliance Officer'],
            ['name' => 'legal_counsel', 'label' => 'Corporate Legal Counsel'],

            // AI, Analytics & Pricing
            ['name' => 'pricing_specialist', 'label' => 'AI Dynamic Pricing Specialist'],
            ['name' => 'operations_planner', 'label' => 'Multivariate Demand Operations Planner'],
            ['name' => 'security_officer', 'label' => 'Cybersecurity & Anti-Fraud Officer'],
            ['name' => 'supply_chain_mgr', 'label' => 'Enterprise Supply Chain Manager'],

            // Mobility, EV & Retail
            ['name' => 'ev_operator', 'label' => 'EV Charging Network Operator'],
            ['name' => 'fleet_manager', 'label' => 'Corporate Fleet Asset Manager'],
            ['name' => 'mall_admin', 'label' => 'Retail Mall Operations Admin'],
            ['name' => 'tenant_manager', 'label' => 'Commercial Tenant Leasing Manager'],
            ['name' => 'resto_manager', 'label' => 'Cloud Kitchen & Resto Manager'],
            ['name' => 'chef', 'label' => 'Executive Kitchen Chef'],
            ['name' => 'vending_operator', 'label' => 'Autonomous Vending Restock Operator'],
            ['name' => 'customer', 'label' => 'End Customer / Patient / Guest'],
        ];

        foreach ($roles as $role) {
            Role::query()->firstOrCreate(
                ['name' => $role['name']],
                ['label' => $role['label']]
            );
        }
    }
}
