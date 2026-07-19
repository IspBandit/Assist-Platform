<?php

declare(strict_types=1);

/**
 * Owner-finance starter chart of accounts and tax codes (seeded idempotently).
 * Account rows: [code, name, type, is_system]. The agent-model control accounts
 * (Provider Funds Held / Settlements Payable / clearing) are flagged as system
 * accounts so they cannot be deleted once posted to.
 *
 * GST is NOT enabled by default (sole trader, not registered). The GST tax
 * codes are seeded as definitions only; postings use "No GST" until GST is
 * turned on from an effective date with accountant review.
 */

return [
    'accounts' => [
        // Assets (1xxx)
        ['1000', 'Business Bank Account', 'asset', 0],
        ['1010', 'Payment Gateway Clearing', 'asset', 1],
        ['1020', 'Undeposited Funds', 'asset', 0],
        ['1100', 'Accounts Receivable', 'asset', 1],
        ['1200', 'GST Receivable', 'asset', 0],
        ['1300', 'Prepaid Expenses', 'asset', 0],
        ['1400', 'Provider Settlement Receivable', 'asset', 0],
        ['1500', 'Computer and Office Equipment', 'asset', 0],
        ['1510', 'Software Development Assets', 'asset', 0],
        ['1520', 'Software and Intellectual Property Assets', 'asset', 0],
        ['1590', 'Accumulated Depreciation', 'asset', 0],
        ['1900', 'Other Current Assets', 'asset', 0],

        // Liabilities (2xxx)
        ['2000', 'Accounts Payable', 'liability', 1],
        ['2200', 'GST Payable', 'liability', 1],
        ['2210', 'GST Control', 'liability', 1],
        ['2300', 'Customer Credits', 'liability', 0],
        ['2400', 'Provider Funds Held', 'liability', 1],
        ['2410', 'Provider Settlements Payable', 'liability', 1],
        ['2420', 'Payment Clearing Liability', 'liability', 0],
        ['2500', 'Unearned Subscription Revenue', 'liability', 0],
        ['2510', 'Deferred Subscription Revenue', 'liability', 0],
        ['2520', 'Deferred Advertising Revenue', 'liability', 0],
        ['2530', 'Deferred Lead Credit Revenue', 'liability', 0],
        ['2540', 'Deferred Sponsorship Revenue', 'liability', 0],
        ['2700', 'Business Loans', 'liability', 0],
        ['2800', 'Accrued Expenses', 'liability', 0],
        ['2900', 'Other Current Liabilities', 'liability', 0],

        // Equity (3xxx)
        ['3000', 'Owner Contributions', 'equity', 0],
        ['3100', 'Owner Drawings or Distributions', 'equity', 0],
        ['3200', 'Retained Earnings', 'equity', 1],
        ['3300', 'Current Year Earnings', 'equity', 1],

        // Income (4xxx)
        ['4000', 'Provider Subscription Income', 'income', 0],
        ['4010', 'Basic Plan Income', 'income', 0],
        ['4020', 'Professional Plan Income', 'income', 0],
        ['4030', 'Premium Plan Income', 'income', 0],
        ['4040', 'Enterprise Plan Income', 'income', 0],
        ['4100', 'Featured Listing Income', 'income', 0],
        ['4110', 'Promoted Placement Income', 'income', 0],
        ['4120', 'Provider Verification Income', 'income', 0],
        ['4200', 'Lead Fee Income', 'income', 0],
        ['4210', 'Lead Credit Income', 'income', 0],
        ['4300', 'Booking Fee Income', 'income', 0],
        ['4310', 'Commission Income', 'income', 0],
        ['4320', 'Platform Transaction Fee Income', 'income', 0],
        ['4330', 'Payment Processing Fee Income', 'income', 0],
        ['4400', 'Additional Category Income', 'income', 0],
        ['4410', 'Additional Region Income', 'income', 0],
        ['4420', 'Additional Location Income', 'income', 0],
        ['4500', 'Town Sponsorship Income', 'income', 0],
        ['4510', 'Regional Sponsorship Income', 'income', 0],
        ['4520', 'State Sponsorship Income', 'income', 0],
        ['4600', 'Advertising Income', 'income', 0],
        ['4610', 'Banner Advertising Income', 'income', 0],
        ['4700', 'Referral Income', 'income', 0],
        ['4710', 'Affiliate Income', 'income', 0],
        ['4800', 'Onboarding Income', 'income', 0],
        ['4810', 'Profile Setup Income', 'income', 0],
        ['4820', 'Content Service Income', 'income', 0],
        ['4830', 'API Access Income', 'income', 0],
        ['4840', 'Integration Fee Income', 'income', 0],
        ['4850', 'White-Label Directory Income', 'income', 0],
        ['4860', 'Professional Services Income', 'income', 0],
        ['4900', 'Other Business Income', 'income', 0],

        // Cost of sales (5xxx)
        ['5000', 'Direct Hosting Costs', 'cost_of_sales', 0],
        ['5010', 'Direct Mapping Costs', 'cost_of_sales', 0],
        ['5020', 'Direct Email Delivery Costs', 'cost_of_sales', 0],
        ['5030', 'Direct SMS Delivery Costs', 'cost_of_sales', 0],
        ['5040', 'Direct Contractor Costs', 'cost_of_sales', 0],
        ['5050', 'Payment Processing Costs', 'cost_of_sales', 0],
        ['5060', 'Provider Verification Costs', 'cost_of_sales', 0],
        ['5070', 'Direct Customer Support Costs', 'cost_of_sales', 0],
        ['5080', 'Direct Advertising Delivery Costs', 'cost_of_sales', 0],

        // Expenses (6xxx)
        ['6000', 'Web Hosting and Infrastructure', 'expense', 0],
        ['6010', 'Database Hosting', 'expense', 0],
        ['6020', 'File Storage', 'expense', 0],
        ['6030', 'Mapping and Geocoding Services', 'expense', 0],
        ['6040', 'Email Delivery', 'expense', 0],
        ['6050', 'SMS Delivery', 'expense', 0],
        ['6060', 'Domain Registrations', 'expense', 0],
        ['6070', 'Software Subscriptions', 'expense', 0],
        ['6080', 'Software Development Contractors', 'expense', 0],
        ['6090', 'Technical Support Contractors', 'expense', 0],
        ['6100', 'Legal Fees', 'expense', 0],
        ['6110', 'Accounting and Bookkeeping', 'expense', 0],
        ['6120', 'Insurance', 'expense', 0],
        ['6130', 'Cybersecurity Services', 'expense', 0],
        ['6140', 'Advertising and Marketing', 'expense', 0],
        ['6150', 'Search Engine Advertising', 'expense', 0],
        ['6160', 'Social Media Advertising', 'expense', 0],
        ['6170', 'Content Creation', 'expense', 0],
        ['6180', 'Bank Fees', 'expense', 0],
        ['6190', 'Payment Gateway Fees', 'expense', 0],
        ['6200', 'Office Expenses', 'expense', 0],
        ['6210', 'Computer Equipment', 'expense', 0],
        ['6220', 'Telephone and Internet', 'expense', 0],
        ['6230', 'Travel', 'expense', 0],
        ['6240', 'Motor Vehicle', 'expense', 0],
        ['6250', 'Training', 'expense', 0],
        ['6260', 'Repairs and Maintenance', 'expense', 0],
        ['6270', 'Bad Debts', 'expense', 0],
        ['6280', 'Depreciation', 'expense', 0],
        ['6290', 'Amortisation', 'expense', 0],
        ['6300', 'General Expenses', 'expense', 0],

        // Other income / expense
        ['8000', 'Foreign Exchange Gain', 'other_income', 0],
        ['9000', 'Foreign Exchange Loss', 'other_expense', 0],
    ],

    // [code, name, rate, applies_to]
    'tax_codes' => [
        ['GST_INCOME', 'GST on Income', 10.0, 'sales'],
        ['GST_EXPENSE', 'GST on Expenses', 10.0, 'purchases'],
        ['GST_FREE', 'GST Free', 0.0, 'both'],
        ['INPUT_TAXED', 'Input Taxed', 0.0, 'both'],
        ['NO_GST', 'No GST', 0.0, 'both'],
        ['OUT_OF_SCOPE', 'Out of Scope', 0.0, 'both'],
        ['REVIEW', 'Review Required', 0.0, 'both'],
    ],
];
