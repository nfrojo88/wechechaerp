<?php

return [
    /*
    |--------------------------------------------------------------------------
    | General Ledger Posting Feature Flags
    |--------------------------------------------------------------------------
    |
    | Master toggle and per-module granular toggles for double-entry GL posting.
    | When disabled, controllers operate in legacy mode without writing to GL,
    | allowing safe staging rollouts and zero-downtime rollbacks.
    |
    */
    'live_posting_enabled' => env('GL_LIVE_POSTING_ENABLED', false),

    'modules' => [
        'payroll'             => env('GL_POSTING_PAYROLL', true),
        'procurement'         => env('GL_POSTING_PROCUREMENT', true),
        'ar'                  => env('GL_POSTING_AR', true),
        'tax_remittance'      => env('GL_POSTING_TAX_REMITTANCE', true),
        'cash_expenses'       => env('GL_POSTING_CASH_EXPENSES', true),
        'utilities'           => env('GL_POSTING_UTILITIES', true),
        'revenue_recognition' => env('GL_POSTING_REVENUE_RECOGNITION', false), // Off by default
        'ipc_billing'         => env('GL_POSTING_IPC_BILLING', false),         // Off by default
    ],

    /*
    |--------------------------------------------------------------------------
    | Statutory Tax Configuration (Pending Tax Advisor Sign-Off)
    |--------------------------------------------------------------------------
    |
    | All rates and thresholds are marked 'to_be_verified' pending formal
    | written sign-off from the CFO and corporate tax advisor.
    |
    */
    'tax' => [
        'wht_goods' => [
            'standard_rate'    => (float) env('FINANCE_WHT_GOODS_RATE', 0.02),
            'non_tin_rate'     => (float) env('FINANCE_WHT_GOODS_NON_TIN_RATE', 0.30),
            'threshold'        => (float) env('FINANCE_WHT_GOODS_THRESHOLD', 10000.00),
            'threshold_status' => 'to_be_verified',
            'status'           => 'to_be_verified',
            'legal_basis'      => 'Proclamation 979/2016, Art. 92(1)',
        ],
        'wht_services' => [
            'standard_rate'    => (float) env('FINANCE_WHT_SERVICES_RATE', 0.03),
            'non_tin_rate'     => (float) env('FINANCE_WHT_SERVICES_NON_TIN_RATE', 0.30),
            'threshold'        => (float) env('FINANCE_WHT_SERVICES_THRESHOLD', 3000.00),
            'threshold_status' => 'to_be_verified',
            'status'           => 'to_be_verified',
            'legal_basis'      => 'Proclamation 979/2016, Art. 92(2)',
        ],
        'vat' => [
            'standard_rate' => (float) env('FINANCE_VAT_RATE', 0.15),
            'status'        => 'to_be_verified',
            'legal_basis'   => 'Proclamation 285/2002',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Personal Income Tax Brackets (PAYE - Proclamation 979/2016)
    |--------------------------------------------------------------------------
    |
    | Brackets remain flagged as 'to_be_verified' until signed off.
    |
    */
    'paye' => [
        'status' => 'to_be_verified',
        'effective_date' => '2026-07-08',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pension Social Security Agency Targets
    |--------------------------------------------------------------------------
    */
    'pension' => [
        'default_agency'     => env('PENSION_AGENCY_TARGET', 'POESSA'),
        'employee_rate'      => 0.07,
        'employer_rate'      => 0.11,
        'total_statutory'    => 0.18,
    ],

    /*
    |--------------------------------------------------------------------------
    | Construction Contracts & Revenue Recognition Configuration (IFRS 15)
    |--------------------------------------------------------------------------
    |
    | vat_base options:
    |   1. 'net_certified'               - Certified less advance recovery less retention
    |   2. 'full_certified'              - Full gross certified value (unreduced)
    |   3. 'certified_less_advance_only' - Certified less advance recovery (retention not deducted)
    |
    | STRICTLY NO DEFAULT: vat_base must be explicitly configured and signed off.
    | IPC posting is blocked if vat_base is null or status is not signed_off.
    |
    */
    'contracts' => [
        'vat_base'           => env('FINANCE_CONTRACTS_VAT_BASE', null),
        'vat_base_status'    => env('FINANCE_CONTRACTS_VAT_BASE_STATUS', 'to_be_verified'),
        'vat_timing_rule'    => env('FINANCE_CONTRACTS_VAT_TIMING_RULE', 'to_be_verified'),
    ],
];
