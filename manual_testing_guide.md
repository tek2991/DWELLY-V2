# Dwelly V2 — Manual Testing Guide

**Version:** Phase 1–5 Complete  
**Base URL:** `http://localhost/admin` (or your configured dev server URL)  
**Prerequisites:** App running (`php artisan serve`), queue worker running (`php artisan queue:work`), scheduler registered.

---

## Table of Contents

1. [Pre-Flight Checks](#1-pre-flight-checks)
2. [Reference Data (Settings)](#2-reference-data-settings)
3. [Workflow Definitions](#3-workflow-definitions)
4. [Party Management](#4-party-management)
5. [Property Management](#5-property-management)
6. [Tenancy Agreements](#6-tenancy-agreements)
7. [Accounting & Ledger](#7-accounting--ledger)
8. [Communications](#8-communications)
9. [Scheduled Commands](#9-scheduled-commands)
10. [System Integrity Checks](#10-system-integrity-checks)

---

## 1. Pre-Flight Checks

These checks confirm the application is wired correctly before testing individual features.

### TC-001 — Admin Panel Accessible

| Step | Action | Expected Result |
|---|---|---|
| 1 | Navigate to `/admin` | Redirected to `/admin/login` |
| 2 | Login with seeded admin credentials | Dashboard loads without errors |
| 3 | Inspect the navigation sidebar | All resource links visible: Properties, Parties, Tenancy Agreements, Rent Payments, Owner Payouts, Workflow Definitions, Notification Templates, Communication Logs |

### TC-002 — Event Wiring Verification

| Step | Action | Expected Result |
|---|---|---|
| 1 | Run `php artisan event:list` | Shows `TenancyActivated → SendWelcomeNotification` and `PartyCreated → CreateAccountingContact` |
| 2 | Run `php artisan schedule:list` | Shows `finance:generate-rent-invoices` scheduled for `0 0 1 * *` |

### TC-003 — Migration Status

| Step | Action | Expected Result |
|---|---|---|
| 1 | Run `php artisan migrate:status` | All 18 migrations show status `Ran` with no pending migrations |

---

## 2. Reference Data (Settings)

Navigate to: **Admin → Reference Data**

Reference Data controls the configurable lookup values used throughout the system (property types, BHK types, amenity categories, etc.).

### TC-004 — Access Reference Data Page

| Step | Action | Expected Result |
|---|---|---|
| 1 | Click "Reference Data" in the sidebar | The Reference Data settings page loads |
| 2 | Inspect the available categories | Categories for property types, BHK types, amenities, flooring types etc. are visible |

### TC-005 — Create a Property Type

| Step | Action | Expected Result |
|---|---|---|
| 1 | Navigate to Reference Data |  |
| 2 | Add a new Property Type: `Apartment` | Record saves successfully |
| 3 | Add another: `Villa` | Record saves successfully |
| 4 | Verify they appear in the list |  |

### TC-006 — Create a BHK Type

| Step | Action | Expected Result |
|---|---|---|
| 1 | Navigate to Reference Data |  |
| 2 | Add a new BHK type: `2 BHK` | Record saves successfully |
| 3 | Add another: `3 BHK` | Record saves successfully |

### TC-007 — Create a Geographic Region

Reference data for Regions is required before creating Properties or Parties.

| Step | Action | Expected Result |
|---|---|---|
| 1 | Navigate to Reference Data |  |
| 2 | Create a Region: `Guwahati` | Record saves |
| 3 | Create a sub-Region or Locality if supported | Record saves |

---

## 3. Workflow Definitions

Navigate to: **Admin → Workflow Definitions**

Workflow Definitions define the state machine for each entity type. Without an active definition, entities are created but not tracked in a workflow.

### TC-008 — Create a Property Workflow Definition

| Step | Action | Expected Result |
|---|---|---|
| 1 | Click "New Workflow Definition" |  |
| 2 | Set Entity Type: `Property`, Status: `Active` | Form saves |
| 3 | Note the created record ID | Will be used when onboarding a property |

### TC-009 — Create a Tenancy Agreement Workflow Definition

| Step | Action | Expected Result |
|---|---|---|
| 1 | Click "New Workflow Definition" |  |
| 2 | Set Entity Type: `TenancyAgreement`, Status: `Active` | Form saves |
| 3 | Note the created record | Required for drafting agreements |

> **Note:** If no active Workflow Definition exists for an entity type, the system gracefully skips workflow creation (no crash) but the entity won't appear in workflow tracking.

---

## 4. Party Management

Navigate to: **Admin → Parties**

Parties represent all external people and organizations in the system — Owners, Tenants, and Vendors.

### TC-010 — Create an Individual Owner Party

| Step | Action | Expected Result |
|---|---|---|
| 1 | Click "New Party" |  |
| 2 | Party Type: `Individual` | Individual Details section appears dynamically |
| 3 | Fill: Display Name `Rajesh Kumar`, Phone `9876543210`, Email `rajesh@example.com` |  |
| 4 | Individual Details: First Name `Rajesh`, Last Name `Kumar`, Aadhaar `1234-5678-9012` |  |
| 5 | Profile Options: Profile Type → `Owner` |  |
| 6 | Click Save | Party created successfully, redirects to list |
| 7 | Check queue worker output | `CreateAccountingContact` listener fired — logs "Accounting contact synced for party [id] as owner" |

### TC-011 — Create an Individual Tenant Party

| Step | Action | Expected Result |
|---|---|---|
| 1 | Click "New Party" |  |
| 2 | Party Type: `Individual` |  |
| 3 | Fill: Display Name `Priya Sharma`, Phone `9988776655`, Email `priya@example.com` |  |
| 4 | Individual Details: First Name `Priya`, Last Name `Sharma` |  |
| 5 | Profile Options: Profile Type → `Tenant` |  |
| 6 | Click Save | Party created successfully |

### TC-012 — Create an Organization Vendor Party

| Step | Action | Expected Result |
|---|---|---|
| 1 | Click "New Party" |  |
| 2 | Party Type: `Organization` | Organization Details section appears, Individual Details disappears |
| 3 | Fill: Display Name `QuickFix Services Pvt Ltd`, Phone `7788990011` |  |
| 4 | Organization Details: Legal Name `QuickFix Services Pvt Ltd`, GSTIN `22AAAAA0000A1Z5` |  |
| 5 | Profile Options: Profile Type → `Vendor` |  |
| 6 | Click Save | Party created successfully |

### TC-013 — Party Type Toggle Validation

| Step | Action | Expected Result |
|---|---|---|
| 1 | Open "New Party" |  |
| 2 | Select Party Type: `Individual` | Individual Details section shows, Organization section hidden |
| 3 | Switch Party Type to: `Organization` | Organization Details shows, Individual Details hidden |
| 4 | Switch back to `Individual` | Individual Details returns |

### TC-014 — Party List and Search

| Step | Action | Expected Result |
|---|---|---|
| 1 | Navigate to Parties list |  |
| 2 | Search for `Rajesh` in the search bar | Only matching records shown |
| 3 | Clear search | All parties shown |

---

## 5. Property Management

Navigate to: **Admin → Properties**

### TC-015 — Create a New Property

| Step | Action | Expected Result |
|---|---|---|
| 1 | Click "New Property" |  |
| 2 | Basic Details: Building Name `Regent Paradise`, Region → select `Guwahati` |  |
| 3 | Property Type → `Apartment`, BHK Type → `2 BHK` |  |
| 4 | Address: Address Line 1 `Ghoramara`, Locality `Azara`, City `Guwahati`, Pincode `781017` |  |
| 5 | Click Save | Property created with status `draft` |
| 6 | Open the saved record | Code field auto-populated (e.g. `PRO-2026-0001`) |
| 7 | Check database (`workflow_instances` table) | A workflow instance exists for this property with `current_state = draft` |

### TC-016 — Property Code is Auto-Generated and Unique

| Step | Action | Expected Result |
|---|---|---|
| 1 | Create a second property: `Green Valley` |  |
| 2 | Click Save |  |
| 3 | Compare codes of both properties | Codes are sequential (e.g., `PRO-2026-0001`, `PRO-2026-0002`) — never duplicated |

### TC-017 — Property Status Defaults to Draft

| Step | Action | Expected Result |
|---|---|---|
| 1 | Create any new property | Status is always `draft` upon creation — cannot be manually set to `active` through the creation form |

### TC-018 — Utility Defaults Created on Property Creation

| Step | Action | Expected Result |
|---|---|---|
| 1 | Create a property |  |
| 2 | Query the `property_utility_configs` table for the new property | Record exists with `electricity_billing = direct_to_tenant`, `water_billing = included_in_rent`, `society_fee_model = owner_pays` |

### TC-019 — Property List and Edit

| Step | Action | Expected Result |
|---|---|---|
| 1 | Navigate to Properties list | All properties visible |
| 2 | Click Edit on a property | Edit form loads with current values |
| 3 | Change Building Name, Save | Change persists |

---

## 6. Tenancy Agreements

Navigate to: **Admin → Tenancy Agreements**

> **Prerequisite:** At least one Property and one Party with a **Tenant profile** must exist before creating a Tenancy Agreement.

### TC-020 — Create a Tenancy Agreement (Wizard — Step 1: Property & Terms)

| Step | Action | Expected Result |
|---|---|---|
| 1 | Click "New Tenancy Agreement" | Multi-step Wizard opens |
| 2 | Step 1 — Select Property: `Regent Paradise` |  |
| 3 | Rent Amount: `23000` |  |
| 4 | Security Deposit: leave blank (auto-calculate test) |  |
| 5 | Start Date: `2026-08-01`, End Date: `2027-07-31` |  |
| 6 | Lock-in Period: `11` months, Notice Period: `30` days |  |
| 7 | Click "Next" | Proceeds to Step 2 |

### TC-021 — Wizard Step 2: Tenants & Roles

| Step | Action | Expected Result |
|---|---|---|
| 1 | In Step 2, open the Primary Tenant dropdown | **Only parties with a Tenant profile appear** (not owners or vendors) |
| 2 | Select `Priya Sharma` |  |
| 3 | Click "Next" | Proceeds to Step 3 |

### TC-022 — Wizard Step 3: E-Signature & Review

| Step | Action | Expected Result |
|---|---|---|
| 1 | On Step 3, add Special Terms: `Pet not allowed` |  |
| 2 | Observe the E-Signature placeholder block | Informational placeholder message visible |
| 3 | Click "Create" | Agreement saved |

### TC-023 — Security Deposit Auto-Calculation

| Step | Action | Expected Result |
|---|---|---|
| 1 | Create an agreement with Rent `20000`, leave Security Deposit blank |  |
| 2 | After saving, inspect the record | Security Deposit auto-set to `40000` (2× rent) |
| 3 | Create another with Rent `20000` and explicitly set Security Deposit `50000` |  |
| 4 | After saving | Security Deposit is `50000` (explicit value respected) |

### TC-024 — Agreement Code Auto-Generated

| Step | Action | Expected Result |
|---|---|---|
| 1 | View any saved agreement | Code field populated (e.g. `TNC-2026-0001`) |
| 2 | Create a second agreement | Code is `TNC-2026-0002` — sequential, never duplicated |

### TC-025 — Workflow Instance Created on Draft

| Step | Action | Expected Result |
|---|---|---|
| 1 | Create a Tenancy Agreement | Record saved |
| 2 | Inspect `workflow_instances` table | Row exists with `subject_type = App\Domain\Agreement\Models\TenancyAgreement`, `current_state = draft` |

### TC-026 — Tenancy Role Created

| Step | Action | Expected Result |
|---|---|---|
| 1 | After creating an agreement | Inspect `tenancy_roles` table | One row with `party_id = [tenant id]`, `role_type = Primary Tenant`, `is_primary = 1` |

---

## 7. Accounting & Ledger

### Owner Payouts

Navigate to: **Admin → Owner Payouts**

#### TC-027 — Generate a Payout via Modal

| Step | Action | Expected Result |
|---|---|---|
| 1 | Navigate to Owner Payouts list |  |
| 2 | Click "Generate Payout" header button | A modal opens with 3 fields: Property, Period Start, Period End |
| 3 | Select Property: `Regent Paradise` |  |
| 4 | Period Start: `2026-08-01`, Period End: `2026-08-31` |  |
| 5 | Click Save/Submit | Success notification: "Payout Generated Successfully" |
| 6 | Inspect the payouts table | New row visible with: `rent_collected`, `management_fee` (10% of rent), `amount` (rent − fee − ₹500 reserve), `status = pending` |

#### TC-028 — Management Fee Calculation

| Step | Action | Expected Result |
|---|---|---|
| 1 | Generate a payout for a property with rent `23000` |  |
| 2 | Inspect the record | `rent_collected = 23000`, `management_fee = 2300` (10%), `reserve_deduction = 500`, `amount = 20200` |

#### TC-029 — Payout Records are Immutable

| Step | Action | Expected Result |
|---|---|---|
| 1 | Navigate to Owner Payouts list |  |
| 2 | Try to find an Edit button or Delete checkbox | Neither is available — the table is read-only |

### Rent Payments

Navigate to: **Admin → Rent Payments**

#### TC-030 — View Rent Payments

| Step | Action | Expected Result |
|---|---|---|
| 1 | Navigate to Rent Payments | List loads |
| 2 | Inspect columns | Agreement code, Tenant name, Amount, Due Date, Status badge shown |
| 3 | Status badges render correctly | `pending = yellow`, `paid = green`, `overdue = red` |

---

## 8. Communications

### Notification Templates

Navigate to: **Admin → Notification Templates**

#### TC-031 — Create a Welcome Email Template

| Step | Action | Expected Result |
|---|---|---|
| 1 | Click "New Notification Template" |  |
| 2 | Event Name: `TenancyActivated` | Must be exact — this matches the domain event name |
| 3 | Channel: `Email` |  |
| 4 | Subject: `Welcome to {{property}}, {{name}}!` |  |
| 5 | Body: `Dear {{name}}, Your tenancy at {{property}} starts on {{start_date}}. Your monthly rent is ₹{{rent_amount}}.` |  |
| 6 | Active: Toggle ON |  |
| 7 | Save | Template created |

#### TC-032 — Create a WhatsApp Template for the Same Event

| Step | Action | Expected Result |
|---|---|---|
| 1 | Click "New Notification Template" |  |
| 2 | Event Name: `TenancyActivated`, Channel: `WhatsApp` | Multiple channels supported for the same event |
| 3 | Body: `Hi {{name}}! Your move-in at {{property}} is confirmed for {{start_date}}.` |  |
| 4 | Save | Template created |
| 5 | Verify both templates appear in the list for `TenancyActivated` | Both Email and WhatsApp templates visible |

#### TC-033 — Template Toggle (Active/Inactive)

| Step | Action | Expected Result |
|---|---|---|
| 1 | Edit an existing template | Toggle `is_active` OFF |
| 2 | Save |  |
| 3 | Trigger an event for this template | The inactive template is NOT dispatched (no log entry created) |

#### TC-034 — Deactivate and Verify No Log Created

| Step | Action | Expected Result |
|---|---|---|
| 1 | Deactivate the `TenancyActivated` email template |  |
| 2 | Trigger a tenancy activation (via `ActivateTenancyAction` in tinker) |  |
| 3 | Inspect Communication Logs | No log entry for email; WhatsApp entry may exist if that template is still active |

### Communication Logs

Navigate to: **Admin → Communication Logs**

#### TC-035 — Logs are Read-Only

| Step | Action | Expected Result |
|---|---|---|
| 1 | Navigate to Communication Logs |  |
| 2 | Look for "New Log", Edit buttons, Delete checkboxes | None are present — the table is fully read-only |

#### TC-036 — Log Columns Display Correctly

| Step | Action | Expected Result |
|---|---|---|
| 1 | After any notification is dispatched (see TC-037), open Communication Logs |  |
| 2 | Verify columns | Recipient Name, Channel (badge), Contact Details, Subject, Status badge, Sent At |
| 3 | Status `sent` renders as green badge, `failed` as red |  |

#### TC-037 — End-to-End: Tenancy Activation Triggers Notification

> This test covers the full loop from activation → event → listener → service → log.

| Step | Action | Expected Result |
|---|---|---|
| 1 | Ensure at least one `TenancyActivated` template is **active** (TC-031) |  |
| 2 | In Tinker: `$agreement = TenancyAgreement::first(); app(ActivateTenancyAction::class)->execute($agreement, User::first());` |  |
| 3 | Check queue worker terminal | `SendWelcomeNotification` job picked up and processed |
| 4 | Navigate to Communication Logs | New log entry with: channel, recipient email/phone, parsed subject and body (variables replaced), status `sent` |
| 5 | Inspect the body content | `{{name}}` replaced with tenant's actual name, `{{property}}` with building name, etc. |

---

## 9. Scheduled Commands

### TC-038 — Monthly Rent Invoice Generation

| Step | Action | Expected Result |
|---|---|---|
| 1 | Ensure at least one `TenancyAgreement` with `status = active` exists in the database |  |
| 2 | Run manually: `php artisan finance:generate-rent-invoices` |  |
| 3 | Observe terminal output | "Starting monthly rent invoice generation..." then "Successfully generated X rent invoices." |
| 4 | Check `rent_payments` table | New rows created with `status = pending`, correct `tenancy_agreement_id`, `tenant_id`, `amount`, and `due_date = start of current month` |

### TC-039 — Duplicate Invoice Prevention

| Step | Action | Expected Result |
|---|---|---|
| 1 | Run `php artisan finance:generate-rent-invoices` twice in the same month |  |
| 2 | Second run terminal output | Count = 0 — "Successfully generated 0 rent invoices." |
| 3 | Check `rent_payments` table | No duplicate rows for the current month |

### TC-040 — Agreement with No Primary Tenant is Skipped Gracefully

| Step | Action | Expected Result |
|---|---|---|
| 1 | Create a test active agreement with no tenancy roles | Simulate edge case |
| 2 | Run `php artisan finance:generate-rent-invoices` | Warning shown: "No primary tenant for agreement [code], skipping." No crash |

---

## 10. System Integrity Checks

These verify architectural constraints are enforced.

### TC-041 — Business Records Cannot Be Deleted (D1 Principle)

| Step | Action | Expected Result |
|---|---|---|
| 1 | Navigate to Owner Payouts | No Delete button or checkbox |
| 2 | Navigate to Rent Payments | No Delete action on records |
| 3 | Navigate to Communication Logs | No Delete action |
| 4 | Navigate to Tenancy Agreements | No Delete bulk action |

### TC-042 — NumberingService Race Condition Safety

| Step | Action | Expected Result |
|---|---|---|
| 1 | Open 2 browser tabs simultaneously |  |
| 2 | In both, start creating a Property and click Save within seconds of each other |  |
| 3 | Check the generated codes | Both codes are **unique and sequential** — pessimistic locking prevents duplication |

### TC-043 — Financial Amounts Use Correct Precision

| Step | Action | Expected Result |
|---|---|---|
| 1 | Create a tenancy with rent `22222` (odd number) |  |
| 2 | Auto-calculate Security Deposit | Shows `44444.00` — no floating point errors |
| 3 | Generate an owner payout for this property | Management fee is `2222.20` (10%), payout is `19499.80` — all rounded correctly using `HALF_UP` |

### TC-044 — Event Discovery Covers All Domain Namespaces

| Step | Action | Expected Result |
|---|---|---|
| 1 | Run `php artisan event:list` |  |
| 2 | Verify both custom events are shown | `App\Domain\Agreement\Events\TenancyActivated` and `App\Domain\Party\Events\PartyCreated` both listed with their listeners |

### TC-045 — Queue Connection for Critical Listeners

| Step | Action | Expected Result |
|---|---|---|
| 1 | Create a new Party (TC-010) |  |
| 2 | Check the Redis `critical` queue | `CreateAccountingContact` job is dispatched to the `critical` queue (not the default queue) |

### TC-046 — Workflow Transition History

| Step | Action | Expected Result |
|---|---|---|
| 1 | Create a Tenancy Agreement → workflow starts at `draft` |  |
| 2 | Activate it via Tinker using `ActivateTenancyAction` |  |
| 3 | Inspect `workflow_transitions` table | One row: `from_state = draft`, `to_state = active`, `transitioned_by = [user id]`, `reason = Tenancy activated.` |

---

## Appendix — Test Data Setup Script

Run in `php artisan tinker` to quickly bootstrap test data:

```php
// 1. Create a Region
$region = App\Domain\Geographic\Models\Region::create(['name' => 'Guwahati', 'code' => 'GUW']);

// 2. Create an Owner Party
$owner = app(App\Domain\Party\Actions\CreatePartyAction::class)->execute([
    'party_type' => 'individual',
    'display_name' => 'Rajesh Kumar',
    'phone' => '9876543210',
    'email' => 'rajesh@test.com',
    'region_id' => $region->id,
    'individual_data' => ['first_name' => 'Rajesh', 'last_name' => 'Kumar'],
], 'owner');

// 3. Create a Tenant Party
$tenant = app(App\Domain\Party\Actions\CreatePartyAction::class)->execute([
    'party_type' => 'individual',
    'display_name' => 'Priya Sharma',
    'phone' => '9988776655',
    'email' => 'priya@test.com',
    'region_id' => $region->id,
    'individual_data' => ['first_name' => 'Priya', 'last_name' => 'Sharma'],
], 'tenant');

// 4. Create Workflow Definitions
App\Domain\Workflow\Models\WorkflowDefinition::create([
    'entity_type' => 'Property', 'name' => 'Property Lifecycle', 'is_active' => true,
]);
App\Domain\Workflow\Models\WorkflowDefinition::create([
    'entity_type' => 'TenancyAgreement', 'name' => 'Tenancy Lifecycle', 'is_active' => true,
]);

// 5. Create a Property
$property = app(App\Domain\Property\Actions\OnboardPropertyAction::class)->execute([
    'building_name' => 'Regent Paradise',
    'region_id' => $region->id,
], App\Models\User::first());

// 6. Draft a Tenancy Agreement
$agreement = app(App\Domain\Agreement\Actions\DraftTenancyAgreementAction::class)->execute(
    $property,
    ['rent_amount' => 23000, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31'],
    [['party_id' => $tenant->id, 'role_type' => 'Primary Tenant', 'is_primary' => true]],
    App\Models\User::first()
);
echo "Done! Agreement: {$agreement->code}";
```
