# Dwelly V2 — Property Management Platform
## Implementation Plan (v6 — Final)

> **Stack:** Laravel 13 · Filament 4/5 · MySQL/PostgreSQL · Spatie packages · tek2991/accounting plugin
> **Architecture:** DDD · Event-Driven · State Machines · Universal Task Engine · Unified Party Model · Geographic Regions · Generic Approval Engine · Hierarchical Authorization

---

## Resolved Architectural Decisions

| # | Decision | Resolution |
|---|---|---|
| D1 | Record deletion | **Never.** Lifecycle states only. Archive/Cancel in the UI. Soft-deletes where technically required. |
| D2 | Activity timeline | **Yes.** `spatie/laravel-activitylog` on all models + `property_activity_timeline` for business-meaningful events. |
| D3 | Media management | **Centralized.** One polymorphic `media` table. All entities use the `Mediable` trait. |
| D4 | Master data | **Configurable.** All operational lists are admin-managed lookup tables. Only logic-critical values are PHP enums. |
| D5 | Workflows | **Configurable engine.** Step sequences and gates are data, not code. `WorkflowEngine` reads from `workflow_definitions`. |
| D6 | Geographic expansion | **Hierarchical regions.** One company. Cities/states organized via a self-referential `regions` table. No multi-tenancy. No `entity_id`. |
| D7 | Document numbering | **Centralized `NumberingService`.** Human-readable, sequential, annually resetting. Atomic increment. |
| D8 | Configurable rules | **Yes, with versioning.** Settings affecting financial/contractual records are versioned. Historical records reference the version active at creation. |
| D9 | Property audits | **Structured templates.** Configurable `audit_templates` with per-section photo/video minimums. Submissions blocked until gates pass. |
| D10 | Document versioning | **Yes.** Every important document upload creates a new `document_version`. No overwriting. |
| D11 | Financial immutability | **Yes.** Finalized invoices, bills, and payouts are immutable. Corrections via credit/debit notes only. |
| D12 | Global search | **Yes.** Filament Global Search across all major entities including party by PAN, Aadhaar, and phone. |
| D13 | Scheduled jobs | **Centralized and documented.** Daily/monthly/annual cadences. All work queued. |
| D14 | Dashboard metrics | **Live in Phase 1.** Caching introduced only when performance requires it. |
| D15 | Permissions | **Four-layer hierarchical model.** `spatie/laravel-permission`. User → Role → Module Access → Capability. Geographic scope is an independent, additive fifth layer. |
| D16 | Event-driven | **Yes.** Major business events dispatch queued Laravel Events. Business logic stays decoupled. |
| D17 | Unified Party model | **Yes.** One `parties` table for individuals and organizations. Role-specific profile tables. Multiple simultaneous roles supported. |
| D18 | Module-level authorization | **Yes.** Every business module has a `module.access` permission as the first authorization gate. Users see only modules they are permitted to enter. |
| D19 | Permission naming convention | **`module.resource.action` dot-notation.** e.g. `property.access`, `accounting.invoices.post`, `maintenance.approve`. Business logic never checks role names. |
| D20 | Module UI visibility | **Filament navigation, widgets, and reports respect module access.** If a user lacks `module.access`, the entire module — including nav group, widgets, and direct URLs — is inaccessible. |
| D21 | Future module expansion | **No architectural changes required.** Adding CRM, HR, Payroll, Inventory, or any future module = introduce a new `module.access` permission. Existing auth framework needs no redesign. |
| D22 | Authorization integration standard | **Policies + `$user->can()` everywhere.** Role-name checks (`$user->role === 'manager'`) are prohibited. All protected actions verify capability permissions through Laravel Policies. |
| D23 | Accounting module readiness | **Permission namespace reserved from day one.** Accounting permissions are seeded even before the module is built, so they can be assigned to roles when the module activates without any structural change. |

---

## Open Question

> [!IMPORTANT]
> **Q — Geographic Assignment Granularity:** At what level are Operations Executives assigned — property-by-property, or by Area/Region? **Recommendation:** support both simultaneously. An executive is visible to a property if directly assigned to it, OR assigned to any ancestor region. This requires no schema change — both `property.assigned_executive_id` (direct) and `staff_regions` (regional) are already modeled.

---

## Domain Philosophy

The architecture has evolved beyond a single central aggregate. The updated principle:

> **Property is the primary operational aggregate for property-centric workflows. However, Parties, Agreements, Tasks, Financial Records, and Workflows are independent aggregates with their own lifecycle, history, and business rules. The architecture preserves clear domain boundaries while allowing aggregates to collaborate through Events and well-defined Services. No domain reaches directly into another domain's internals.**

Independent aggregates and their primary lifecycle owner:

| Aggregate | Lifecycle Owner |
|---|---|
| **Property** | Property Domain — status transitions, configuration, gallery |
| **Party** | People Domain — identity, KYC, role profiles |
| **Tenancy / Agreement** | Tenancy Domain — commercial terms, signatures, SD |
| **Task** | Task Domain — assignment, gates, escalation, comments |
| **MaintenanceRequest** | Maintenance Domain — owns Task as its operational driver |
| **Approval** | Approval Domain — generic, reused by any domain needing decisions |
| **RentCycle / Payout** | Finance Domain — rent tracking, payout calculation |
| **Communication** | Communication Domain — conversations and messages |
| **Notification** | Notification Domain — delivery and rendered history |
| **Workflow** | Workflow Domain — definitions consumed by Task Engine |

---

## Core Architectural Principles

1. Business records are never permanently deleted through the application
2. Historical data is never lost or overwritten
3. Workflows are configurable — step sequences and gates are data, not code
4. All operational master data is administrator-managed via Reference Data
5. All file handling uses a unified polymorphic media system
6. **Any configuration that directly affects financial calculations must be versioned — financial documents always reference the version active at the time they were generated**
7. Financial corrections occur through accounting adjustments (credit/debit notes), never by editing finalized records
8. The platform supports geographic expansion via hierarchical regions — not multi-tenancy
9. Events decouple business processes; all listeners are queued jobs
10. Permissions are capability-based; roles are collections of permissions; scope is geographic
11. Every person or organization exists only once in the system (Party model)
12. All major operational activity is fully auditable
13. All non-interactive, long-running, or scheduled work runs through Laravel Queues with defined priority tiers
14. All business calculations pass through the centralized `CalculationService`
15. Property Configuration and Property Operational State are separate, independently evolving concerns
16. Authorization is hierarchical — module access is verified before any capability check; role names are never compared directly in business logic

---

## Reference Data (Master Data)

All configurable lookup entities used by the application. Managed via the Settings panel. Adding a new value = one database row. Zero application code changes.

### Shared Lookup Structure

```sql
id           char(26) PRIMARY KEY   -- ULID
name         varchar(100) NOT NULL
slug         varchar(100) NOT NULL UNIQUE
description  text NULL
color        varchar(20) NULL        -- for UI badges
icon         varchar(50) NULL        -- icon slug
sort_order   int DEFAULT 0
is_active    boolean DEFAULT true
created_at   timestamp
updated_at   timestamp
```

### Complete Reference Data Catalogue

| Table | Purpose | Sample Values |
|---|---|---|
| `regions` | Geographic hierarchy | India → Assam → Kamrup Metro → Guwahati → Azara |
| `property_types` | Building classification | Apartment, House, Villa, Studio, PG |
| `bhk_types` | Bedroom configuration | Studio, 1BHK, 2BHK, 3BHK, 4BHK, 5BHK+ |
| `furnishing_types` | Furnishing level | Unfurnished, Semi-Furnished, Fully Furnished |
| `flooring_types` | Floor material | Vitrified, Marble, Tiles, Wood, Other |
| `amenity_types` | Property amenities | Parking, Lift, Gym, Pool, Security, EV Charging, Solar |
| `room_types` | Room categories | Hall, Dining, Kitchen, Bedroom, Bathroom, Balcony, Puja Room |
| `appliance_types` | Furniture/appliances | Fan, Light, Wardrobe, Bed, AC, TV, Fridge, Washing Machine |
| `establishment_types` | POI categories | Market, Hospital, School, ATM, Pharmacy, Metro, Airport |
| `vendor_trades` | Vendor specialization | Plumber, Electrician, Painter, Carpenter, AC Technician, Pest Control |
| `maintenance_categories` | Issue classification | Plumbing, Electrical, Carpentry, Painting, Appliance, Civil |
| `utility_types` | Utility billing types | Electricity (Submeter), Water (Society), Owner Reported |
| `task_types` | Task classification | Maintenance, Onboarding, Move-In, Move-Out, Renewal, Inspection, Follow-Up |
| `task_priorities` | Task urgency | Critical, High, Medium, Low |
| `payment_modes` | Payment methods | UPI, NEFT, IMPS, Cash, Cheque, Other |
| `media_collections` | Attachment categories | Gallery, Audit Section, Task Before/After, Owner KYC, Agreement, Receipt |
| `document_types` | Document categories | Government ID, PAN, Bank Cheque, Electricity Bill, Police Verification |
| `conversation_channel_types` | Communication channels | Owner ↔ Dwelly, Tenant ↔ Dwelly, Three-Way |
| `workflow_definitions` | Workflow templates | Tenant Move-In, Maintenance Standard, Owner Onboarding, Move-Out, Renewal |
| `audit_templates` | Inspection templates | Standard Move-In, Standard Move-Out, Routine Inspection |
| `sla_definitions` | Agreement variants | entity × furnishing × pricing model combinations |
| `notification_trigger_types` | Notification events | rent_due_day1, payout_released, maintenance_quoted, agreement_signed |
| `numbering_sequences` | Document number series | Property (GAU), Maintenance (MR), Invoice (INV), Agreement (AGR) |
| `approval_step_types` | Approval categories | Maintenance Quotation, Formal Notice, Payout Release, Expense |
| `party_relationship_types` | Relationship roles | Owner, Tenant, Vendor, Staff, Co-Owner |

