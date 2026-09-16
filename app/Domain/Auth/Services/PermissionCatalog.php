<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Enums\RoleName;

class PermissionCatalog
{
    /**
     * Categories definition with metadata (id, label, icon, description, sort order).
     *
     * @return array<string, array{id: string, label: string, icon: string, description: string, sort: int}>
     */
    public static function getCategories(): array
    {
        return [
            'supply' => [
                'id' => 'supply',
                'label' => 'Supply Pipeline',
                'icon' => 'heroicon-o-funnel',
                'description' => 'Owner prospecting, opportunity stages, expected rental evaluations, and acquisition workflows.',
                'sort' => 1,
            ],
            'mou' => [
                'id' => 'mou',
                'label' => 'Owner MOUs & KYC',
                'icon' => 'heroicon-o-document-check',
                'description' => 'Landlord agreements, identity verification, commercial terms sign-off, and property conversion.',
                'sort' => 2,
            ],
            'property' => [
                'id' => 'property',
                'label' => 'Property Catalog',
                'icon' => 'heroicon-o-building-office-2',
                'description' => 'Building catalog, unit specs, room inventory, furnishings, and live market publishing.',
                'sort' => 3,
            ],
            'banking' => [
                'id' => 'banking',
                'label' => 'Landlord Banking',
                'icon' => 'heroicon-o-banknotes',
                'description' => 'Owner bank details, commercial commission structures, minimum rent guarantees, and bank gateway push.',
                'sort' => 4,
            ],
            'leasing' => [
                'id' => 'leasing',
                'label' => 'Tenancy Agreements',
                'icon' => 'heroicon-o-key',
                'description' => 'Lease contract drafting, tenant move-in activation, renewals, security deposits, and key handovers.',
                'sort' => 5,
            ],
            'audits' => [
                'id' => 'audits',
                'label' => 'Field Audits',
                'icon' => 'heroicon-o-clipboard-document-check',
                'description' => 'Move-in/out inspections, photographic condition evidence, auditor submissions, and managerial seals.',
                'sort' => 6,
            ],
            'maintenance' => [
                'id' => 'maintenance',
                'label' => 'Maintenance & Repairs',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'description' => 'Repair tickets, contractor quotations, internal profit margins, work orders, and field repair sign-offs.',
                'sort' => 7,
            ],
            'deboarding' => [
                'id' => 'deboarding',
                'label' => 'Tenant Deboarding',
                'icon' => 'heroicon-o-arrow-left-on-rectangle',
                'description' => 'Move-out notices, damage assessments, key returns, deposit deductions, and net refund disbursements.',
                'sort' => 8,
            ],
            'billing' => [
                'id' => 'billing',
                'label' => 'Billing & Collections',
                'icon' => 'heroicon-o-receipt-percent',
                'description' => 'Rent demand generation, prorated billings, offline receipt recording (UPI/cheque), and utility payments.',
                'sort' => 9,
            ],
            'payout' => [
                'id' => 'payout',
                'label' => 'Owner Payouts',
                'icon' => 'heroicon-o-currency-dollar',
                'description' => 'Monthly bulk payout batches, dispute holds, reserve withholdings, statements, and bank wire transfers.',
                'sort' => 10,
            ],
            'accounting' => [
                'id' => 'accounting',
                'label' => 'General Ledger & CoA',
                'icon' => 'heroicon-o-scale',
                'description' => 'Chart of Accounts, general ledger manual journal entries, bank reconciliations, and financial statements.',
                'sort' => 11,
            ],
            'admin' => [
                'id' => 'admin',
                'label' => 'Administration & Security',
                'icon' => 'heroicon-o-shield-check',
                'description' => 'Staff account provisioning, role and permission configuration, city zones, and audit trails.',
                'sort' => 12,
            ],
            'navigation' => [
                'id' => 'navigation',
                'label' => 'Navigation Access',
                'icon' => 'heroicon-o-squares-2x2',
                'description' => 'High-level access to view and navigate major functional modules within the Dwelly management panel.',
                'sort' => 13,
            ],
        ];
    }

