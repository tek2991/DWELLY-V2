# Dwelly Existing Properties CSV Onboarding Guide

This directory contains the master CSV templates and data definitions for bulk-onboarding existing properties, along with their Owners, Authorized Signatories, MOUs, Multi-Tenant Occupants, Tenancy Agreements, Specifications, and Photos into Dwelly-V2.

> 👤 **Looking for the Data Entry Guide?**
> If you are a Data Entry Operator or Operations team member entering property details in Excel / Google Sheets, please refer to the dedicated **[DATA_ENTRY_GUIDE.md](file:///home/tek2991/Desktop/Works/Dwelly-V2/database/seeders/data/DATA_ENTRY_GUIDE.md)** which contains zero developer jargon, field-by-field guidance, allowed values, and common mistakes to avoid.

---

## 🧭 File Structure

The onboarding system uses 3 synchronized CSV files and 1 asset folder per property:

```text
database/seeders/data/
├── existing_properties_template.csv      # File 1: Properties, Owners, Commercials, Signing Authority
├── existing_tenants_template.csv         # File 2: Primary & Secondary Tenants, Agreement Dates
├── property_specifications_template.csv  # File 3: Rooms, Amenities, Inventory Checklist
└── properties/                           # Photos, Signed MOUs, POA scans, and Tenancy Agreements
```

---

## 📁 Folder-Based Asset Structure

Organize all physical or digital documents and photos into folders named after the **`property_code`** (e.g. `GAU-0091`, `BLR-0091`):

```text
storage/app/seed_assets/properties/
└── GAU-0091/
    ├── mou.pdf             # Signed MOU document
    ├── poa.pdf             # Power of Attorney deed (if signatory is different from owner)
    ├── tenancy.pdf         # Signed Tenancy Agreement (if occupied)
    └── photos/             # All property photos
        ├── 01_elevation.jpg # First photo will automatically be set as featured cover photo!
        ├── 02_living.jpg
        └── 03_bedroom.jpg
```

> **Alternative Location**: You can also put property folders right next to your CSV in:
> `database/seeders/data/properties/{property_code}/`

### How It Works:
1. **MOU File**: Looks for `mou.pdf` inside `{property_code}/`. Attaches to Spatie Media collection `signed_pdf`.
2. **POA File**: Looks for `poa.pdf` or `poa_deed.pdf` inside `{property_code}/`. Attaches to Spatie Media collection `signatory_poa`.
3. **Tenancy File**: Looks for `tenancy.pdf` or `agreement.pdf` inside `{property_code}/`. Attaches to Spatie Media collection `signed_agreement`.
4. **Photos**: Looks inside `{property_code}/photos/` (or directly inside `{property_code}/`). Ingests all images, sorts them, copies to public storage, and assigns `is_featured = true` to the first photo.
5. **Graceful Fallbacks**: If any document or photo is omitted or not yet placed in the folder, the importer automatically generates an authentic digital PDF (via DomPDF) or an elevation image so that Filament previews and document viewers work without errors.

---

## 🚀 How to Run the Import / Seeder

### 1. Test / Validate without writing to database (Dry-Run):
```bash
php artisan dwelly:seed-properties-csv --dry-run
```

### 2. Execute Live Import (Auto-discovers tenant and spec CSVs):
```bash
php artisan dwelly:seed-properties-csv
```

### 3. Execute Import with custom files:
```bash
php artisan dwelly:seed-properties-csv \
  --file=/path/to/properties.csv \
  --tenants-file=/path/to/tenants.csv \
  --specs-file=/path/to/specs.csv
```

### 4. Or run via standard Laravel Seeder:
```bash
php artisan db:seed --class=CsvPropertySeeder
```

---

## 📋 File 1: Properties CSV Column Dictionary (`existing_properties_template.csv`)

| Column Name | Required | Default / Format | Description & Valid Values |
|---|---|---|---|
| `property_code` | Optional | Auto-generated (`PRO-YYYY-XXXXX`) | Unique Property Code (e.g. `GAU-0091`, `BLR-0091`) — used to match asset folder! |
| `building_name` | **Required** | Text | Society or Building name |
| `address_line_1` | **Required** | Text | Flat / Door number, Street address |
| `address_line_2` | Optional | Text | Locality landmark or line 2 |
| `locality` | **Required** | Text | Locality name (auto-mapped or created under city) |
| `city` | **Required** | `Guwahati` or `Bangalore` | Registered branch city |
| `state` | Optional | Auto-inferred from City | `Assam` or `Karnataka` |
| `pincode` | **Required** | 6-digit numeric | Postal PIN code |
| `bhk_type` | **Required** | String | `1 BHK`, `2 BHK`, `3 BHK`, `4 BHK`, `5+ BHK`, `1 RK` |
| `property_type` | **Required** | String | `Apartment`, `Independent House`, `Villa`, `Builder Floor`, `Studio` |
| `furnishing_type` | **Required** | String | `Fully Furnished`, `Semi-Furnished`, `Unfurnished` |
| `floor` | Optional | Integer (e.g. `3`) | Floor on which unit is located |
| `total_floors` | Optional | Integer (e.g. `7`) | Total floors in the building |
| `floor_space_sqft` | Optional | Integer (e.g. `1150`) | Area in Square Feet |
| `property_status` | **Required** | String | `Occupied`, `Vacant`, `Under Maintenance` |
| `is_listed` | Optional | `true` | Public listing visibility (`true` / `false`) |
| `available_from` | Optional | `YYYY-MM-DD` | Date unit is available |
| `owner_name` | **Required** | Text | Property owner's full legal name |
| `owner_phone` | **Required** | 10-digit mobile | Contact phone number |
| `owner_email` | **Required** | Valid email | Owner's email address |
| `owner_pan` | Optional | 10-character PAN | e.g. `ABCPB1234M` |
| `owner_aadhaar` | Optional | 12-digit numeric | e.g. `987654321012` |
| `owner_bank_name` | Optional | Text | e.g. `HDFC Bank`, `State Bank of India` |
| `owner_bank_account` | Optional | Text | Bank Account Number |
| `owner_bank_ifsc` | Optional | Text | 11-character IFSC Code |
| `is_signatory_different` | **Required** | Boolean (`true`/`false`) | `false` if owner signs; `true` if authorized signatory/POA signs |
| `signatory_name` | If different | Text | Representative's full name |
| `signatory_relation` | If different | Text | Relationship to owner (e.g. `POA Holder / Son`, `Spouse`) |
| `signatory_phone` | If different | 10-digit mobile | Representative's contact number |
| `signatory_email` | If different | Valid email | Representative's email |
| `signatory_pan` | Optional | 10-character PAN | Representative's PAN |
| `signatory_aadhaar` | Optional | 12-digit numeric | Representative's Aadhaar |
| `mou_start_date` | Optional | `YYYY-MM-DD` | Onboarding / MOU effective date |
| `mou_status` | Optional | `converted` | `converted`, `signed`, `verified`, `draft` |
| `is_rent_sharing` | **Required** | Boolean (`true`/`false`) | `true` for "Rent share" (% fee), `false` for "Annual subscription" (0% fee) |
| `mou_fee_percentage` | Optional | Decimal (e.g. `8.0`) | Dwelly management fee % (0.0 if not rent sharing) |
| `rent_amount` | **Required** | Decimal (e.g. `24000`) | Monthly Rent in INR |
| `security_deposit` | **Required** | Decimal (e.g. `48000`) | Security Deposit in INR |
| `society_fee` | Optional | Decimal (e.g. `2000`) | Monthly maintenance fee |

> ⚙️ **Note**: `mou_number` and `agreement_code` are **system-generated** using `NumberingService` and do not appear in the CSV.

---

## 👥 File 2: Dedicated Tenants CSV (`existing_tenants_template.csv`)

Dwelly supports multiple tenants per property (primary leaseholder + co-tenants/family members):

| Column Name | Required | Default / Format | Description & Valid Values |
|---|---|---|---|
| `property_code` | **Required** | Text | Must match `property_code` in File 1 (e.g. `GAU-0091`) |
| `is_primary_tenant` | **Required** | Boolean (`true`/`false`) | `true` for primary leaseholder; `false` for co-tenant / family occupant |
| `name` | **Required** | Text | Tenant's full legal name |
| `relationship` | Optional | Text | `Self`, `Spouse`, `Son`, `Daughter`, `Roommate`, etc. |
| `phone` | If Primary | 10-digit mobile | Tenant's phone number |
| `email` | If Primary | Valid email | Tenant's email address |
| `address` | Optional | Text | Permanent / previous address |
| `pan` | Optional | 10-character PAN | Tenant PAN (accepts `pad` or `pan_number`) |
| `aadhaar` | Optional | 12-digit numeric | Tenant Aadhaar card number |
| `parent_name` | Optional | Text | Father's / Guardian's full name |
| `voter_id` | Optional | Text | Voter ID card number |
| `agreement_start_date` | If Primary | `YYYY-MM-DD` | Tenancy start date |
| `agreement_end_date` | If Primary | `YYYY-MM-DD` | Tenancy expiry date |
| `lock_in_months` | Optional | `6` | Lock-in period in months |
| `notice_period_days` | Optional | `30` | Notice period in days |

---

## 🛏️ File 3: Specifications Checklist CSV (`property_specifications_template.csv`)

Granular details like rooms, amenities, and inventory are maintained in this checklist CSV. The system links them automatically via `property_code`.

### Column Summary
- **Linking Column**: `property_code` (e.g. `GAU-0091`)
- **Rooms**: `room_living_room`, `room_kitchen`, `room_master_bedroom`, `room_second_bedroom`, `room_third_bedroom`, `room_guest_bedroom`, `room_attached_bathroom`, `room_common_bathroom`, `room_balcony`, `room_pooja_room`, `room_servant_room`, `room_study_room`
- **Amenities**: `amenity_lift`, `amenity_power_backup`, `amenity_security`, `amenity_parking`, `amenity_gym`, `amenity_swimming_pool`, `amenity_clubhouse`
- **Inventory**: `inv_fan`, `inv_light`, `inv_ac`, `inv_bed`, `inv_wardrobe`, `inv_sofa`, `inv_dining_set`, `inv_geyser`, `inv_fridge`, `inv_tv`, `inv_washing_machine`, `inv_microwave`, `inv_water_purifier`, `inv_chimney`, `inv_keys`

Accepts integers (e.g. `2`, `4`) or boolean strings (`yes`/`no`, `true`/`false`).

---

## ⚡ Standalone Seeding Commands

If properties already exist and you want to import or update specifications separately:

```bash
# Dry-run validation:
php artisan dwelly:seed-property-specs --dry-run

# Live update:
php artisan dwelly:seed-property-specs

# Custom specs file:
php artisan dwelly:seed-property-specs --file=/path/to/custom_specifications.csv
```