> **Rule:** Every value in these tables can be added, renamed, reordered, or deactivated by an administrator without code changes. Values flagged `is_active = false` no longer appear in dropdowns but remain on historical records.

---

## Geographic Model

Dwelly is one company. Geographic hierarchy drives operational scope — not multi-tenancy.

### `regions`

```sql
id          char(26) PRIMARY KEY
name        varchar(100) NOT NULL
slug        varchar(100) NOT NULL UNIQUE
parent_id   char(26) REFERENCES regions(id) NULL
level       varchar(20)   -- 'country', 'state', 'district', 'city', 'area'
is_active   boolean DEFAULT true
```

**Example tree:**
```
India (country)
└── Assam (state)
    └── Kamrup Metro (district)
        └── Guwahati (city)
            ├── Beltola (area)
            ├── Six Mile (area)
            ├── Khanapara (area)
            └── Azara (area)
```

### `staff_regions`

```sql
id          char(26) PRIMARY KEY
user_id     char(26) REFERENCES users(id)
region_id   char(26) REFERENCES regions(id)
created_at  timestamp
```

**Access resolution:** A staff member can access a property if their region assignment covers the property's region or any ancestor region. Manager assigned to "Assam" sees all Guwahati properties. Executive assigned to "Khanapara" sees only Khanapara. Business Owner has no region restriction.

---

## Authorization Architecture (D15, D18–D23)

### The Four-Layer Model

```
Layer 1 — User
    The authenticated identity. Belongs to one or more Roles.

Layer 2 — Role
    Business function. A named collection of permissions.
    Examples: Business Owner, Operations Manager, Operations Executive, Accountant.

Layer 3 — Module Access
    The first gate. Determines which functional areas are visible and accessible.
    Permission name: {module}.access
    Examples: property.access, maintenance.access, accounting.access

Layer 4 — Capability Permissions
    Fine-grained operations within a module.
    Permission name: {module}.{resource}.{action} or {module}.{action}
    Examples: maintenance.approve, accounting.invoices.post, property.archive

Layer 5 (orthogonal) — Geographic Scope
    Evaluated alongside permissions. Filters records by region assignment.
    Does not override capability permissions; works in conjunction with them.
```

**Authorization flow for any request:**
```
1. Is user authenticated?            → 401 if not
2. Does user have {module}.access?   → 403 if not (module gate)
3. Does user have {capability}?      → 403 if not (action gate)
4. Is record within user's region?   → 403 if not (geographic gate)
5. Proceed
```

**Authorization rule — always use `can()`, never compare role names:**
```php
// ❌ PROHIBITED — couples logic to organizational structure
if ($user->role === 'manager') {
    // ...
}

// ✅ REQUIRED — capability check, independent of role name
if ($user->can('maintenance.approve')) {
    // ...
}

// ✅ REQUIRED — module gate before action gate
if ($user->can('maintenance.access') && $user->can('maintenance.approve')) {
    // ...
}
```

All authorization is implemented through **Laravel Policies** backed by `spatie/laravel-permission`. Policies are the single enforcement point — not middleware, not inline role checks.

---

### Permission Catalogue

All permissions follow `{module}.{action}` or `{module}.{resource}.{action}` dot-notation.
Seeded via `DatabaseSeeder` using `spatie/laravel-permission`.

#### Property Module
```
property.access
property.view              — view properties in own region
property.view_all          — view properties across all regions
property.create
property.update
property.archive
property.pricing.manage    — create/edit pricing versions
property.assignment.manage — assign executives
property.gallery.manage    — manage media and gallery
```

#### Party Module
```
party.access
party.view
party.create
party.update
party.kyc.manage           — manage KYC documents and verification
party.bank_accounts.manage
```

#### Agreement Module
```
agreement.access
agreement.view
agreement.create
agreement.update
agreement.sign.record      — record manual signature events
agreement.share            — mark copy as shared with parties
```

#### Maintenance Module
```
maintenance.access
maintenance.view
maintenance.create
maintenance.classify        — set fault classification and party
maintenance.quote.submit    — submit vendor cost
maintenance.approve         — owner approval decisions
maintenance.assign          — assign vendor
maintenance.complete        — mark work done
maintenance.verify          — cross-verify completion
maintenance.invoice         — raise accounting invoice
maintenance.dispute.manage
```

#### Task Module
```
task.access
task.view
task.create
task.assign
task.complete
task.escalate
task.cancel
task.comment
```

#### Finance Module
```
finance.access
finance.rent.view
finance.rent.confirm        — confirm rent payment
finance.payout.view
finance.payout.release
finance.payout.hold
finance.sd.view
finance.sd.manage           — create SD entries
finance.advance.manage
finance.annual_charge.manage
finance.risk_score.view
```

#### Utilities Module
```
utility.access
utility.electricity.view
utility.electricity.manage  — record readings, set rates
utility.water.view
utility.water.manage
```

#### Communication Module
```
communication.access
communication.view
communication.send
communication.archive
```

#### Document Module
```
document.access
document.view
document.upload
document.version.view       — access historical document versions
```

#### Reports Module
```
reports.access
reports.operational.view    — maintenance, task, property reports
reports.financial.view      — rent, payout, SD reports
reports.export
```

#### Workflow Module
```
workflow.access
workflow.definitions.view
workflow.definitions.manage — create/edit workflow definitions and steps
workflow.audit_templates.manage
```

#### Administration Module
```
administration.access
administration.users.view
administration.users.manage
administration.roles.manage
administration.reference_data.manage
administration.regions.manage
administration.numbering.manage
administration.configuration.manage  — versioned configuration settings
administration.notification_templates.manage
administration.migration.access      — migration wizard
```

#### Accounting Module (reserved — seeded from day one)
```
accounting.access
accounting.dashboard.view
accounting.accounts.view
accounting.accounts.manage
accounting.journals.view
accounting.journals.post
accounting.invoices.view
accounting.invoices.create
accounting.invoices.post
accounting.bills.view
accounting.bills.create
accounting.bills.post
accounting.payments.record
accounting.ledger.view
accounting.reports.view
accounting.period.close
accounting.settings.manage
accounting.credit_notes.create
accounting.debit_notes.create
```

#### Future Module Namespaces (reserved — not seeded until module ships)
```
crm.access  ·  crm.*
hr.access   ·  hr.*
payroll.access  ·  payroll.*
inventory.access  ·  inventory.*
procurement.access  ·  procurement.*
customer_portal.access  ·  customer_portal.*
vendor_portal.access  ·  vendor_portal.*
```

Adding a future module = seed its permissions and assign to roles. Zero changes to the auth framework.

---

### Module Permission Matrix

Shows module access + representative capabilities per role.

| Module | Permission | Business Owner | Manager | Executive | Accountant |
|---|---|:---:|:---:|:---:|:---:|
| **Property** | `.access` | ✅ | ✅ | ✅ | ✅ |
| | `.view` | ✅ | ✅ | ✅ (own region) | ✅ |
| | `.view_all` | ✅ | ✅ | ❌ | ✅ |
| | `.create` | ❌ | ✅ | ❌ | ❌ |
| | `.update` | ❌ | ✅ | ✅ | ❌ |
| | `.archive` | ❌ | ✅ | ❌ | ❌ |
| | `.pricing.manage` | ❌ | ✅ | ❌ | ❌ |
| **Party** | `.access` | ✅ | ✅ | ✅ | ❌ |
| | `.create / .update` | ❌ | ✅ | ✅ | ❌ |
| | `.kyc.manage` | ❌ | ✅ | ✅ | ❌ |
| **Agreement** | `.access` | ✅ | ✅ | ✅ | ❌ |
| | `.create / .update` | ❌ | ✅ | ✅ | ❌ |
| | `.sign.record` | ❌ | ✅ | ✅ | ❌ |
| **Maintenance** | `.access` | ✅ | ✅ | ✅ | ❌ |
| | `.create / .classify` | ❌ | ✅ | ✅ | ❌ |
| | `.approve` | ✅ | ✅ | ❌ | ❌ |
| | `.verify / .invoice` | ❌ | ✅ | ✅ | ❌ |
| **Task** | `.access` | ✅ | ✅ | ✅ | ❌ |
| | `.create / .assign` | ❌ | ✅ | ✅ (own) | ❌ |
| | `.escalate` | ❌ | ✅ | ✅ | ❌ |
| **Finance** | `.access` | ✅ | ✅ | ✅ | ✅ |
| | `.rent.confirm` | ❌ | ✅ | ✅ | ❌ |
| | `.payout.release` | ✅ | ✅ | ❌ | ❌ |
| | `.sd.manage` | ❌ | ✅ | ✅ | ❌ |
| | `.rent.view / .payout.view` | ✅ | ✅ | ❌ | ✅ |
| **Utilities** | `.access` | ✅ | ✅ | ✅ | ❌ |
| | `.manage` | ❌ | ✅ | ✅ | ❌ |
| **Communication** | `.access` | ✅ | ✅ | ✅ | ❌ |
| **Document** | `.access` | ✅ | ✅ | ✅ | ❌ |
| **Reports** | `.access` | ✅ | ✅ | ❌ | ✅ |
| | `.financial.view` | ✅ | ✅ | ❌ | ✅ |
| | `.export` | ✅ | ✅ | ❌ | ✅ |
| **Workflow** | `.access` | ✅ | ✅ | ❌ | ❌ |
| | `.definitions.manage` | ❌ | ✅ | ❌ | ❌ |
| **Administration** | `.access` | ✅ | ✅ | ❌ | ❌ |
| | `.users.manage` | ✅ | ❌ | ❌ | ❌ |
| | `.roles.manage` | ✅ | ❌ | ❌ | ❌ |
| | `.reference_data.manage` | ✅ | ✅ | ❌ | ❌ |
| | `.configuration.manage` | ✅ | ✅ | ❌ | ❌ |
| **Accounting** | `.access` | ✅ | ✅ | ❌ | ✅ |
| | `.invoices.post` | ❌ | ✅ | ❌ | ✅ |
| | `.period.close` | ✅ | ❌ | ❌ | ✅ |
| | `.reports.view` | ✅ | ✅ | ❌ | ✅ |

