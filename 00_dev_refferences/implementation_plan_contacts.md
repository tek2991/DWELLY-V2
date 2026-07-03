# Goal Description

Align the Operations module's `Parties` resource with the Accounting module's `Contacts` schema so that all necessary fields for accounting sync are supported and manageable from the Operations module. 

## Open Questions

> [!IMPORTANT]
> **GSTIN for Individuals:** Currently, `gstin` is only on the `party_organizations` table. In India, individuals (sole proprietors) can also have a GSTIN. Should we add `gstin` to `party_individuals` as well?
> 
> **Addresses and Bank Accounts UI:** The operations module uses separate related tables (`party_addresses` and `party_bank_accounts`) rather than flat text fields (like `billing_address` in accounting). Should we add these as `Repeater` components directly in the Party form, or as separate tabs (`RelationManagers`) on the Edit page? (The plan currently proposes `Repeater` components for easier inline data entry during creation).
> 
> **Region vs State:** The `parties` table uses `region_id`, while accounting uses `state_id`. Does your business logic require mapping the region to an accounting state during the sync process, or is the `region` effectively acting as a state?

## Proposed Changes

### Database Migrations

#### [NEW] `database/migrations/xxxx_xx_xx_xxxxxx_add_tax_registration_fields_to_parties.php`
- Add `is_tax_registered` (boolean, default false) to the `parties` table.
- Add `gst_registration_type` (string, nullable) to the `parties` table.
- Add `gstin` (string, nullable) to the `party_individuals` table (to support registered sole proprietors).

### Application Layer (Models & Forms)

#### [MODIFY] `app/Domain/Party/Models/Party.php`
- Add `is_tax_registered` and `gst_registration_type` to the `$fillable` array or ensure they are not guarded.
- Add a cast for `is_tax_registered => 'boolean'`.

#### [MODIFY] `app/Filament/Resources/Parties/Schemas/PartyForm.php`
- **Tax Details Section**: Add toggle for `is_tax_registered` and select for `gst_registration_type` (using `Tek2991\Accounting\Enums\GstRegistrationType::class`).
- **GSTIN for Individuals**: Add a `gstin` input field in the "Individual Details" section, visible only if tax registered.
- **Bank Accounts**: Add a `Repeater` for the `bankAccounts` relationship containing inputs for `account_name`, `account_number`, `ifsc_code`, and `bank_name`.
- **Addresses**: Add a `Repeater` for the `addresses` relationship containing inputs for `type` (residential, billing, correspondence), `address_line_1`, `address_line_2`, `city`, `state`, `pincode`.

## Verification Plan

### Automated Tests
- N/A (Assume no existing tests to update unless specified).

### Manual Verification
- Open the Create Party form in the Operations module.
- Verify that Tax Registration fields, Address repeaters, and Bank Account repeaters are visible and functional.
- Save a new Party and verify that data correctly persists to `parties`, `party_addresses`, and `party_bank_accounts`.
- Check the Edit Party page to ensure the data loads and updates correctly.
