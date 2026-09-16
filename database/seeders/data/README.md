# Dwelly Existing Properties CSV Onboarding Guide

This directory contains the master CSV templates and data definitions for bulk-onboarding existing properties, along with their Owners, MOUs, Tenancy Agreements, Tenants, and Photos into Dwelly-V2.

> 👤 **Looking for the Data Entry Guide?**
> If you are a Data Entry Operator or Operations team member entering property details in Excel / Google Sheets, please refer to the dedicated **[DATA_ENTRY_GUIDE.md](file:///home/tek2991/Desktop/Works/Dwelly-V2/database/seeders/data/DATA_ENTRY_GUIDE.md)** which contains zero developer jargon, field-by-field guidance, allowed values, and common mistakes to avoid.

---

## 📁 Fast Folder-Based Asset Structure (Recommended)

Instead of manually typing long file paths for every property in the CSV, simply organize your files into folders named after the **`property_code`** (e.g. `GAU-0091`, `BLR-0091`):

```text
storage/app/seed_assets/properties/
└── GAU-0091/
    ├── mou.pdf             # Signed MOU document (or mou_signed.pdf, etc.)
    ├── tenancy.pdf         # Signed Tenancy Agreement (or agreement.pdf, etc.)
    └── photos/             # All property photos
        ├── 01_elevation.jpg # First photo will automatically be set as featured!
        ├── 02_living.jpg
        └── 03_bedroom.jpg
```

> **Alternative Location**: You can also put property folders right next to your CSV in:
> `database/seeders/data/properties/{property_code}/`

### How It Works:
1. **MOU File**: Looks for `mou.pdf` (or any `*mou*.pdf`) inside `{property_code}/`. Attaches to Spatie Media collection `signed_pdf`.
2. **Tenancy File**: Looks for `tenancy.pdf` or `agreement.pdf` (or `*tenan*.pdf` / `*agree*.pdf`) inside `{property_code}/`. Attaches to Spatie Media collection `signed_agreement`.
3. **Photos**: Looks inside `{property_code}/photos/` (or directly inside `{property_code}/`). Ingests all images (`.jpg`, `.jpeg`, `.png`, `.webp`, `.svg`), sorts them, copies to public storage, and assigns `is_featured = true` to the first photo.
4. **Graceful Fallbacks**: If any document or photo is omitted or not yet placed in the folder, the importer automatically generates an authentic digital PDF (via DomPDF) or an elevation image so that Filament previews and document viewers work without errors.

---

## 🚀 How to Run the Import / Seeder

### 1. Test / Validate without writing to database (Dry-Run):
```bash
php artisan dwelly:seed-properties-csv --dry-run
```

### 2. Execute Live Import from default template:
```bash
php artisan dwelly:seed-properties-csv
```

### 3. Execute Import from a custom CSV file:
```bash
php artisan dwelly:seed-properties-csv --file=/path/to/your_properties.csv
```

### 4. Or use standard Laravel Seeder:
```bash
php artisan db:seed --class=CsvPropertySeeder
```

---

## 📋 CSV Column Dictionary

The CSV is now simplified with no file path columns required:

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
| `property_status` | **Required** | String | `Occupied`, `Vacant`, `Under Maintenance`, `Under Notice` |
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
| `mou_number` | Optional | Auto-generated (`MOU-YYYY-XXXXX`) | E.g. `MOU-2026-00091` |
| `mou_start_date` | Optional | `YYYY-MM-DD` | Onboarding / MOU effective date |
| `mou_status` | Optional | `converted` | `converted`, `signed`, `verified`, `draft` |
| `mou_fee_percentage` | Optional | `8.0` | Dwelly management fee % |
| `rent_amount` | **Required** | Decimal (e.g. `24000`) | Monthly Rent in INR |
| `security_deposit` | **Required** | Decimal (e.g. `48000`) | Security Deposit in INR |
| `society_fee` | Optional | Decimal (e.g. `2000`) | Monthly maintenance fee |
| `tenant_name` | If `Occupied` | Text | Primary tenant's full legal name |
| `tenant_phone` | If `Occupied` | 10-digit mobile | Tenant's phone number |
| `tenant_email` | If `Occupied` | Valid email | Tenant's email address |
| `tenant_pan` | Optional | 10-character PAN | Tenant PAN |
| `tenant_aadhaar` | Optional | 12-digit numeric | Tenant Aadhaar |
| `agreement_code` | Optional | Auto-generated (`TNC-YYYY-XXXXX`) | Tenancy agreement code |
| `agreement_start_date` | If `Occupied` | `YYYY-MM-DD` | Tenancy start date |
| `agreement_end_date` | If `Occupied` | `YYYY-MM-DD` | Tenancy expiry date |
| `lock_in_months` | Optional | `6` | Lock-in period in months |
| `notice_period_days` | Optional | `30` | Notice period in days |

---

## 🛏️ Separate Specifications Checklist CSV (Rooms, Amenities, Inventory)

To keep property onboarding fast and clean, granular details like rooms, amenities, and inventory are kept in a separate checklist CSV:
`database/seeders/data/property_specifications_template.csv`

The system **links them up automatically via `property_code`**.

Every column accepts either:
- **Quantity counts**: e.g., `1`, `2`, `3`, `5`
- **Yes / No flags**: e.g., `yes` / `no`, `true` / `false` (counts as `1` or `0`)

### Column Mapping Dictionary

#### 1. Linking Column
- `property_code`: **Required**. Must match the `property_code` in the properties CSV (e.g. `GAU-0091`).

#### 2. Rooms (`room_*`)
- `room_living_room`: Living Room
- `room_kitchen`: Modular Kitchen
- `room_master_bedroom`: Master Bedroom
- `room_second_bedroom`: Second Bedroom
- `room_third_bedroom`: Third Bedroom
- `room_guest_bedroom`: Guest Bedroom
- `room_attached_bathroom`: Attached Bathroom (e.g. `2`)
- `room_common_bathroom`: Common Bathroom (e.g. `1`)
- `room_balcony`: Balconies (e.g. `2`)
- `room_pooja_room`: Pooja Room (`yes`/`no` or `1`/`0`)
- `room_servant_room`: Servant Room (`yes`/`no` or `1`/`0`)
- `room_study_room`: Study / Home Office (`yes`/`no` or `1`/`0`)

#### 3. Amenities (`amenity_*`)
- `amenity_lift`: Lift / Elevator (`yes`/`no`)
- `amenity_power_backup`: Full or DG Power Backup (`yes`/`no`)
- `amenity_security`: 24/7 Security & CCTV (`yes`/`no`)
- `amenity_parking`: Covered Car Parking (`yes`/`no`)
- `amenity_gym`: Society Fitness Gym (`yes`/`no`)
- `amenity_swimming_pool`: Swimming Pool (`yes`/`no`)
- `amenity_clubhouse`: Community Club House (`yes`/`no`)

#### 4. Inventory (`inv_*`)
- `inv_fan`: Ceiling Fans (quantity count, e.g. `4`)
- `inv_light`: LED Lights / Tube lights (quantity count, e.g. `8`)
- `inv_ac`: Air Conditioners (quantity count, e.g. `2`)
- `inv_bed`: Beds / Mattresses (quantity count, e.g. `2`)
- `inv_wardrobe`: Wardrobes / Closets (quantity count, e.g. `3`)
- `inv_sofa`: Sofa sets (quantity count, e.g. `1`)
- `inv_dining_set`: Dining table & chairs (e.g. `1`)
- `inv_geyser`: Water Heaters / Geysers (quantity count, e.g. `2`)
- `inv_fridge`: Refrigerator (quantity count, e.g. `1`)
- `inv_tv`: Smart TV (quantity count, e.g. `1`)
- `inv_washing_machine`: Washing Machine (quantity count, e.g. `1`)
- `inv_microwave`: Microwave (quantity count, e.g. `1`)
- `inv_water_purifier`: RO Water Purifier (quantity count, e.g. `1`)
- `inv_chimney`: Modular Kitchen Chimney (quantity count, e.g. `1`)
- `inv_keys`: Physical Property Keys (count, e.g. `3` or `4`)

---

## ⚡ Specifications Seeding Commands

### Option A: Seed Automatically with Properties
When you run:
```bash
php artisan dwelly:seed-properties-csv
```
It will automatically search for `property_specifications_template.csv` (or `property_specifications.csv`) in the same directory and merge specifications for each property.

You can also specify a custom specifications file:
```bash
php artisan dwelly:seed-properties-csv --file=/path/to/properties.csv --specs-file=/path/to/specs.csv
```

### Option B: Standalone Seed or Update Specifications
If properties already exist and you want to import or update their rooms, amenities, and inventory checklists:

```bash
# Dry-run validation:
php artisan dwelly:seed-property-specs --dry-run

# Live update:
php artisan dwelly:seed-property-specs

# Custom specs file:
php artisan dwelly:seed-property-specs --file=/path/to/custom_specifications.csv
```