---

### Module UI Visibility Standard

Every module in the Filament panel is guarded by its `{module}.access` permission at multiple levels:

| Layer | Mechanism | Effect |
|---|---|---|
| **Navigation group** | Filament `navigationItems()->visible()` + Policy | Nav group hidden entirely |
| **Dashboard widgets** | Widget `canView()` method checks module access | Widget hidden from dashboard |
| **Resource pages** | Filament Resource `canAccess()` method | Page returns 403 |
| **Direct URL access** | Middleware `EnsureModuleAccess` on all resource routes | 403 even if URL is guessed |
| **Actions (table/form)** | Filament Action `visible()` + Policy `can()` | Action button hidden |
| **Bulk actions** | Same as actions | Hidden |
| **Reports** | Report `canView()` checks `reports.access` + subpermission | Report not listed or accessible |
| **API / background ops** | Policy applied in Job constructor | Job aborted with logged warning |

**Implementation pattern:**
```php
// In every Filament Resource:
public static function canAccess(): bool
{
    return auth()->user()->can(static::$moduleAccessPermission);
}

// In navigation registration:
NavigationItem::make('Accounting')
    ->visible(fn () => auth()->user()->can('accounting.access'));

// In widgets:
public static function canView(): bool
{
    return auth()->user()->can('finance.access');
}
```

---

### Accounting Module Readiness

The Accounting module (`tek2991/accounting` plugin) exists from day one. Its permissions are seeded immediately — even before all features are wired. This means:

- Roles can be assigned accounting permissions at any time without migrations
- The Accountant role receives `accounting.access` and relevant sub-permissions on first seed
- When a new accounting feature ships, its permission already exists — just enable it for the appropriate role

**Phase 1 Accountant seed:**
```
accounting.access
accounting.dashboard.view
accounting.accounts.view
accounting.invoices.view  /  .create  /  .post
accounting.bills.view     /  .create  /  .post
accounting.payments.record
accounting.ledger.view
accounting.reports.view
accounting.credit_notes.create
accounting.debit_notes.create
```

**Business Owner additionally receives:**
```
accounting.period.close
accounting.settings.manage
```

---

## Unified Party Model (D17 — Expanded)

### `parties`

| Column | Type | Notes |
|---|---|---|
| id | ulid PK | |
| party_type | enum | `individual`, `organization` |
| display_name | varchar | full name or company name |
| phone | varchar | primary phone |
| email | varchar | nullable |
| whatsapp_number | varchar | nullable |
| region_id | FK → regions | nullable — home/operating region |
| accounting_contact_id | varchar | FK → tek2991/accounting — auto-created in DB transaction |
| created_at, updated_at | timestamps | |

### `party_individuals`

| Column | Type | Notes |
|---|---|---|
| id | ulid PK | |
| party_id | FK → parties | unique |
| first_name | varchar | |
| last_name | varchar | nullable |
| date_of_birth | date | nullable |
| gender | varchar | nullable |
| aadhaar_number | varchar | nullable |
| pan_number | varchar | nullable |
| voter_id | varchar | nullable |
| address_line_1 | varchar | nullable |
| address_line_2 | varchar | nullable |
| city | varchar | nullable |
| state | varchar | nullable |
| pincode | varchar | nullable |

### `party_organizations`

| Column | Type | Notes |
|---|---|---|
| id | ulid PK | |
| party_id | FK → parties | unique |
| legal_name | varchar | |
| gstin | varchar | nullable |
| pan | varchar | nullable |
| cin | varchar | nullable |
| registered_address | text | nullable |
| contact_person_name | varchar | nullable |
| contact_person_phone | varchar | nullable |

### `owner_profiles`

| Column | Type | Notes |
|---|---|---|
| id | ulid PK | |
| party_id | FK → parties | unique |
| bank_account_name | varchar | nullable |
| bank_account_number | varchar | nullable |
| bank_ifsc | varchar | nullable |
| default_bank_account_id | FK → party_bank_accounts | nullable |
| notification_preference | enum | `all`, `summary`, `none` |
| payout_method | enum | `per_unit`, `combined` |

### `tenant_profiles`

| Column | Type | Notes |
|---|---|---|
| id | ulid PK | |
| party_id | FK → parties | unique |
| emergency_contact_name | varchar | nullable |
| emergency_contact_phone | varchar | nullable |
| occupation | varchar | nullable |
| employer_name | varchar | nullable |
| monthly_income | decimal(12,2) | nullable — for screening |

*(Risk scores are never stored here — see `tenant_risk_snapshots`)*

### `vendor_profiles`

| Column | Type | Notes |
|---|---|---|
| id | ulid PK | |
| party_id | FK → parties | unique |
| vendor_trade_id | FK → vendor_trades (lookup) | |
| gstin | varchar | nullable |
| bank_account_name | varchar | nullable |
| bank_account_number | varchar | nullable |
| bank_ifsc | varchar | nullable |
| service_regions | json | array of region_ids — areas the vendor operates in |
| rating | decimal(3,2) | nullable — computed from completed job feedback |
| total_jobs_completed | int | default 0 |
| notes | text | nullable |
| is_preferred | boolean | default false |

### `staff_profiles`

| Column | Type | Notes |
|---|---|---|
| id | ulid PK | |
| party_id | FK → parties | unique |
| user_id | FK → users | unique |
| employee_code | varchar | nullable |
| department | varchar | nullable |
| designation | varchar | nullable |
| joining_date | date | nullable |
| reporting_to | FK → users | nullable |

### `party_bank_accounts`

| Column | Type | Notes |
|---|---|---|
| id | ulid PK | |
| party_id | FK → parties | |
| account_name | varchar | |
| account_number | varchar | |
| ifsc_code | varchar | |
| bank_name | varchar | nullable |
| is_primary | boolean | default false |
| is_verified | boolean | default false |
| created_at, updated_at | timestamps | |

> A party can hold any combination of roles. John can own Property A, rent Property B, and be a vendor — one `parties` record, three profile rows, one accounting contact, one KYC document set.

---

## Property — Configuration vs. Operational State

These are separate concerns that evolve independently.

### Property Configuration
Long-lived settings that define what the property *is*. Changes infrequently.

```
properties table:
    building_name, address hierarchy, region_id,
    bhk_type, property_type, floor, total_floors, floor_space_sqft,
    flooring, furnishing

property_amenities (pivot)       ← what facilities does it have?
property_rooms (pivot)           ← what rooms and how many?
property_furniture (pivot)       ← what items are included?
property_pricing_versions        ← what does it cost? (versioned)
property_utility_config          ← how are utilities billed? (see below)
```

### Property Operational State
Changes frequently as business events occur. Driven by the state machine.

```
properties.status                ← Onboarding | Vacant | Occupied | Archived
properties.assigned_executive_id ← current assignment (history in property_assignments)
properties.available_from        ← listing date
properties.is_promoted           ← listing status
```

### `property_utility_config`

Separates "how this property's utilities are billed" from the property's identity.

| Column | Type | Notes |
|---|---|---|
| id | ulid PK | |
| property_id | FK | unique |
| electricity_billing | enum | `submeter`, `included_in_rent`, `not_applicable` |
| water_billing | enum | `tenant_pays_directly`, `society_split`, `owner_reported`, `not_applicable` |
| society_fee_model | enum | `tenant_pays_directly`, `bundled_in_payout`, `dwelly_advances` |
| has_owner_variable_charges | boolean | default false |
| variable_charge_description | varchar | nullable |
| created_at, updated_at | timestamps | |

---

## Property Assignment History

Operational responsibility changes over time. The current `assigned_executive_id` captures only the present. The full history lives in `property_assignments`.

### `property_assignments`

| Column | Type | Notes |
|---|---|---|
| id | ulid PK | |
| property_id | FK → properties | |
| assigned_to | FK → users | |
| assignment_role | varchar | `primary_executive`, `secondary_executive`, `manager_override` |
| effective_from | date | |
| effective_to | date | nullable — null means currently active |
| reason | text | nullable |
| assigned_by | FK → users | |
| created_at | timestamp | — no updated_at, immutable log |

