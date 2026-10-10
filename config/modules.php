<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Module & table-prefix registry (KONSEP.md §A1)
|--------------------------------------------------------------------------
|
| One prefix = one owner module. Only the owner may create, alter or query
| tables with that prefix; other modules go through the owner's Contract,
| events, the Ledger or the PaymentGateway. `php artisan arch:scan` (rule A2)
| reports every table access that does not match this registry, so this file
| describes the TARGET ownership, not the current state: e.g. `oto_` belongs to
| the planned Mobility module (merge of Telematics/Ev/Fleet) and `gov_` to the
| planned Governance module, therefore today's writers are reported until
| PROGRESS R4.2/R4.4 move them. Changing an owner requires a DECISIONS.md entry.
|
*/

return [

    'table_prefixes' => [
        'agy_' => 'Agency',
        'agri_' => 'Agri',
        'ast_' => 'Asset',
        'serve_' => 'AutoServe',
        'dex_' => 'AutoDex',
        'b2b_' => 'B2b',
        'bank_' => 'Banking',
        'ckt_' => 'CloudKitchen',
        'ctr_' => 'Contract',
        'sct_' => 'ControlTower',
        'core_' => 'Core',
        'sim_' => 'Core',
        'platform_' => 'Core',
        'role_' => 'Core',
        'user_' => 'Core',
        'crypto_' => 'Crypto',
        'dist_' => 'Distribution',
        'edu_' => 'Edu',
        'egy_' => 'Egy',
        'ef_' => 'EnterpriseFinance',
        'epc_' => 'Epc',
        'esg_' => 'Esg',
        'fin_' => 'Finance',
        'gov_' => 'Governance',
        'hcm_' => 'Hcm',
        'hsp_' => 'Hospital',
        'htl_' => 'Hotel',
        'ins_' => 'Insurance',
        'intg_' => 'Integration',
        'ic_' => 'Intercompany',
        'intl_' => 'International',
        'inv_' => 'Inventory',
        'lgx_' => 'Logistics',
        'mall_' => 'Mall',
        'mfg_' => 'Manufacturing',
        'med_' => 'Med',
        'min_' => 'Mining',
        'oto_' => 'Mobility',
        'ptn_' => 'Partner',
        'pty_' => 'Party',
        'pay_' => 'Payment',
        'plm_' => 'Plm',
        'pric_' => 'Pricing',
        'prc_' => 'Procurement',
        'prp_' => 'Proptech',
        'resto_' => 'Resto',
        'ret_' => 'Ret',
        'rwa_' => 'Rwa',
        'store_' => 'Store',
        'sup_' => 'Supplier',
        'tlx_' => 'Tlx',
        'trd_' => 'Trade',
        'tf_' => 'TradeFinance',
        'trs_' => 'Treasury',
        'vnd_' => 'Vending',
        'ven_' => 'Venue',
        'wm_' => 'Wealth',
        'wms_' => 'Wms',
    ],

    /*
    | Tables without a module prefix (framework tables and pre-modular legacy tables).
    */
    'legacy_tables' => [
        'users' => 'App',
        'password_reset_tokens' => 'App',
        'sessions' => 'App',
        'cache' => 'App',
        'cache_locks' => 'App',
        'jobs' => 'App',
        'job_batches' => 'App',
        'failed_jobs' => 'App',
        'personal_access_tokens' => 'App',
        'migrations' => 'App',
        'bookings' => 'AutoServe',
        'booking_sparepart' => 'AutoServe',
        'spareparts' => 'AutoServe',
        'services' => 'AutoServe',
        'cars' => 'AutoDex',
        'brands' => 'AutoDex',
        'garages' => 'AutoDex',
        'wishlists' => 'AutoDex',
        'roles' => 'Core',
        'permissions' => 'Core',
    ],

    /*
    | Modules that form the platform kernel; they must not depend on business modules (rule A9).
    */
    'kernel_modules' => ['Shared', 'Core'],

    /*
    | Namespaces every module may import even though they live in another module's Domain
    | (rule A1 exceptions). Empty until the ledger kernel moves to a shared namespace (R1/R4).
    */
    'shared_kernel_namespaces' => [],

    /*
    | `table.column` => reason, for `*_id` columns that intentionally reference another
    | module's table without a foreign key (rule A12 exceptions).
    */
    'cross_module_columns' => [],

];