    /**
     * Complete list of all 131 permissions with descriptive metadata.
     *
     * @return array<string, array{code: string, label: string, description: string, category: string, risk: 'read'|'action'|'sensitive'|'fiduciary'|'destructive'}>
     */
    public static function getPermissions(): array
    {
        return [
            // ==========================================
            // 1. Supply Pipeline & Landlord Leads
            // ==========================================
            'opportunity.viewAny' => [
                'code' => 'opportunity.viewAny',
                'label' => 'View Lead Pipeline List',
                'description' => 'Allows browsing and searching landlord leads and prospective property acquisition opportunities.',
                'category' => 'supply',
                'risk' => 'read',
            ],
            'opportunity.view' => [
                'code' => 'opportunity.view',
                'label' => 'View Opportunity Details',
                'description' => 'Allows viewing complete specifications, owner discussions, and commercial expectations of a lead.',
                'category' => 'supply',
                'risk' => 'read',
            ],
            'opportunity.create' => [
                'code' => 'opportunity.create',
                'label' => 'Create Inbound Landlord Leads',
                'description' => 'Allows logging new landlord prospects, inbound marketing leads, and property acquisition calls.',
                'category' => 'supply',
                'risk' => 'action',
            ],
            'opportunity.update' => [
                'code' => 'opportunity.update',
                'label' => 'Edit Opportunity Status & Terms',
                'description' => 'Allows advancing lead stages, updating expected rental values, and recording commercial negotiation notes.',
                'category' => 'supply',
                'risk' => 'action',
            ],
            'opportunity.delete' => [
                'code' => 'opportunity.delete',
                'label' => 'Delete Opportunity Records',
                'description' => 'Allows discarding junk inquiries, invalid prospect submissions, or duplicate lead records.',
                'category' => 'supply',
                'risk' => 'destructive',
            ],

            // ==========================================
            // 2. Owner MOUs & KYC Contracting
            // ==========================================
            'mou.viewAny' => [
                'code' => 'mou.viewAny',
                'label' => 'View Owner MOUs List',
                'description' => 'Allows browsing executed and in-progress property management agreements and owner contracts.',
                'category' => 'mou',
                'risk' => 'read',
            ],
            'mou.view' => [
                'code' => 'mou.view',
                'label' => 'View Signed MOU Details',
                'description' => 'Allows reviewing full agreement clauses, landlord contact details, and commercial agreements.',
                'category' => 'mou',
                'risk' => 'read',
            ],
            'mou.create' => [
                'code' => 'mou.create',
                'label' => 'Draft New Owner MOU',
                'description' => 'Allows preparing property management agreements, commission clauses, and onboarding agreements.',
                'category' => 'mou',
                'risk' => 'action',
            ],
            'mou.update' => [
                'code' => 'mou.update',
                'label' => 'Edit MOU Draft & Terms',
                'description' => 'Allows editing clauses, payout timelines, and commercial parameters before final execution.',
                'category' => 'mou',
                'risk' => 'action',
            ],
            'mou.verify' => [
                'code' => 'mou.verify',
                'label' => 'Verify Landlord KYC & Ownership',
                'description' => 'Authorizes verifying landlord identity documents, title deeds, power of attorney, and tax records.',
                'category' => 'mou',
                'risk' => 'sensitive',
            ],
            'mou.convert' => [
                'code' => 'mou.convert',
                'label' => 'Convert MOU to Live Properties',
                'description' => 'Enables executing the formal conversion that provisions buildings and units into the property catalog.',
                'category' => 'mou',
                'risk' => 'action',
            ],
            'mou.archive' => [
                'code' => 'mou.archive',
                'label' => 'Archive Expired / Superseded MOUs',
                'description' => 'Allows archiving expired management agreements, terminated contracts, or superseded documents.',
                'category' => 'mou',
                'risk' => 'destructive',
            ],

            // ==========================================
            // 3. Property Catalog & Onboarding
            // ==========================================
            'property.viewAny' => [
                'code' => 'property.viewAny',
                'label' => 'View Property Catalog',
                'description' => 'Allows browsing managed properties, buildings, units, and occupancy statuses across cities.',
                'category' => 'property',
                'risk' => 'read',
            ],
            'property.view' => [
                'code' => 'property.view',
                'label' => 'View Property Specifications',
                'description' => 'Allows viewing complete unit layouts, photo galleries, room configurations, and amenities.',
                'category' => 'property',
                'risk' => 'read',
            ],
            'property.create' => [
                'code' => 'property.create',
                'label' => 'Create New Property & Units',
                'description' => 'Allows manually adding new buildings, standalone houses, and rental unit structures.',
                'category' => 'property',
                'risk' => 'action',
            ],
            'property.update' => [
                'code' => 'property.update',
                'label' => 'Update Property Information',
                'description' => 'Allows editing property names, descriptions, amenities, media galleries, and operational details.',
                'category' => 'property',
                'risk' => 'action',
            ],
            'property.review' => [
                'code' => 'property.review',
                'label' => 'Conduct Onboarding Quality Review',
                'description' => 'Allows checking field audit completeness and verifying furnishings prior to public market launch.',
                'category' => 'property',
                'risk' => 'action',
            ],
            'property.activate' => [
                'code' => 'property.activate',
                'label' => 'Activate Property to Market',
                'description' => 'Formally publishes the property as available for tenant leasing and marketing matching.',
                'category' => 'property',
                'risk' => 'action',
            ],
            'property.archive' => [
                'code' => 'property.archive',
                'label' => 'Decommission / Archive Property',
                'description' => 'Removes the property from the active rental inventory upon landlord termination or sale.',
                'category' => 'property',
                'risk' => 'destructive',
            ],
            'property.structure.update' => [
                'code' => 'property.structure.update',
                'label' => 'Modify Room & Floor Structure',
                'description' => 'Allows altering physical floor plans, room splitting, bed capacity, and architectural unit layout.',
                'category' => 'property',
                'risk' => 'sensitive',
            ],

            // ==========================================
            // 4. Landlord Banking & Financial Terms
            // ==========================================
            'property.financials.view' => [
                'code' => 'property.financials.view',
                'label' => 'View Landlord Commercial Terms',
                'description' => 'Allows viewing landlord commission percentages, management fee ratios, and minimum guaranteed rent.',
                'category' => 'banking',
                'risk' => 'read',
            ],
            'property.financials.manage' => [
                'code' => 'property.financials.manage',
                'label' => 'Update Commercial & Commission Terms',
                'description' => 'Authorizes modifying landlord commission tiers, fixed management fees, and payout schedule rules.',
                'category' => 'banking',
                'risk' => 'fiduciary',
            ],
            'property.bank.view_unmasked' => [
                'code' => 'property.bank.view_unmasked',
                'label' => 'View Unmasked Bank Account Details',
                'description' => 'Reveals complete, unmasked landlord bank account numbers and IFSC codes. High security sensitivity.',
                'category' => 'banking',
                'risk' => 'sensitive',
            ],
            'property.bank.push' => [
                'code' => 'property.bank.push',
                'label' => 'Push Bank Details to Banking Gateway',
                'description' => 'Authorizes registering and syncing landlord beneficiary bank details with the automated payout provider.',
                'category' => 'banking',
                'risk' => 'fiduciary',
            ],

            // ==========================================
            // 5. Tenancy Agreements & Leasing
            // ==========================================
            'agreement.viewAny' => [
                'code' => 'agreement.viewAny',
                'label' => 'View Tenancy Agreements List',
                'description' => 'Allows browsing active, expired, upcoming, and draft tenant lease contracts.',
                'category' => 'leasing',
                'risk' => 'read',
            ],
            'agreement.view' => [
                'code' => 'agreement.view',
                'label' => 'View Full Tenancy Agreement',
                'description' => 'Allows viewing lease contract terms, monthly rent, security deposit amounts, and tenant schedules.',
                'category' => 'leasing',
                'risk' => 'read',
            ],
            'agreement.create' => [
                'code' => 'agreement.create',
                'label' => 'Draft New Tenancy Agreement',
                'description' => 'Allows creating lease agreements, linking vetted tenants to units, and defining contract dates.',
                'category' => 'leasing',
                'risk' => 'action',
            ],
            'agreement.update' => [
                'code' => 'agreement.update',
                'label' => 'Edit Draft Agreement Details',
                'description' => 'Allows updating tenant personal details, emergency contacts, and draft contract terms.',
                'category' => 'leasing',
                'risk' => 'action',
            ],
            'agreement.activate' => [
                'code' => 'agreement.activate',
                'label' => 'Activate Tenancy Agreement',
                'description' => 'Formally activates lease once security deposit and advance rent payments are verified.',
                'category' => 'leasing',
                'risk' => 'action',
            ],
            'agreement.renew' => [
                'code' => 'agreement.renew',
                'label' => 'Execute Tenancy Contract Renewal',
                'description' => 'Allows extending lease duration, applying rent escalation clauses, and issuing renewal contracts.',
                'category' => 'leasing',
                'risk' => 'action',
            ],
            'agreement.deboard' => [
                'code' => 'agreement.deboard',
                'label' => 'Initiate Move-Out / Deboarding',
                'description' => 'Triggers the move-out process and hands the tenancy case off to the operational deboarding queue.',
                'category' => 'leasing',
                'risk' => 'action',
            ],
            'agreement.delete' => [
                'code' => 'agreement.delete',
                'label' => 'Void / Delete Draft Agreements',
                'description' => 'Allows deleting aborted lease drafts or unexecuted agreement contracts.',
                'category' => 'leasing',
                'risk' => 'destructive',
            ],
            'agreement.terms.update' => [
                'code' => 'agreement.terms.update',
                'label' => 'Modify Executed Agreement Terms',
                'description' => 'Allows changing rental rates, utility split rules, or lock-in periods on active contracts.',
                'category' => 'leasing',
                'risk' => 'sensitive',
            ],
            'agreement.deposit.confirm' => [
                'code' => 'agreement.deposit.confirm',
                'label' => 'Confirm Security Deposit Clearance',
                'description' => 'Fiduciary clearance confirming that tenant security deposit funds are verified in the bank account.',
                'category' => 'leasing',
                'risk' => 'fiduciary',
            ],
            'agreement.keys.handover' => [
                'code' => 'agreement.keys.handover',
                'label' => 'Record Move-In Key Handover',
                'description' => 'Allows logging field physical key handovers, digital smart lock codes, and tenant check-in.',
                'category' => 'leasing',
                'risk' => 'action',
            ],

            // ==========================================
            // 6. Field Audits & Inspections
            // ==========================================
            'audit.viewAny' => [
                'code' => 'audit.viewAny',
                'label' => 'View Field Audits List',
                'description' => 'Allows browsing scheduled, in-progress, and completed property condition inspections.',
                'category' => 'audits',
                'risk' => 'read',
            ],
            'audit.view' => [
                'code' => 'audit.view',
                'label' => 'View Inspection Reports & Photos',
                'description' => 'Allows reviewing detailed inspection items, condition ratings, meter readings, and photographic proof.',
                'category' => 'audits',
                'risk' => 'read',
            ],
            'audit.create' => [
                'code' => 'audit.create',
                'label' => 'Schedule New Property Inspection',
                'description' => 'Allows booking move-in, routine periodic, or move-out inspections and assigning field auditors.',
                'category' => 'audits',
                'risk' => 'action',
            ],
            'audit.update' => [
                'code' => 'audit.update',
                'label' => 'Edit Inspection Details & Schedules',
                'description' => 'Allows modifying inspection appointment times, assigned staff, and baseline inspection checklists.',
                'category' => 'audits',
                'risk' => 'action',
            ],
            'audit.inspect' => [
                'code' => 'audit.inspect',
                'label' => 'Execute Live Field Inspection',
                'description' => 'Allows field auditors to log item conditions, meter readings, and upload timestamped defect photos.',
                'category' => 'audits',
                'risk' => 'action',
            ],
            'audit.submit' => [
                'code' => 'audit.submit',
                'label' => 'Submit Completed Audit for Review',
                'description' => 'Allows field auditor to mark inspection complete and route findings to management for sign-off.',
                'category' => 'audits',
                'risk' => 'action',
            ],
            'audit.review' => [
                'code' => 'audit.review',
                'label' => 'Managerial Audit Review & Flagging',
                'description' => 'Allows operations managers to verify auditor observations and flag items for maintenance or damage.',
                'category' => 'audits',
                'risk' => 'action',
            ],
            'audit.seal' => [
                'code' => 'audit.seal',
                'label' => 'Cryptographically Seal Audit Report',
                'description' => 'Locks the audit report permanently into an immutable, tamper-proof legal document.',
                'category' => 'audits',
                'risk' => 'sensitive',
            ],
            'audit.delete' => [
                'code' => 'audit.delete',
                'label' => 'Cancel / Delete Inspection Appointment',
                'description' => 'Allows removing unexecuted or mistakenly scheduled inspection entries.',
                'category' => 'audits',
                'risk' => 'destructive',
            ],

            // ==========================================
            // 7. Maintenance & Work Orders
            // ==========================================
            'maintenance.viewAny' => [
                'code' => 'maintenance.viewAny',
                'label' => 'View Maintenance Tickets List',
                'description' => 'Allows browsing repair requests, issue categories, vendor quotes, and completion status.',
                'category' => 'maintenance',
                'risk' => 'read',
            ],
            'maintenance.view' => [
                'code' => 'maintenance.view',
                'label' => 'View Ticket Details & Timeline',
                'description' => 'Allows viewing repair history, vendor quotations, tenant notes, and proof of work completed.',
                'category' => 'maintenance',
                'risk' => 'read',
            ],
            'maintenance.create' => [
                'code' => 'maintenance.create',
                'label' => 'Log New Maintenance Ticket',
                'description' => 'Allows reporting issues, logging tenant complaints, and requesting repair assessments.',
                'category' => 'maintenance',
                'risk' => 'action',
            ],
            'maintenance.update' => [
                'code' => 'maintenance.update',
                'label' => 'Update Ticket & Vendor Assignment',
                'description' => 'Allows assigning contractors, changing repair priorities, and updating repair timelines.',
                'category' => 'maintenance',
                'risk' => 'action',
            ],
            'maintenance.fault.attribute' => [
                'code' => 'maintenance.fault.attribute',
                'label' => 'Determine Financial Liability Attribution',
                'description' => 'Authorizes designating financial responsibility between Tenant, Landlord, or Company Fair Wear & Tear.',
                'category' => 'maintenance',
                'risk' => 'sensitive',
            ],
            'maintenance.quote.collect' => [
                'code' => 'maintenance.quote.collect',
                'label' => 'Upload Contractor Estimates & Quotes',
                'description' => 'Allows recording estimates and repair quotes received from trade technicians and suppliers.',
                'category' => 'maintenance',
                'risk' => 'action',
            ],
            'maintenance.margin.apply' => [
                'code' => 'maintenance.margin.apply',
                'label' => 'Apply Internal Profit Margin Markup',
                'description' => 'Allows configuring internal Dwelly profit markups on vendor repair costs charged to owners or tenants.',
                'category' => 'maintenance',
                'risk' => 'fiduciary',
            ],
            'maintenance.margin.view' => [
                'code' => 'maintenance.margin.view',
                'label' => 'View Hidden Contractor Margins',
                'description' => 'Reveals true vendor cost vs marked-up client invoice price and internal company gross margin.',
                'category' => 'maintenance',
                'risk' => 'sensitive',
            ],
            'maintenance.work_order.issue' => [
                'code' => 'maintenance.work_order.issue',
                'label' => 'Issue Binding Vendor Work Orders',
                'description' => 'Formally authorizes external contractors to commence work and commits company expenditure.',
                'category' => 'maintenance',
                'risk' => 'action',
            ],
            'maintenance.repair.supervise' => [
                'code' => 'maintenance.repair.supervise',
                'label' => 'Supervise & Upload Repair Proof',
                'description' => 'Allows recording on-site inspection of repairs and uploading after-repair photographic proof.',
                'category' => 'maintenance',
                'risk' => 'action',
            ],
            'maintenance.sign_off' => [
                'code' => 'maintenance.sign_off',
                'label' => 'Sign Off & Clear Vendor Invoices',
                'description' => 'Approves completed maintenance work and releases vendor bills to accounts payable for settlement.',
                'category' => 'maintenance',
                'risk' => 'fiduciary',
            ],
            'maintenance.delete' => [
                'code' => 'maintenance.delete',
                'label' => 'Delete Invalid Maintenance Tickets',
                'description' => 'Allows purging duplicate, spam, or erroneously created repair requests.',
                'category' => 'maintenance',
                'risk' => 'destructive',
            ],

            // ==========================================
            // 8. Tenant Deboarding & Exit Settlements
            // ==========================================
            'deboarding.viewAny' => [
                'code' => 'deboarding.viewAny',
                'label' => 'View Deboarding Cases List',
                'description' => 'Allows browsing active move-out cases, notice periods, and deposit settlement queues.',
                'category' => 'deboarding',
                'risk' => 'read',
            ],
            'deboarding.view' => [
                'code' => 'deboarding.view',
                'label' => 'View Deboarding & Settlement Details',
                'description' => 'Allows viewing move-out audit comparisons, itemized deductions, and refund calculations.',
                'category' => 'deboarding',
                'risk' => 'read',
            ],
            'deboarding.create' => [
                'code' => 'deboarding.create',
                'label' => 'Initiate Deboarding Case',
                'description' => 'Allows registering tenant move-out notices, scheduled exit dates, and forwarding addresses.',
                'category' => 'deboarding',
                'risk' => 'action',
            ],
            'deboarding.update' => [
                'code' => 'deboarding.update',
                'label' => 'Update Deboarding Case Details',
                'description' => 'Allows modifying target move-out dates, tenant exit documentation, and case notes.',
                'category' => 'deboarding',
                'risk' => 'action',
            ],
            'deboarding.audit' => [
                'code' => 'deboarding.audit',
                'label' => 'Perform Move-Out Checkout Audit',
                'description' => 'Authorizes executing the final physical checkout inspection and inventory verification.',
                'category' => 'deboarding',
                'risk' => 'action',
            ],
            'deboarding.damage.assess' => [
                'code' => 'deboarding.damage.assess',
                'label' => 'Assess Tenant Damage Deductions',
                'description' => 'Allows calculating financial liability for broken furnishings, deep cleaning, or unpaid bills.',
                'category' => 'deboarding',
                'risk' => 'sensitive',
            ],
            'deboarding.keys.return' => [
                'code' => 'deboarding.keys.return',
                'label' => 'Acknowledge Key & Access Device Return',
                'description' => 'Confirms physical receipt of property keys, access cards, and smart lock reset.',
                'category' => 'deboarding',
                'risk' => 'action',
            ],
            'deboarding.settlement.draft' => [
                'code' => 'deboarding.settlement.draft',
                'label' => 'Draft Deposit Settlement Statement',
                'description' => 'Allows assembling the itemized settlement statement combining damage deductions and utility bills.',
                'category' => 'deboarding',
                'risk' => 'action',
            ],
            'deboarding.settlement.approve' => [
                'code' => 'deboarding.settlement.approve',
                'label' => 'Approve Final Deposit Settlement',
                'description' => 'Operational sign-off locking tenant deductions and clearing refund balance for financial disbursement.',
                'category' => 'deboarding',
                'risk' => 'sensitive',
            ],
            'deboarding.refund.disburse' => [
                'code' => 'deboarding.refund.disburse',
                'label' => 'Disburse Net Security Deposit Refund',
                'description' => 'Fiduciary authority to initiate and execute electronic bank wire refund to departing tenant.',
                'category' => 'deboarding',
                'risk' => 'fiduciary',
            ],
            'deboarding.complete' => [
                'code' => 'deboarding.complete',
                'label' => 'Formally Close Deboarding Case',
                'description' => 'Marks the tenancy as fully vacated, settled, and returns unit to vacant catalog status.',
                'category' => 'deboarding',
                'risk' => 'action',
            ],
            'deboarding.delete' => [
                'code' => 'deboarding.delete',
                'label' => 'Void / Delete Deboarding Case',
                'description' => 'Allows cancelling aborted move-out notices when tenants revoke departure.',
                'category' => 'deboarding',
                'risk' => 'destructive',
            ],

            // ==========================================
            // 9. Billing & Rent Collections
            // ==========================================
            'billing.viewAny' => [
                'code' => 'billing.viewAny',
                'label' => 'View Rent Demands & Invoices',
                'description' => 'Allows browsing tenant billing records, recurring rent demands, and collection statuses.',
                'category' => 'billing',
                'risk' => 'read',
            ],
            'billing.view' => [
                'code' => 'billing.view',
                'label' => 'View Detailed Invoice & Receipts',
                'description' => 'Allows inspecting invoice line items, tax breakdowns, payment vouchers, and ledger allocations.',
                'category' => 'billing',
                'risk' => 'read',
            ],
            'billing.hub.access' => [
                'code' => 'billing.hub.access',
                'label' => 'Access Central Receivables Hub',
                'description' => 'Grants access to the main billing and financial collections dashboard.',
                'category' => 'billing',
                'risk' => 'read',
            ],
            'billing.rent.generate' => [
                'code' => 'billing.rent.generate',
                'label' => 'Generate Monthly Rent Demands',
                'description' => 'Authorizes triggering monthly recurring rent generation for all active tenancy contracts.',
                'category' => 'billing',
                'risk' => 'fiduciary',
            ],
            'billing.rent.prorate' => [
                'code' => 'billing.rent.prorate',
                'label' => 'Calculate & Issue Prorated Rent',
                'description' => 'Allows generating mid-month partial rent demands for partial occupancies or lease extensions.',
                'category' => 'billing',
                'risk' => 'action',
            ],
            'billing.receipt.record' => [
                'code' => 'billing.receipt.record',
                'label' => 'Record Offline Rent Payments',
                'description' => 'Authorizes recording manual offline receipts (cash, cheques, manual NEFT/RTGS, and direct UPI).',
                'category' => 'billing',
                'risk' => 'fiduciary',
            ],
            'billing.deposit.record' => [
                'code' => 'billing.deposit.record',
                'label' => 'Record Security Deposit Receipt',
                'description' => 'Allows logging and issuing official receipts for initial security deposit payments.',
                'category' => 'billing',
                'risk' => 'fiduciary',
            ],
            'billing.bill.pay' => [
                'code' => 'billing.bill.pay',
                'label' => 'Pay Property Utility Bills',
                'description' => 'Authorizes disbursing payments for electricity, water, internet, and building maintenance bills.',
                'category' => 'billing',
                'risk' => 'fiduciary',
            ],
            'billing.advance.record' => [
                'code' => 'billing.advance.record',
                'label' => 'Record Advance Rent Credits',
                'description' => 'Allows recording advance rental balances and applying them against future monthly invoices.',
                'category' => 'billing',
                'risk' => 'fiduciary',
            ],
            'billing.invoice.create' => [
                'code' => 'billing.invoice.create',
                'label' => 'Create Client Invoices',
                'description' => 'Allows raising manual client invoices, documentation charges, and tenant recovery demands.',
                'category' => 'billing',
                'risk' => 'action',
            ],
            'billing.invoice.post' => [
                'code' => 'billing.invoice.post',
                'label' => 'Approve & Post Invoices',
                'description' => 'Allows approving draft client invoices and posting them to the General Ledger.',
                'category' => 'billing',
                'risk' => 'fiduciary',
            ],
            'billing.bill.viewAny' => [
                'code' => 'billing.bill.viewAny',
                'label' => 'View Vendor Bills & Payables',
                'description' => 'Allows browsing vendor bills, contractor repair invoices, and utility payables.',
                'category' => 'billing',
                'risk' => 'read',
            ],
            'billing.bill.view' => [
                'code' => 'billing.bill.view',
                'label' => 'View Detailed Bill & Attachments',
                'description' => 'Allows inspecting vendor bill line items, taxes, and uploaded contractor invoices.',
                'category' => 'billing',
                'risk' => 'read',
            ],
            'billing.bill.create' => [
                'code' => 'billing.bill.create',
                'label' => 'Record Inbound Vendor Bills',
                'description' => 'Allows recording incoming contractor work order bills, society dues, and utility invoices.',
                'category' => 'billing',
                'risk' => 'action',
            ],
            'billing.bill.approve' => [
                'code' => 'billing.bill.approve',
                'label' => 'Approve Vendor Bills for Settlement',
                'description' => 'Authorizes signing off vendor bills for accounts payable release or owner payout deduction.',
                'category' => 'billing',
                'risk' => 'fiduciary',
            ],

            // ==========================================
            // 10. Owner Payout Engine
            // ==========================================
            'payout.viewAny' => [
                'code' => 'payout.viewAny',
                'label' => 'View Owner Payout Batches',
                'description' => 'Allows browsing monthly landlord payout summaries, net disbursement amounts, and schedules.',
                'category' => 'payout',
                'risk' => 'read',
            ],
            'payout.view' => [
                'code' => 'payout.view',
                'label' => 'View Itemized Payout Breakdown',
                'description' => 'Allows viewing gross collected rent, commission deductions, maintenance deductions, and net payout.',
                'category' => 'payout',
                'risk' => 'read',
            ],
            'payout.bulk.generate' => [
                'code' => 'payout.bulk.generate',
                'label' => 'Generate Monthly Bulk Payout Batches',
                'description' => 'Authorizes running the calculation engine that generates payouts for all managed property owners.',
                'category' => 'payout',
                'risk' => 'fiduciary',
            ],
            'payout.hold.manage' => [
                'code' => 'payout.hold.manage',
                'label' => 'Place / Release Payout Holds',
                'description' => 'Allows freezing landlord payouts due to disputes, missing bank details, or pending maintenance.',
                'category' => 'payout',
                'risk' => 'sensitive',
            ],
            'payout.commission.validate' => [
                'code' => 'payout.commission.validate',
                'label' => 'Validate Commission Deductions',
                'description' => 'Allows verifying management fee withholding calculations against signed owner MOU agreements.',
                'category' => 'payout',
                'risk' => 'read',
            ],
            'payout.reserve.manage' => [
                'code' => 'payout.reserve.manage',
                'label' => 'Manage Contingency Reserve Funds',
                'description' => 'Allows withholding and releasing maintenance emergency reserves from landlord balances.',
                'category' => 'payout',
                'risk' => 'sensitive',
            ],
            'payout.disburse' => [
                'code' => 'payout.disburse',
                'label' => 'Authorize Bank Payout Disbursement',
                'description' => 'High fiduciary power: releases actual funds from company bank accounts to landlord accounts.',
                'category' => 'payout',
                'risk' => 'fiduciary',
            ],
            'payout.statement.generate' => [
                'code' => 'payout.statement.generate',
                'label' => 'Generate Official Payout Statements',
                'description' => 'Allows generating, publishing, and emailing monthly financial statements to property owners.',
                'category' => 'payout',
                'risk' => 'action',
            ],
            'payout.delete' => [
                'code' => 'payout.delete',
                'label' => 'Cancel / Delete Draft Payouts',
                'description' => 'Allows voiding recalculating draft payout batches prior to banking execution.',
                'category' => 'payout',
                'risk' => 'destructive',
            ],

            // ==========================================
            // 11. Double-Entry Accounting & Ledgers
            // ==========================================
            'accounting.panel.access' => [
                'code' => 'accounting.panel.access',
                'label' => 'Access General Ledger & CoA Hub',
                'description' => 'Allows navigating to the double-entry accounting screens, ledgers, and trial balances.',
                'category' => 'accounting',
                'risk' => 'read',
            ],
            'accounting.coa.manage' => [
                'code' => 'accounting.coa.manage',
                'label' => 'Manage Chart of Accounts',
                'description' => 'Allows creating, modifying, and archiving balance sheet and income statement ledger accounts.',
                'category' => 'accounting',
                'risk' => 'sensitive',
            ],
            'accounting.journal.post' => [
                'code' => 'accounting.journal.post',
                'label' => 'Post Manual Journal Vouchers',
                'description' => 'Fiduciary permission to post manual debit and credit journal entries to the company general ledger.',
                'category' => 'accounting',
                'risk' => 'fiduciary',
            ],
            'accounting.bank.recon' => [
                'code' => 'accounting.bank.recon',
                'label' => 'Perform Bank Feed Reconciliation',
                'description' => 'Allows matching bank transactions against invoices, bill payments, and payouts.',
                'category' => 'accounting',
                'risk' => 'fiduciary',
            ],
            'accounting.tax.report' => [
                'code' => 'accounting.tax.report',
                'label' => 'Generate Tax & Statutory Reports',
                'description' => 'Allows compiling GST returns, TDS withholdings, and statutory fiscal reports.',
                'category' => 'accounting',
                'risk' => 'read',
            ],
            'accounting.reports.view' => [
                'code' => 'accounting.reports.view',
                'label' => 'View Balance Sheet & P&L Reports',
                'description' => 'Allows viewing company Trial Balance, Profit & Loss statements, and Balance Sheets.',
                'category' => 'accounting',
                'risk' => 'read',
            ],

            // ==========================================
            // 12. Administration, Security & Masters
            // ==========================================
            'admin.users.viewAny' => [
                'code' => 'admin.users.viewAny',
                'label' => 'View Staff Directory & Users',
                'description' => 'Allows browsing employee accounts, system logins, and active user statuses.',
                'category' => 'admin',
                'risk' => 'read',
            ],
            'admin.users.view' => [
                'code' => 'admin.users.view',
                'label' => 'View User Profiles & Assignments',
                'description' => 'Allows viewing staff personal profiles, assigned branches, and role permissions.',
                'category' => 'admin',
                'risk' => 'read',
            ],
            'admin.users.create' => [
                'code' => 'admin.users.create',
                'label' => 'Provision New Staff Accounts',
                'description' => 'Allows creating new employee credentials and onboarding operational staff.',
                'category' => 'admin',
                'risk' => 'action',
            ],
            'admin.users.update' => [
                'code' => 'admin.users.update',
                'label' => 'Edit User Profiles & Branch Access',
                'description' => 'Allows modifying employee contact info, resetting passwords, and changing branch assignments.',
                'category' => 'admin',
                'risk' => 'action',
            ],
            'admin.users.delete' => [
                'code' => 'admin.users.delete',
                'label' => 'Deactivate / Delete User Accounts',
                'description' => 'Allows terminating employee access and disabling user accounts.',
                'category' => 'admin',
                'risk' => 'destructive',
            ],
            'admin.roles.assign' => [
                'code' => 'admin.roles.assign',
                'label' => 'Assign Security Roles to Users',
                'description' => 'Critical security privilege: allows elevating user accounts to specific system roles.',
                'category' => 'admin',
                'risk' => 'sensitive',
            ],
            'admin.roles.viewAny' => [
                'code' => 'admin.roles.viewAny',
                'label' => 'View Roles & Permissions Definitions',
                'description' => 'Allows viewing the security roles list and inspecting permission matrices.',
                'category' => 'admin',
                'risk' => 'read',
            ],
            'admin.roles.create' => [
                'code' => 'admin.roles.create',
                'label' => 'Create Custom Security Roles',
                'description' => 'Allows defining new custom roles with tailored permission subsets.',
                'category' => 'admin',
                'risk' => 'sensitive',
            ],
            'admin.roles.update' => [
                'code' => 'admin.roles.update',
                'label' => 'Modify Role Permissions & Titles',
                'description' => 'Allows editing permissions, display titles, and responsibilities for system and custom roles.',
                'category' => 'admin',
                'risk' => 'sensitive',
            ],
            'admin.roles.delete' => [
                'code' => 'admin.roles.delete',
                'label' => 'Delete Custom Roles',
                'description' => 'Allows removing obsolete custom roles. (Core system roles remain protected).',
                'category' => 'admin',
                'risk' => 'destructive',
            ],
            'admin.geographic.viewAny' => [
                'code' => 'admin.geographic.viewAny',
                'label' => 'View Geographic Hierarchy',
                'description' => 'Allows browsing operating cities, branches, and neighborhood clusters.',
                'category' => 'admin',
                'risk' => 'read',
            ],
            'admin.geographic.manage' => [
                'code' => 'admin.geographic.manage',
                'label' => 'Manage Cities & Branch Hierarchy',
                'description' => 'Allows creating operating cities, configuring branch boundaries, and editing location codes.',
                'category' => 'admin',
                'risk' => 'sensitive',
            ],
            'admin.masters.viewAny' => [
                'code' => 'admin.masters.viewAny',
                'label' => 'View Master Reference Data',
                'description' => 'Allows inspecting property amenities, vendor trades, unit categories, and financial model settings.',
                'category' => 'admin',
                'risk' => 'read',
            ],
            'admin.masters.manage' => [
                'code' => 'admin.masters.manage',
                'label' => 'Manage Reference Masters & Settings',
                'description' => 'Allows editing system-wide reference amenities, vendor trades, and financial interest parameters.',
                'category' => 'admin',
                'risk' => 'sensitive',
            ],
            'admin.audit_logs.viewAny' => [
                'code' => 'admin.audit_logs.viewAny',
                'label' => 'View Security Audit Logs',
                'description' => 'Allows browsing forensic activity logs of user logins, role modifications, and system events.',
                'category' => 'admin',
                'risk' => 'read',
            ],
            'admin.audit_logs.view' => [
                'code' => 'admin.audit_logs.view',
                'label' => 'View Detailed Audit Diffs',
                'description' => 'Allows viewing forensic before-and-after property change diffs, IP addresses, and user timestamps.',
                'category' => 'admin',
                'risk' => 'read',
            ],

            // ==========================================
            // 13. Workspace & Module Navigation Access
            // ==========================================
            'property.access' => [
                'code' => 'property.access',
                'label' => 'Property Workspace Access',
                'description' => 'Allows viewing and accessing Property and Unit inventory menus in the navigation panel.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'party.access' => [
                'code' => 'party.access',
                'label' => 'Parties & CRM Workspace Access',
                'description' => 'Allows viewing Owners, Tenants, and Vendors in the navigation panel.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'task.access' => [
                'code' => 'task.access',
                'label' => 'Operational Tasks Access',
                'description' => 'Allows viewing operational checklist queues and task management.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'maintenance.access' => [
                'code' => 'maintenance.access',
                'label' => 'Maintenance Workspace Access',
                'description' => 'Allows accessing Maintenance Requests and Repairs in the navigation panel.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'finance.access' => [
                'code' => 'finance.access',
                'label' => 'Finance Overview Access',
                'description' => 'Allows accessing top-level financial summaries and cash flow dashboards.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'utility.access' => [
                'code' => 'utility.access',
                'label' => 'Utility Management Access',
                'description' => 'Allows accessing utility meters and energy bill schedules in the navigation panel.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'agreement.access' => [
                'code' => 'agreement.access',
                'label' => 'Tenancy Agreements Access',
                'description' => 'Allows accessing the Tenancy Agreements resource in the navigation panel.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'document.access' => [
                'code' => 'document.access',
                'label' => 'Documents Hub Access',
                'description' => 'Allows accessing the centralized documents and uploaded files repository.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'communication.access' => [
                'code' => 'communication.access',
                'label' => 'Communication Logs Access',
                'description' => 'Allows viewing automated SMS, WhatsApp, and email transmission logs.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'accounting.access' => [
                'code' => 'accounting.access',
                'label' => 'Accounting Workspace Access',
                'description' => 'Allows accessing Chart of Accounts, Journals, and Financial Statement modules.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'administration.access' => [
                'code' => 'administration.access',
                'label' => 'Administration Cluster Access',
                'description' => 'Allows accessing the Administration cluster (Users, Roles, Geography, Masters).',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'opportunity.access' => [
                'code' => 'opportunity.access',
                'label' => 'Opportunity Pipeline Access',
                'description' => 'Allows accessing Landlord Leads and Acquisition opportunities.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'mou.access' => [
                'code' => 'mou.access',
                'label' => 'Owner MOUs Access',
                'description' => 'Allows accessing Owner Agreements and MOU contracting in the navigation panel.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'audit.access' => [
                'code' => 'audit.access',
                'label' => 'Field Audits Access',
                'description' => 'Allows accessing the Field Audits and Inspections resource in the navigation panel.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'deboarding.access' => [
                'code' => 'deboarding.access',
                'label' => 'Tenant Deboarding Access',
                'description' => 'Allows accessing Move-Out and Deboarding cases in the navigation panel.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'billing.access' => [
                'code' => 'billing.access',
                'label' => 'Billing & Collections Access',
                'description' => 'Allows accessing Rent Demands and Collections Hub in the navigation panel.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
            'payout.access' => [
                'code' => 'payout.access',
                'label' => 'Owner Payouts Access',
                'description' => 'Allows accessing the Owner Payout Engine in the navigation panel.',
                'category' => 'navigation',
                'risk' => 'read',
            ],
        ];
    }

    /**
     * Get permissions grouped by category with category metadata.
     *
     * @return array<string, array{
     *     category: array{id: string, label: string, icon: string, description: string, sort: int},
     *     permissions: array<string, array{code: string, label: string, description: string, category: string, risk: string}>
     * }>
     */
    public static function getGroupedPermissions(): array
    {
        $categories = self::getCategories();
        $permissions = self::getPermissions();

        // Sort categories by 'sort' order
        uasort($categories, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        $grouped = [];
        foreach ($categories as $catId => $catMeta) {
            $grouped[$catId] = [
                'category' => $catMeta,
                'permissions' => [],
            ];
        }

        foreach ($permissions as $code => $data) {
            $catId = $data['category'] ?? 'admin';
            if (! isset($grouped[$catId])) {
                $grouped[$catId] = [
                    'category' => [
                        'id' => $catId,
                        'label' => ucfirst($catId),
                        'icon' => 'heroicon-o-shield-check',
                        'description' => 'Additional module permissions.',
                        'sort' => 99,
                    ],
                    'permissions' => [],
                ];
            }
            $grouped[$catId]['permissions'][$code] = $data;
        }

        return $grouped;
    }

    /**
     * Return default factory permissions for any given system role.
     *
     * @return array<string>
     */
    public static function getDefaultsForRole(string|RoleName $role): array
    {
        $roleName = $role instanceof RoleName ? $role->value : $role;

        $allPermissions = array_keys(self::getPermissions());
        $allModules = [
            'property.access', 'party.access', 'task.access', 'maintenance.access',
            'finance.access', 'utility.access', 'agreement.access', 'document.access',
            'communication.access', 'accounting.access', 'administration.access',
            'opportunity.access', 'mou.access', 'audit.access', 'deboarding.access',
            'billing.access', 'payout.access',
        ];

        return match ($roleName) {
            RoleName::BUSINESS_OWNER->value => $allPermissions,

            RoleName::CITY_MANAGER->value => array_values(array_filter($allPermissions, function ($perm) {
                // Strictly exclude fiduciary disbursement, double-entry ledger mutations, and system security administration
                return ! in_array($perm, [
                    'deboarding.refund.disburse',
                    'payout.disburse',
                    'billing.rent.generate',
                    'billing.receipt.record',
                    'billing.deposit.record',
                    'billing.bill.pay',
                    'billing.advance.record',
                    'billing.invoice.post',
                    'payout.delete',
                    'accounting.coa.manage',
                    'accounting.journal.post',
                    'accounting.bank.recon',
                    'admin.roles.assign',
                    'admin.roles.viewAny',
                    'admin.roles.create',
                    'admin.roles.update',
                    'admin.roles.delete',
                    'admin.users.delete',
                    'admin.users.update',
                    'admin.geographic.manage',
                    'admin.masters.manage',
                ], true);
            })),

            RoleName::SUPPLY_MANAGER->value => [
                'opportunity.access', 'mou.access', 'property.access', 'party.access',
                'document.access', 'communication.access', 'payout.access',
                'opportunity.viewAny', 'opportunity.view', 'opportunity.create', 'opportunity.update',
                'mou.viewAny', 'mou.view', 'mou.create', 'mou.update', 'mou.convert',
                'property.viewAny', 'property.view', 'property.create', 'property.update',
                'property.financials.view',
                'payout.commission.validate', 'payout.statement.generate',
            ],

            RoleName::DEMAND_MANAGER->value => [
                'party.access', 'agreement.access', 'document.access', 'communication.access',
                'property.access', 'maintenance.access', 'deboarding.access',
                'property.viewAny', 'property.view',
                'agreement.viewAny', 'agreement.view', 'agreement.create', 'agreement.update',
                'agreement.activate', 'agreement.renew', 'agreement.deboard', 'agreement.keys.handover',
                'maintenance.create',
                'deboarding.viewAny', 'deboarding.view', 'deboarding.create',
            ],

            RoleName::OPERATIONS_MANAGER->value => [
                'property.access', 'party.access', 'task.access', 'maintenance.access', 'audit.access', 'deboarding.access',
                'agreement.access', 'document.access', 'communication.access', 'opportunity.access', 'mou.access',
                'billing.access', 'payout.access',
                'opportunity.viewAny', 'opportunity.view',
                'mou.viewAny', 'mou.view', 'mou.verify', 'mou.convert', 'mou.archive',
                'property.viewAny', 'property.view', 'property.update', 'property.review', 'property.activate', 'property.structure.update',
                'property.financials.view',
                'agreement.viewAny', 'agreement.view', 'agreement.deboard',
                'audit.viewAny', 'audit.view', 'audit.create', 'audit.update', 'audit.review', 'audit.seal',
                'maintenance.viewAny', 'maintenance.view', 'maintenance.create', 'maintenance.update',
                'maintenance.fault.attribute', 'maintenance.quote.collect', 'maintenance.margin.apply',
                'maintenance.margin.view', 'maintenance.work_order.issue', 'maintenance.repair.supervise',
                'maintenance.sign_off',
                'deboarding.viewAny', 'deboarding.view', 'deboarding.create', 'deboarding.update',
                'deboarding.audit', 'deboarding.damage.assess', 'deboarding.keys.return',
                'deboarding.settlement.draft', 'deboarding.settlement.approve', 'deboarding.complete',
                'billing.viewAny', 'billing.view', 'billing.hub.access',
                'billing.invoice.create',
                'billing.bill.viewAny', 'billing.bill.view', 'billing.bill.create', 'billing.bill.approve',
                'payout.viewAny', 'payout.view', 'payout.hold.manage', 'payout.reserve.manage',
            ],

            RoleName::OPERATIONS_EXECUTIVE->value => [
                'property.access', 'party.access', 'task.access', 'maintenance.access',
                'document.access', 'communication.access', 'audit.access', 'deboarding.access',
                'property.viewAny', 'property.view', 'property.update',
                'agreement.keys.handover',
                'audit.viewAny', 'audit.view', 'audit.inspect', 'audit.submit',
                'maintenance.viewAny', 'maintenance.view', 'maintenance.create', 'maintenance.update',
                'maintenance.quote.collect', 'maintenance.repair.supervise',
                'deboarding.viewAny', 'deboarding.view', 'deboarding.damage.assess', 'deboarding.keys.return',
            ],

            RoleName::ACCOUNTANT->value => [
                'party.access', 'finance.access', 'utility.access', 'accounting.access', 'mou.access',
                'property.access', 'agreement.access', 'maintenance.access', 'deboarding.access',
                'billing.access', 'payout.access', 'administration.access',
                'mou.viewAny', 'mou.view',
                'property.viewAny', 'property.view',
                'property.financials.view', 'property.financials.manage', 'property.bank.view_unmasked', 'property.bank.push',
                'agreement.viewAny', 'agreement.view', 'agreement.deposit.confirm',
                'maintenance.viewAny', 'maintenance.view', 'maintenance.margin.view', 'maintenance.sign_off',
                'deboarding.viewAny', 'deboarding.view', 'deboarding.damage.assess', 'deboarding.keys.return',
                'deboarding.settlement.draft', 'deboarding.settlement.approve', 'deboarding.refund.disburse', 'deboarding.complete',
                'billing.viewAny', 'billing.view', 'billing.hub.access', 'billing.rent.generate',
                'billing.rent.prorate', 'billing.receipt.record', 'billing.deposit.record',
                'billing.bill.pay', 'billing.advance.record',
                'billing.invoice.create', 'billing.invoice.post',
                'billing.bill.viewAny', 'billing.bill.view', 'billing.bill.create', 'billing.bill.approve',
                'payout.viewAny', 'payout.view', 'payout.bulk.generate', 'payout.hold.manage',
                'payout.commission.validate', 'payout.reserve.manage', 'payout.disburse',
                'payout.statement.generate', 'payout.delete',
                'accounting.panel.access', 'accounting.coa.manage', 'accounting.journal.post',
                'accounting.bank.recon', 'accounting.tax.report', 'accounting.reports.view',
                'admin.masters.viewAny', 'admin.audit_logs.viewAny', 'admin.audit_logs.view',
            ],

            default => [],
        };
    }
}