**Usage:**
- `properties.assigned_executive_id` remains for fast query access (current state)
- When exec changes: close current assignment (`effective_to = today`), create new assignment row, update `assigned_executive_id`
- Enables: workload history, exec performance metrics, handover audit trail
- Event: `PropertyAssignmentChanged` → `LogPropertyActivity`

---

## Generic Approval Engine

A reusable approval system used by any domain that requires a formal decision. Replaces per-module approval logic duplication.

### `approvals`

| Column | Type | Notes |
|---|---|---|
| id | ulid PK | |
| reference_number | varchar | system-generated |
| approvable_type | varchar | polymorphic subject — MaintenanceRequest, OwnerPayout, RentCycle, Expense |
| approvable_id | ulid | |
| property_id | FK | |
| approval_step_type | FK → approval_step_types (lookup) | e.g. `maintenance_quotation`, `formal_notice`, `payout_release` |
| requested_by | FK → users | |
| required_role | varchar | which permission/role can decide |
| assigned_approver | FK → users | nullable — specific person if required |
| status | enum | `pending`, `approved`, `rejected`, `escalated`, `expired` |
| decision | enum | `approved`, `rejected`, `conditional` | nullable |
| remarks | text | nullable |
| conditions | text | nullable — if decision = conditional |
| decided_by | FK → users | nullable |
| decided_at | timestamp | nullable |
| expires_at | timestamp | nullable — auto-reject after expiry |
| created_at, updated_at | timestamps | |

### `approval_steps` (for multi-step approval chains)

| Column | Type | Notes |
|---|---|---|
| id | ulid PK | |
| approval_id | FK → approvals | |
| step_number | int | |
| required_role | varchar | |
| assigned_approver | FK → users | nullable |
| status | enum | `pending`, `approved`, `rejected`, `skipped` |
| decision | enum | nullable |
| remarks | text | nullable |
| decided_by | FK → users | nullable |
| decided_at | timestamp | nullable |
| created_at | timestamp | |

**Usage across domains:**
```php
// Maintenance quotation approval:
ApprovalEngine::request(
    subject: $maintenanceRequest,
    type: 'maintenance_quotation',
    requiredRole: 'approve_maintenance',
    property: $property,
    expiresIn: '72 hours'
);

// Formal notice (rent Day 10) — manager must approve:
ApprovalEngine::request(
    subject: $rentCycle,
    type: 'formal_notice',
    requiredRole: 'approve_maintenance', // reuses same engine
    property: $property,
);

// Payout release:
ApprovalEngine::request(
    subject: $ownerPayout,
    type: 'payout_release',
    requiredRole: 'release_payouts',
    property: $property,
);
```

When an approval is decided, `ApprovalDecided` event fires → domain-specific listener handles the outcome. The Approval Engine itself has no domain knowledge.

---

## Centralized Calculation Service

All business calculations pass through `CalculationService`. No calculation logic is scattered across models, controllers, or listeners.

```php
// Domain/Finance/Services/CalculationService.php

class CalculationService {

    // Rent calculations
    public function proratedRent(int $rent, Carbon $moveInDate): Money;
    public function dwellyFee(Money $rent, float $feePercent): Money;

    // Electricity
    public function electricityBill(float $units, float $ratePerUnit): Money;
    public function electricityUnits(float $opening, float $closing): float;

    // Water
    public function waterBillShare(Money $totalBill, int $flats, string $method, ?float $area = null): Money;

    // Payout
    public function netPayout(
        Money $rentCollected,
        Money $dwellyFee,
        Money $societyFeeCharged,
        Money $ownerVariableCharges,
        Money $invoiceDeductions,
        Money $advanceRecoveries
    ): Money;

    // Security Deposit
    public function sdBalance(Collection $entries): Money;
    public function noticePeriodAdjustment(Money $rent, int $noticeMonths): Money;

    // Vendor quote
    public function dwellyQuotedAmount(Money $vendorCost, float $marginRate): Money;

    // Tenant risk
    public function onTimePaymentRate(Collection $rentCycles): float;
    public function averageDaysLate(Collection $rentCycles): float;
    public function riskTier(float $onTimeRate): string;
}
```

**All monetary values use `brick/money` (`Money` objects) — already a dependency of `tek2991/accounting`.**

Benefits:
- Single place to update when tax rules, fee structures, or formulas change
- Fully unit-testable in isolation from Eloquent
- No duplicated formula logic across domains

---

## Database Schema — Complete (v5)

*(Only tables new or significantly changed from v4 are shown in full. Tables unchanged from v4 are summarized.)*

### Geographic
```sql
regions (id, name, slug, parent_id, level, is_active)
staff_regions (id, user_id, region_id, created_at)
```

### Party
```sql
parties (id, party_type, display_name, phone, email, whatsapp_number, region_id, accounting_contact_id)
party_individuals (id, party_id, first_name, last_name, date_of_birth, gender, aadhaar_number, pan_number, voter_id, address fields)
party_organizations (id, party_id, legal_name, gstin, pan, cin, registered_address, contact details)
owner_profiles (id, party_id, default_bank_account_id, notification_preference, payout_method)
tenant_profiles (id, party_id, emergency_contact_name, emergency_contact_phone, occupation, employer_name, monthly_income)
vendor_profiles (id, party_id, vendor_trade_id, gstin, bank details, service_regions [json], rating, total_jobs_completed, is_preferred)
staff_profiles (id, party_id, user_id, employee_code, department, designation, joining_date, reporting_to)
party_bank_accounts (id, party_id, account_name, account_number, ifsc_code, bank_name, is_primary, is_verified)
```

### Property
```sql
properties (
    id, code [GAU-0042], region_id,
    status [PropertyStatus state machine],
    building_name, address_line_1, address_line_2, locality, area,
    city, district, state, country, pincode, landmark, latitude, longitude,
    bhk_type_id→bhk_types, property_type_id→property_types,
    floor, total_floors, floor_space_sqft,
    flooring_type_id→flooring_types, furnishing_type_id→furnishing_types,
    pricing_model [PricingModel enum],
    is_promoted, available_from,
    assigned_executive_id→users,
    archived_at, archived_reason
)

property_pricing_versions (
    id, property_id, effective_from, effective_to NULL,
    rent, security_deposit, society_fee, pricing_model [snapshot],
    fee_percentage, booking_amount,
    notes, created_by, created_at
)

property_utility_config (
    id, property_id unique,
    electricity_billing, water_billing, society_fee_model,
    has_owner_variable_charges, variable_charge_description
)

property_amenities (id, property_id, amenity_type_id, notes)
property_rooms (id, property_id, room_type_id, count)
property_furniture (id, property_id, appliance_type_id, count)
```

### Property Assignment History *(new)*
```sql
property_assignments (
    id, property_id, assigned_to→users, assignment_role,
    effective_from, effective_to NULL,
    reason, assigned_by→users, created_at
)
```

### Shared Establishments
```sql
establishments (id, name, establishment_type_id, address, city, latitude, longitude, google_place_id)
property_establishments (id, property_id, establishment_id, distance_km, travel_time_minutes, remarks)
```

### Workflow Engine
```sql
workflow_definitions (id, name, slug, description, is_active)
workflow_steps (id, workflow_definition_id, step_number, name, description, gate_condition_class, assignable_role, requires_media, requires_approval, approval_role, auto_advance, sla_hours)
workflow_step_transitions (id, from_step_id, to_step_id, condition)
```

### Numbering
```sql
numbering_sequences (id, entity_type unique, prefix, include_year, last_sequence, year, pad_length)
```

### Generic Approval Engine *(new)*
```sql
approvals (
    id, reference_number, approvable_type, approvable_id,
    property_id, approval_step_type_id→lookup,
    requested_by→users, required_role,
    assigned_approver→users, status, decision,
    remarks, conditions, decided_by→users, decided_at, expires_at
)
approval_steps (
    id, approval_id, step_number, required_role,
    assigned_approver, status, decision,
    remarks, decided_by, decided_at, created_at
)
```

### Onboarding
```sql
onboarding_checklists (id, property_id unique, all boolean gates, sla_sent_at, completed_at)
```

### Tenancy

#### Agreement Snapshots (D — Formalized)

Every `agreement` and `tenancy` captures an immutable snapshot of **all commercial terms at the moment of signing**. Future property pricing changes never affect historical agreements.

```sql
tenancies (
    id, reference_number,
    property_id, tenant_party_id,
    status [TenancyStatus],
    pricing_version_id→property_pricing_versions,   -- reference to version used

    -- SNAPSHOTS — immutable at signing:
    rent_amount,
    security_deposit_amount,
    security_deposit_held_by,
    sd_instalments,
    management_fee_percentage,                       -- snapshot of fee% at signing
    management_fee_config_version,                   -- FK → configuration_settings version
    notice_period_months,
    utility_rules [json],                            -- snapshot of utility_config at signing
    applicable_pricing_model,                        -- snapshot

    start_date, end_date, notice_given_date,
    move_in_date, move_out_date,
    prorated_first_month_rent [snapshot],
    booking_amount_paid [snapshot],
    renewal_task_id→tasks
)

agreements (
    id, reference_number,
    tenancy_id, property_id,
    state [AgreementState],
    sla_variant_id, sla_version_snapshot [json],     -- full SLA terms at signing
    signature_provider [manual|signdesk|digilocker|docusign],
    owner_signed_at [snapshot], tenant_signed_at [snapshot],
    signed_copy_shared_at, esign_reference
)
```

### Audit Templates & Inspections
*(unchanged from v4)*
```sql
audit_templates (id, name, audit_type, is_default, is_active)
audit_template_sections (id, audit_template_id, name, sort_order, min_photos, min_videos, requires_notes, checklist_items [json])
property_audits (id, reference_number, property_id, tenancy_id, template_id, audit_type, task_id, status, conducted_by, conducted_at, shared/acknowledged timestamps)
property_audit_sections (id, property_audit_id, template_section_id, notes, condition_rating, is_complete)
-- Media per section via Mediable.
```

### Universal Task Engine
```sql
tasks (
    id, reference_number, property_id, region_id,
    taskable_type, taskable_id,
    workflow_step_id→workflow_steps,
    task_type_id, task_priority_id,
    title, description,
    assigned_to→users, assigned_by, assigned_at,
    due_date, sla_due_at,
    state [TaskState], step_number,
    gate_condition_class, gate_passed_at,
    completed_at, cancelled_at, cancelled_reason,
    created_by
)
task_comments (id, task_id, property_id, author_id, body, created_at)
task_escalations (id, task_id, escalated_by, escalated_to, reason, created_at)
```

### Maintenance
```sql
maintenance_requests (
    id, reference_number [MR-2026-000145],
    property_id, tenancy_id, task_id,
    maintenance_category_id, request_type_id,
    trigger, title, description,
    state [MaintenanceState],
    fault_classification, fault_party,
    vendor_party_id,
    vendor_cost,
    dwelly_margin_rate_snapshot [snapshot],
    dwelly_quoted_amount [snapshot],
    owner_decision, owner_decision_at,
    completion_verified_by, completion_verified_at,
    accounting_invoice_id,
    group_id, is_group_notification_sent
)
maintenance_phases (id, request_id, phase_number, name, description, estimated_cost, owner briefing fields, status)
maintenance_disputes (id, request_id, counter_evidence_notes, resolution, split_percentage_tenant, resolved_by, resolved_at)
```

### Finance

#### Configuration Versioning
```sql
configuration_settings (
    id, key, value, effective_from, effective_to NULL,
    set_by→users, notes, created_at
    -- No updated_at. Append-only version history.
)
```

```sql
rent_cycles (id, reference_number, property_id, region_id, tenancy_id, billing_month, rent_due [snapshot], state, all reminder flags)
rent_payments (id, reference_number, rent_cycle_id, property_id, tenancy_id, amount, payment_date, payment_mode_id, transaction_reference, confirmed_by, confirmed_at, receipt_sent_at, accounting_transaction_id)
owner_payouts (id, reference_number, property_id, region_id, owner_party_id, billing_month, all component snapshots at release, net_payout [snapshot at release], status, hold_reasons [json], released_by, released_at, payment_reference, accounting_transaction_id)
advance_payments (id, property_id, owner_party_id, description, amount, paid_date, recovery_month, recovered_in_payout_id)
annual_charges (id, property_id, description, amount, scheduled_month, status, alert_sent)
tenant_risk_snapshots (id, party_id, snapshot_at, on_time_rate, avg_days_late, split_payment_count, risk_tier)
```

### Security Deposit Ledger
```sql
security_deposit_entries (
    id, tenancy_id, property_id,
    entry_type, amount [+/-], instalment_number, due_date, paid_date,
    notes, created_by, created_at
    -- No updated_at. Append-only.
)
-- Balance = SUM(amount). Never stored.
```

### Utilities
```sql
electricity_rate_versions (id, property_id, rate_per_unit, effective_from, effective_to, created_by)
electricity_readings (id, reference_number, property_id, tenancy_id, billing_month, opening_reading, closing_reading, units_consumed [snapshot], rate_version_id, rate_per_unit_snapshot [snapshot], bill_amount [snapshot, locked], bill_shared_at, accounting_invoice_id)
water_bills (id, reference_number, property_id, billing_month, total_bill_amount, split_method, number_of_flats, per_flat_amount [snapshot], created_by)
```

### Polymorphic Media
```sql
media (id, mediable_type, mediable_id, property_id, media_collection_id, disk, path, thumbnail_path, original_filename, mime_type, size_bytes, is_cover, is_hidden, sort_order, caption, metadata [json], uploaded_by, created_at)
```

### Document Versions
```sql
document_versions (id, documentable_type, documentable_id, property_id, document_type_id→lookup, version_number, label, path, disk, original_filename, mime_type, size_bytes, is_current, uploaded_by, created_at)
```

### Communication
```sql
conversations (id, property_id, tenancy_id, channel_type_id→lookup, status)
conversation_participants (id, conversation_id, participant_type, participant_id, notification_preference)
messages (id, conversation_id, property_id, sender_type, sender_id, body, channel_ref, delivery_status, linked_record_type, linked_record_id, created_at)
-- Attachments via Mediable. Follow-ups = Task (type: follow_up) with taskable → Message.
```

### Notification System (with Rendered History)

Notification templates evolve. The rendered content at time of delivery is preserved so historical communications can always be reconstructed exactly as recipients received them.

```sql
notification_templates (
    id, trigger_event, channel [whatsapp|email|in_app|sms],
    subject, body_template, whatsapp_template_name,
    version int,                     -- template version number
    is_active
)
```

```sql
notifications (
    id, notifiable_type, notifiable_id,
    property_id,
    trigger_event,
    template_id→notification_templates,
    template_version_snapshot int,   -- which template version was used
    channel,
    title,
    rendered_subject text NULL,      -- fully rendered subject at delivery time
    rendered_body text,              -- fully rendered body at delivery time [SNAPSHOT]
    delivery_status [pending|sent|delivered|read|failed],
    provider_message_id varchar NULL,-- WhatsApp SID / email ID
    provider_response json NULL,     -- raw provider delivery response
    linked_record_type, linked_record_id,
    sent_at timestamp NULL,
    read_at timestamp NULL,
    created_at timestamp
)
```

> `rendered_body` is the exact message content delivered to the recipient, with all variables substituted. If the template changes tomorrow, historical notification records still show what was actually sent.

### Activity Timeline
```sql
property_activity_timeline (
    id, property_id, region_id,
    event_type, icon, color, title, description,
    actor_type, actor_id, actor_name [snapshot],
    linked_record_type, linked_record_id,
    occurred_at, created_at
    -- Append-only. No updated_at.
)
```

### Migration Wizard
```sql
migration_imports (id, name, status, source_file_path, row_count, mapped_columns [json], validation_errors [json], imported_count, rollback_at, created_by)
migration_import_rows (id, import_id, row_number, raw_data [json], transformed_data [json], status, validation_messages [json], imported_record_type, imported_record_id)
```

---

## Queue Architecture with Priority Tiers

All non-interactive work is queued. Queue workers run separately per priority tier.

### Priority Tiers

| Tier | Queue Name | Contents |
|---|---|---|
| **Critical** | `critical` | Payment processing, accounting transactions, approval decisions, security deposit entries |
| **High** | `high` | Notifications (all channels), rent reminder escalations, payout release events |
| **Medium** | `medium` | PDF generation, audit reports, owner statements, scheduled calculations (payout, risk score) |
| **Low** | `low` | Image optimization, video thumbnail generation, analytics updates, cache refresh, migration import rows |

### Queue Workers (recommended configuration)

```bash
php artisan queue:work --queue=critical --tries=3 --timeout=30
php artisan queue:work --queue=high,critical --tries=3 --timeout=60
php artisan queue:work --queue=medium --tries=3 --timeout=120
php artisan queue:work --queue=low --tries=5 --timeout=300
```

### Listener Queue Assignment

```php
// Critical
CreateAccountingTransaction   → 'critical'
ProcessRentPayment            → 'critical'
RecordSDEntry                 → 'critical'
ProcessApprovalDecision       → 'critical'

// High
SendRentReceiptNotification   → 'high'
NotifyResponsibleParty        → 'high'
SendPayoutNotification        → 'high'
SendEsignReminder             → 'high'
BlockPayoutIfInvoicePending   → 'high'

// Medium
RecalculateOwnerPayout        → 'medium'
SnapshotTenantRiskScore       → 'medium'
GenerateAuditReport           → 'medium'
GenerateOwnerStatement        → 'medium'
ApplyDwellyMarginSnapshot     → 'medium'

// Low
ProcessMediaThumbnail         → 'low'
OptimizeUploadedImage         → 'low'
LogPropertyActivity           → 'low'
RunMigrationImportRow         → 'low'
```

---

## Centralized Calculation Service

```
Domain/Finance/Services/CalculationService.php

Methods:
  proratedRent(rent, moveInDate) → Money
  dwellyFee(rent, feePercent) → Money
  electricityUnits(opening, closing) → float
  electricityBill(units, ratePerUnit) → Money
  waterBillShare(totalBill, flats, method, area?) → Money
  netPayout(rentCollected, dwellyFee, societyFee, variableCharges, invoiceDeductions, advanceRecoveries) → Money
  sdBalance(entries) → Money
  noticePeriodSdAdjustment(rent, noticeMonths) → Money
  dwellyQuotedAmount(vendorCost, marginRate) → Money
  onTimePaymentRate(rentCycles) → float
  averageDaysLate(rentCycles) → float
  riskTier(onTimeRate) → string
```

All monetary types use `brick/money` for precision arithmetic. No floats for financial values.

---

## Filament Panel — Menu

Single panel: `/admin`. Role + geographic scope applied as Filament middleware.
*Note: Top-level navigation groups are guarded by their respective `{module}.access` permission.*

```
📊 Dashboard
   ├── [BO/Manager]  Portfolio KPIs
   ├── [Executive]   My Tasks + Properties
   └── [Accountant]  Financial Summary

🏠 Properties  [property.access]
   ├── All Properties
   ├── Onboarding
   ├── Vacant
   ├── Occupied
   └── Archived

👥 Parties  [party.access]
   ├── All Individuals
   ├── All Organizations
   ├── Owners
   ├── Tenants
   └── Vendors

📋 Tasks & Operations  [task.access / maintenance.access]
   ├── All Tasks
   ├── My Tasks
   ├── Awaiting Approval
   ├── Overdue / SLA Breached
   └── Maintenance Requests
        ├── Open
        ├── Awaiting Approval
        ├── In Progress
        └── Completed

💰 Financials  [finance.access / utility.access]
   ├── Rent Collection (Current Month / History)
   ├── Owner Payouts (Pending / Released)
   ├── Security Deposits
   ├── Advances & Annual Charges
   └── Utility Billing
        ├── Electricity Readings
        └── Water Bills

📄 Agreements & Documents  [agreement.access / document.access]
   ├── Active Agreements
   ├── Pending Signatures
   ├── Document Vault
   └── SLA Variants

💬 Communications  [communication.access]
   └── Property Message Hub

📦 Accounting  [accounting.access]  [tek2991/accounting plugin]

⚙️ Settings
   ├── Company Profile
   ├── Reference Data         ← all lookup tables in one place
   │    ├── Geographic Regions
   │    ├── Property Types / BHK / Furnishing / Amenities / Rooms / Appliances
   │    ├── Vendor Trades / Maintenance Categories
   │    ├── Task Types / Priorities
   │    ├── Establishment Types
   │    └── Payment Modes / Document Types / Communication Types
   ├── Versioned Configuration (margin rate, fee %, late payment rules, etc.)
   ├── Workflow Definitions
   ├── Audit Templates
   ├── Numbering Sequences
   ├── Notification Templates
   ├── Approval Step Types
   ├── Migration Wizard
   └── Users, Roles & Permissions
```

---

## Property Detail — Tabbed Hub

| Tab | Contents | Access |
|---|---|---|
| **Overview** | Status, address, map, region, current exec + assignment history link, pricing version | All |
| **Timeline** | Chronological activity feed | All |
| **Configuration** | Amenities, rooms, furniture, utility config, nearby establishments | Manager / Exec |
| **Gallery** | Drag-drop photo/video manager (sort, cover, hide) | Manager / Exec |
| **Pricing History** | All pricing versions with effective dates | Manager / BO |
| **Assignment History** | Full exec assignment log | Manager / BO |
| **Onboarding** | Checklist progress bar with gate status | Manager / Exec |
| **Tenancy** | Current tenancy + full history | Manager / BO |
| **Agreement** | Agreement state + signature tracking + document versions | Manager / BO |
| **Audits** | All structured audit records with section-level media | Manager / Exec |
| **Tasks** | All tasks (filterable by type/state), with approval status | Exec / Manager |
| **Maintenance** | Pipeline state view | Exec / Manager |
| **Rent & Payouts** | Monthly ledger + payout breakdown | Manager / BO / Accountant |
| **Security Deposit** | Immutable ledger + running balance | Manager / BO |
| **Utilities** | Electricity + water bills | Manager / Exec |
| **Communications** | Three-channel conversation hub | Manager / Exec |
| **Documents** | Versioned document vault by category | Manager / Exec |

---

## Workflow Implementations

### W1 — Owner Onboarding

```
Manager creates property
    → NumberingService::generate('property', region: 'GAU') → "GAU-0042"
    → property_utility_config row created (defaults)
    → WorkflowEngine::initiate('owner_onboarding', $property)
    → SlaVariantSelectorService auto-selects SLA

Event: PropertyCreated (listeners queued on 'high')
    → CreateOnboardingChecklist
    → PropertyAssignment::create() — initial exec assignment logged
    → SendOwnerWelcomeNotification → NotificationService (renders + stores in notifications)

Tasks generated by WorkflowEngine (from workflow_definition):
    [ ] Govt ID uploaded       gate: DocumentVersion for document_type='govt_id'
    [ ] PAN uploaded           gate: DocumentVersion for document_type='pan'
    [ ] Electricity Bill       gate: DocumentVersion
    [ ] Cancelled Cheque       gate: DocumentVersion
    [ ] Contact confirmed      gate: manual completion
    [ ] SLA sent + signed      gate: Agreement.state == CopyShared

All tasks complete →
Event: OnboardingCompleted
    → TransitionPropertyState(Vacant)  [GATE enforced]
    → CreateAccountingContact          [DB transaction, 'critical' queue]
    → LogPropertyActivity              ['low' queue]
```

---

### W2 — Tenant Move-In

```
WorkflowEngine::initiate('tenant_move_in', $tenancy)
    → PropertyAudit created from default 'move_in' template
    → 8 Tasks created from workflow_definition steps

Step 1: Structured Audit
    → Per AuditSection: photos + video uploaded
    → Gate: AuditCompleteGateChecker (all sections ≥ min_photos + min_videos)
    → Audit status → completed

Step 2: Audit Shared + Acknowledged
    → Audit report PDF generated ('medium' queue)
    → Sent via NotificationService ('high' queue) → owner + tenant
    → Gate: owner_acknowledged_at + tenant_acknowledged_at set

Step 3: Issues Classified
    → Gate: all MaintenanceRequests have fault_classification set

Step 4: Vendor Quotes
    → Gate: vendor_cost + media on all solvable issues

Step 5: Owner Approval
    → ApprovalEngine::request(subject: $maintenanceRequest, type: 'maintenance_quotation')
    → Gate: approval.status == 'approved' on all issues

Step 6: Work + Completion Media
    → Gate: CompletionMediaGateChecker

Step 7: Cross-Verification
    → Gate: completion_verified_at set

Step 8: Invoices
    → AccountingBridgeService::createInvoice() → 'critical' queue
    → Gate: accounting_invoice_id set

→ Tenancy status → Active
→ Property status → Occupied

Event: TenancyStarted
    → CreateRentCycles, ScheduleRenewalTask,
      CreateTenantAccountingContact, LogPropertyActivity
```

---

### W3 — Maintenance

```
Issue reported + media [Gate: media required]
    → MaintenanceRequest (state: Reported)
    → NumberingService → "MR-2026-000146"
    → Task from WorkflowEngine

state → Quoted
    → CalculationService::dwellyQuotedAmount(vendorCost, margin)
         margin = ConfigurationSetting::currentValue('dwelly_margin_rate')
    → dwelly_margin_rate_snapshot stored [snapshot]
    → dwelly_quoted_amount stored [snapshot]

state → OwnerApprovalPending
    → ApprovalEngine::request(type: 'maintenance_quotation', expiresIn: '72 hours')
    → Approval notification rendered + stored

Approval decided → Event: ApprovalDecided → 'critical' queue
    → MaintenanceRequest.owner_decision updated
    → state transitions accordingly

state → Completed → Invoiced
    → AccountingBridgeService::createInvoice() → 'critical' queue
    → BlockPayoutIfDeductionPending → 'high' queue
```

---

### W4 — Rent Collection

```
Scheduler daily 08:00 → SendRentRemindersCommand
    Day 1–15 escalation via Events ('high' queue listeners)
    Day 10: ApprovalEngine::request(type: 'formal_notice') — manager must approve

Payment confirmed:
    CalculationService not needed here — amount is as-received
    Event: RentPaymentConfirmed
        UpdateRentCycleState           → 'high'
        RecalculateOwnerPayout         → 'medium' (CalculationService::netPayout)
        SnapshotTenantRiskScore        → 'medium' (CalculationService::onTimePaymentRate etc.)
        SendRentReceiptNotification    → 'high' (renders + stores in notifications)
        CreateAccountingTransaction    → 'critical'
        LogPropertyActivity            → 'low'
```

---

### W5 — Tenant Move-Out

```
Notice given → tenancy.notice_given_date set
    → CalculationService::noticePeriodSdAdjustment(rent, months)
    → SecurityDepositEntry(type: notice_adjustment) [immutable]
    → WorkflowEngine::initiate('tenant_move_out', $tenancy)

4 Tasks / Phases (as before)

Phase 2: Damage assessment
    → AuditComparison page: move-in audit vs move-out audit side-by-side
    → ApprovalEngine::request(type: 'damage_assessment_agreement') — both parties
    → CalculationService::sdBalance(entries) shown live

Phase 3: Settlement
    → SecurityDepositEntry rows (damage_deduction, refund)
    → All calculations via CalculationService
    → AccountingBridgeService → 'critical' queue

Phase 4: Closure
    → Final statement PDF → 'medium' queue
    → Tenancy → Ended, Property → Vacant
    → LogPropertyActivity → 'low'
```

---

### W6 — Archive Property

```
ArchivePropertyAction::execute($property, $reason)
    Gates (all enforced by ArchiveGuard):
    [GATE] all tasks Completed or Cancelled
    [GATE] all payouts Released or written off
    [GATE] CalculationService::sdBalance() == 0 for all tenancies
    [GATE] no active tenancy

    → property.status → Archived
    → PropertyAssignment closed (effective_to = today)
    → All conversations archived
    → LogPropertyActivity → 'low'
```

---

## Scheduled Job Architecture

### Daily (08:00)
```php
SendRentRemindersCommand       // Day 1–15 escalation events
ProcessRenewalRemindersCommand // 60-day agreement renewal task creation
SendEsignRemindersCommand      // 48hr / 96hr unsigned nudges
ProcessTaskSlaCommand          // flag overdue tasks
FlagAnnualChargesCommand       // 30-day advance notice
ProcessExpiredApprovalsCommand // auto-reject expired approvals
```

### Monthly (1st, 06:00)
```php
GenerateRentCyclesCommand         // create rent_cycle per active tenancy
GenerateSocietyChargesCommand     // schedule society fee tasks
GenerateOwnerStatementsCommand    // prepare payout summaries
```

### Annual (1st Jan, 00:00)
```php
ResetNumberingSequencesCommand    // reset all annual sequences
LeaseExpiryReviewCommand          // flag agreements expiring in current year
```

---

## Global Search

| Model | Searchable Fields |
|---|---|
| Property | code, building_name, full address fields, locality |
| Party (Individual) | display_name, phone, email, aadhaar_number, pan_number, voter_id |
| Party (Organization) | display_name, legal_name, gstin, pan |
| Agreement | reference_number |
| MaintenanceRequest | reference_number, title |
| Task | reference_number, title |
| RentCycle | reference_number |
| OwnerPayout | reference_number, payment_reference |
| Establishment | name |

---

## Accounting Integration

| Dwelly Event | Queue | tek2991/accounting Action |
|---|---|---|
| Party with owner role created | critical | Contact created — owner |
| Party with tenant role created | critical | Contact created — tenant |
| Party with vendor role created | critical | Contact created — vendor |
| Rent payment confirmed | critical | Transaction: Debit Bank / Credit Rent Income |
| Dwelly fee | critical | Transaction: Debit Owner Payable / Credit Management Fee |
| Payout released | critical | Transaction: Debit Owner Payable / Credit Bank |
| Maintenance invoice | critical | Invoice against responsible party Contact |
| Invoice correction | critical | Credit Note or Debit Note |
| SD collection | critical | Transaction: Debit Bank / Credit SD Liability |
| SD refund | critical | Transaction: Debit SD Liability / Credit Bank |
| Electricity bill | critical | Invoice against tenant Contact |
| Water bill share | critical | Invoice per tenant |
| Society fee advance | critical | Transaction: Debit Owner Receivable / Credit Bank |

---

## Domain File Structure

```
app/
├── Domain/
│   ├── Geographic/
│   │   └── Models/ Region.php
│   │
│   ├── Property/
│   │   ├── Actions/   CreateProperty, UpdateProperty, ArchiveProperty,
│   │   │              UpdateGallery, UpdatePricing, UpdateUtilityConfig,
│   │   │              AssignExecutive
│   │   ├── States/    PropertyState + 4 state classes
│   │   ├── Events/    PropertyCreated, PropertyStateChanged, PropertyArchived,
│   │   │              PropertyAssignmentChanged
│   │   ├── Listeners/ CreateOnboardingChecklist, CreatePropertyAssignment,
│   │   │              SendOwnerWelcomeNotification, LogPropertyActivity
│   │   └── Models/    Property, PropertyPricingVersion, PropertyUtilityConfig,
│   │                  PropertyAmenity, PropertyRoom, PropertyFurniture,
│   │                  PropertyAssignment, Establishment, PropertyEstablishment
│   │
│   ├── Party/
│   │   ├── Actions/   CreateParty, AddOwnerRole, AddTenantRole,
│   │   │              AddVendorRole, AddStaffRole, AddBankAccount
│   │   ├── Events/    PartyCreated, PartyRoleAdded
│   │   ├── Listeners/ CreateAccountingContact (transactional, critical queue)
│   │   └── Models/    Party, PartyIndividual, PartyOrganization,
│   │                  OwnerProfile, TenantProfile, VendorProfile,
│   │                  StaffProfile, PartyBankAccount
│   │
│   ├── Approval/
│   │   ├── Engine/    ApprovalEngine.php
│   │   ├── Events/    ApprovalRequested, ApprovalDecided, ApprovalExpired
│   │   ├── Listeners/ NotifyApprover, ProcessExpiredApproval
│   │   └── Models/    Approval, ApprovalStep
│   │
│   ├── Workflow/
│   │   ├── Engine/    WorkflowEngine.php
│   │   ├── Models/    WorkflowDefinition, WorkflowStep, WorkflowStepTransition
│   │   └── Gates/     GateChecker (interface), AuditCompleteGateChecker,
│   │                  DocumentVersionGateChecker, OwnerDecisionGateChecker,
│   │                  CompletionMediaGateChecker, ApprovalGateChecker
│   │
│   ├── Task/
│   │   ├── Actions/   CreateTask, AssignTask, CompleteTask,
│   │   │              EscalateTask, CancelTask
│   │   ├── States/    TaskState + 7 state classes
│   │   ├── Events/    TaskCreated, TaskCompleted, TaskEscalated, TaskOverdue
│   │   ├── Listeners/ AdvanceWorkflowStep, SendTaskNotification, LogPropertyActivity
│   │   └── Models/    Task, TaskComment, TaskEscalation
│   │
│   ├── Audit/
│   │   ├── Actions/   CreatePropertyAudit, CompleteAuditSection,
│   │   │              ShareAudit, RecordAcknowledgement
│   │   ├── Services/  AuditReportGeneratorService (medium queue)
│   │   └── Models/    AuditTemplate, AuditTemplateSection,
│   │                  PropertyAudit, PropertyAuditSection
│   │
│   ├── Maintenance/
│   │   ├── Actions/   CreateMaintenanceRequest, ClassifyFault, SubmitVendorQuote,
│   │   │              RecordOwnerDecision, AssignVendor, RecordCompletion,
│   │   │              VerifyCompletion, RaiseInvoice
│   │   ├── States/    MaintenanceState + 12 state classes
│   │   ├── Events/    MaintenanceReported, MaintenanceQuoted,
│   │   │              MaintenanceCompleted, MaintenanceInvoiced
│   │   ├── Listeners/ ApplyDwellyMarginSnapshot, NotifyResponsibleParty,
│   │   │              BlockPayoutIfInvoicePending, LogPropertyActivity
│   │   └── Models/    MaintenanceRequest, MaintenancePhase, MaintenanceDispute
│   │
│   ├── Tenancy/
│   │   ├── Actions/   CreateTenancy, InitiateMoveIn, RecordMoveOut,
│   │   │              AdjustSDForNotice, ReverseSdAdjustment, SettleSecurityDeposit
│   │   ├── States/    TenancyStatus, AgreementState
│   │   ├── Services/  MoveInCalculatorService, MoveOutSettlementService,
│   │   │              SecurityDepositService, SlaVariantSelectorService
│   │   ├── Events/    TenancyStarted, NoticeGiven, TenancyClosed, AgreementSigned
│   │   ├── Listeners/ CreateRentCycles, ScheduleRenewalTask,
│   │   │              CreateTenantAccountingContact
│   │   └── Models/    Tenancy, Agreement, OnboardingChecklist,
│   │                  SlaVariant, SecurityDepositEntry
│   │
│   ├── Finance/
│   │   ├── Actions/   ConfirmRentPayment, ReleaseOwnerPayout, HoldPayout,
│   │   │              ScheduleAdvanceRecovery, CreateAnnualCharge
│   │   ├── Services/  CalculationService,        ← central calculation authority
│   │   │              PayoutCalculatorService,   ← orchestrates CalculationService
│   │   │              TenantRiskScoringService,  ← orchestrates CalculationService
│   │   │              AccountingBridgeService
│   │   ├── Events/    RentPaymentConfirmed, PayoutReleased, PayoutHeld,
│   │   │              RentDueDayOne ... DefaultRiskFlagged
│   │   ├── Listeners/ RecalculateOwnerPayout, SnapshotTenantRiskScore,
│   │   │              CreateAccountingTransaction, SendRentReceiptNotification
│   │   └── Models/    RentCycle, RentPayment, OwnerPayout, AdvancePayment,
│   │                  AnnualCharge, ConfigurationSetting, TenantRiskSnapshot,
│   │                  NumberingSequence
│   │
│   ├── Utilities/
│   │   ├── Actions/   RecordElectricityReading, RecordWaterBill, SetElectricityRate
│   │   ├── Services/  UtilityCalculatorService (delegates to CalculationService)
│   │   ├── Events/    ElectricityBillGenerated, WaterBillGenerated
│   │   └── Models/    ElectricityReading, ElectricityRateVersion, WaterBill
│   │
│   ├── Communication/
│   │   ├── Actions/   SendMessage, CreateConversation,
│   │   │              CreateFollowUpTask, ArchiveConversation
│   │   ├── Events/    MessageSent, FollowUpCreated
│   │   ├── Listeners/ DeliverViaChannel, LogPropertyActivity
│   │   └── Models/    Conversation, ConversationParticipant, Message
│   │
│   ├── Document/
│   │   ├── Actions/   UploadDocumentVersion, SetCurrentVersion,
│   │   │              SetCoverPhoto, ToggleMediaVisibility, ReorderGallery
│   │   └── Models/    Media, DocumentVersion
│   │
│   ├── Notification/
│   │   ├── Channels/  WhatsAppChannel, InAppChannel, EmailChannel, SmsChannel
│   │   ├── Services/  NotificationService (renders + stores + dispatches)
│   │   ├── DTOs/      NotificationPayload
│   │   └── Models/    NotificationTemplate, Notification
│   │
│   └── Migration/
│       ├── Actions/   UploadImportFile, MapColumns, ValidateImport,
│       │              RunImport, RollbackImport
│       ├── Jobs/      ValidateImportJob (low), RunImportJob (low)
│       └── Models/    MigrationImport, MigrationImportRow
│
├── Filament/
│   ├── Resources/
│   │   ├── PropertyResource/    (tabbed hub — central view)
│   │   ├── PartyResource/       (unified: individuals + organizations)
│   │   ├── TaskResource/
│   │   ├── ApprovalResource/    (pending approvals dashboard)
│   │   ├── MaintenanceResource/
│   │   ├── RentCollectionResource/
│   │   ├── OwnerPayoutResource/
│   │   ├── AgreementResource/
│   │   └── UtilityBillingResource/
│   ├── Pages/
│   │   ├── Dashboard.php
│   │   ├── PropertyGalleryManager.php
│   │   ├── AuditComparison.php        ← move-in vs move-out side-by-side
│   │   └── Settings/                  ← all reference data + configuration
│   └── Widgets/
│       ├── KpiOverviewWidget.php
│       ├── RentCollectionStatusWidget.php
│       ├── PendingPayoutsWidget.php
│       ├── PendingApprovalsWidget.php ← new
│       ├── MaintenancePipelineWidget.php
│       └── PropertyTimelineWidget.php
│
└── Console/Commands/
    ├── SendRentRemindersCommand.php
    ├── ProcessRenewalRemindersCommand.php
    ├── SendEsignRemindersCommand.php
    ├── ProcessTaskSlaCommand.php
    ├── FlagAnnualChargesCommand.php
    ├── ProcessExpiredApprovalsCommand.php   ← new
    ├── GenerateRentCyclesCommand.php
    ├── GenerateSocietyChargesCommand.php
    ├── GenerateOwnerStatementsCommand.php
    └── ResetNumberingSequencesCommand.php
```

---

## Phased Build Plan

### Phase 1 — Foundation (Weeks 1–4)
- [ ] Domain folder structure, service providers, single Filament panel, queue workers per priority tier
- [ ] `spatie/laravel-permission` — capability-based permissions, 4 roles seeded
- [ ] All Reference Data lookup tables seeded + Settings UI (admin-manageable)
- [ ] Geographic regions table + seeder + `staff_regions` pivot + geographic middleware
- [ ] Unified Party model — individuals, organizations, all profile tables, `party_bank_accounts`
- [ ] `AccountingBridgeService` — auto-create accounting contacts (transactional, critical queue)
- [ ] Properties — structured address, region assignment, `property_utility_config`
- [ ] `property_pricing_versions` + pricing version manager
- [ ] Property Configuration: dynamic amenity/room/furniture pivots
- [ ] Shared Establishments master + property pivot
- [ ] Polymorphic Media + Gallery management (sort/cover/hide)
- [ ] `DocumentVersion` + versioned document upload UI
- [ ] `NumberingService` + all sequences seeded
- [ ] `CalculationService` (all methods, fully tested)
- [ ] `property_assignments` history table + assignment tracking

### Phase 2 — Engines, State Machines & Tenancy (Weeks 5–8)
- [ ] `spatie/laravel-model-states` for all workflow models
- [ ] Configurable Workflow Engine + `workflow_definitions` seeded
- [ ] Universal Task Engine (gates, escalation, SLA, comments, media)
- [ ] **Generic Approval Engine** — `ApprovalEngine`, `approvals`, `approval_steps`
- [ ] Configurable Audit Templates + Structured Audit system + completion gates
- [ ] SLA variant auto-selector
- [ ] Tenancy creation + Agreement state machine (manual signature Phase 1) + agreement snapshots
- [ ] Move-in workflow (8 gated tasks, workflow engine, approval engine for quotations)
- [ ] Maintenance request full state machine + Dwelly margin snapshot via `CalculationService`
- [ ] Dispute escalation sub-workflow
- [ ] Infrastructure works with phase tracking

### Phase 3 — Financial Engine (Weeks 9–12)
- [ ] Rent cycles + payment confirmation (immutable log)
- [ ] Rent reminder escalation (Scheduler + queued Events on 'high' queue)
- [ ] Payout calculator via `CalculationService` + `PayoutCalculatorService`
- [ ] Payout finalization + snapshot immutability + approval via `ApprovalEngine`
- [ ] Security deposit ledger (immutable, balance via `CalculationService`)
- [ ] Tenant risk snapshots via `TenantRiskScoringService` + `CalculationService`
- [ ] Electricity readings + versioned rate + immutable bill via `CalculationService`
- [ ] Water bill splitting via `CalculationService`
- [ ] Advance payment tracking + annual charges
- [ ] Full `AccountingBridgeService` (all events → critical queue)
- [ ] Financial immutability enforcement + credit/debit note routes
- [ ] `configuration_settings` versioning + historical value lookup

### Phase 4 — Communication, Notifications & Closure (Weeks 13–16)
- [ ] Conversation model + three-channel setup per property
- [ ] Message composer + Filament hub
- [ ] Follow-up tasks from messages (Task Engine)
- [ ] `NotificationService` — renders body, stores `rendered_body` snapshot, dispatches to channel
- [ ] Notification template management UI with version tracking
- [ ] In-App notification inbox
- [ ] WhatsApp channel (activated when BSP credentials configured)
- [ ] Move-out workflow (4 phases, audit comparison page, SD settlement via `CalculationService`)
- [ ] Archive workflow with closure gates
- [ ] `ProcessExpiredApprovalsCommand` scheduler

### Phase 5 — Dashboard, Timeline, Reports & Migration (Weeks 17–20)
- [ ] Dashboard widgets including `PendingApprovalsWidget`
- [ ] Property Activity Timeline widget + `spatie/laravel-activitylog` on all models
- [ ] Global Search for all entities
- [ ] Export: CSV/PDF for payouts, payment history, maintenance history
- [ ] Migration Wizard — 6-step module with validation + rollback (low queue)
- [ ] Full access control audit (capability + geographic scope)
- [ ] Queue monitoring dashboard (Laravel Horizon or Filament queue panel)

---

## Verification Plan

### Automated Tests (Pest)

```bash
# Foundation
php artisan test --filter=CalculationServiceTest        # all formulas with known inputs
php artisan test --filter=NumberingServiceTest          # sequential, annual reset, concurrency
php artisan test --filter=GeographicScopeTest           # region inheritance (Assam → Guwahati)
php artisan test --filter=PartyMultiRoleTest            # one party: owner + vendor + tenant

# Authorization
php artisan test --filter=ModuleAccessGateTest          # 403 when module access missing despite capability
php artisan test --filter=FilamentVisibilityTest        # nav groups hidden without module access
php artisan test --filter=NoRoleNameHardcodingTest      # static analysis/regex ensures `$user->role ==` is not used

# Workflows & State Machines
php artisan test --filter=PropertyStateMachineTest      # illegal transitions rejected
php artisan test --filter=WorkflowEngineTest            # step generation from definition
php artisan test --filter=ApprovalEngineTest            # request, decide, expire, escalate
php artisan test --filter=AuditGateTest                 # incomplete audit blocks workflow
php artisan test --filter=TaskGateEnforcementTest       # all gate checkers

# Finance
php artisan test --filter=PayoutCalculatorTest          # net_payout formula end-to-end
php artisan test --filter=SDLedgerImmutabilityTest      # no update/delete on entries
php artisan test --filter=FinancialImmutabilityTest     # finalized records locked
php artisan test --filter=PricingVersionTest            # historical cycles use correct version
php artisan test --filter=MarginSnapshotTest            # margin locked at quoting time
php artisan test --filter=ConfigurationVersioningTest   # historical config value retrieval

# Documents & Notifications
php artisan test --filter=DocumentVersionTest           # new upload = new version
php artisan test --filter=NotificationRenderedBodyTest  # rendered_body stored, not template ref

# Accounting
php artisan test --filter=AccountingBridgeTest          # all events post balanced entries
php artisan test --filter=CreditNoteTest                # finalized invoice → credit note only

# Migration
php artisan test --filter=MigrationWizardRollbackTest   # failed import → no partial data
```

### Manual Verification
- Walk all 6 workflows end-to-end with all 4 role types in staging
- Verify Approval Engine correctly blocks workflow until decision is made
- Verify executive in Khanapara cannot see Beltola properties; manager in Assam sees all
- Verify critical queue processes payments before low-queue image jobs
- Verify finalized invoice shows "Read Only" with credit note option only
- Verify notification `rendered_body` matches what was sent even after template changes
- Verify property assignment history shows complete exec handover log
- Verify `CalculationService` is the only place rent/payout/utility math occurs (grep check)
- Verify agreement snapshots are preserved when property pricing version changes
- Verify structured audit blocks submission when any section is below media minimum
- Test archived property: read-only, excluded from all operational filters and global search
- Verify John (owner + vendor) has one KYC set, one accounting contact, two profile rows
