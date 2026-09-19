# 📋 Dwelly Property Onboarding — Data Entry Guide

Welcome! This guide explains step-by-step how to enter existing properties, owners, signing authorities, tenants, agreements, and photos into the Dwelly system using Excel or Google Sheets.

---

## 🧭 Overview: What You Need to Prepare

For every property you onboard, there are **4 parts**:

```
1. Main Properties Spreadsheet   ---> existing_properties_template.csv
2. Tenants Spreadsheet           ---> existing_tenants_template.csv
3. Checklist Spreadsheet         ---> property_specifications_template.csv
4. Documents & Photos Folder     ---> Folder named with the Property Code
```

> 🔑 **The Golden Key**: Every property must have a **unique Property Code** (e.g., `GAU-0091`, `BLR-0015`).
> This exact code connects the properties spreadsheet, tenants spreadsheet, checklist spreadsheet, and the photo folder together.

---

## ⚡ Quick Start: 4-Step Workflow

### Step 1: Open the CSV Files in Excel or Google Sheets

- Open `existing_properties_template.csv`
- Open `existing_tenants_template.csv`
- Open `property_specifications_template.csv`
- **Important**: Keep the first row (the column headers) unchanged. Do not rename, remove, or rearrange any column headers.

### Step 2: Fill in the Property & Owner Details

- Fill in one row per property in `existing_properties_template.csv`.
- Select whether the agreement is **Rent Sharing** (`is_rent_sharing = true`) or **Annual Subscription** (`is_rent_sharing = false`).
- Indicate if the MOU is signed by the owner directly or by an **Authorized Signatory / POA Holder** (`is_signatory_different = true`).

### Step 3: Fill in Tenant Details (For Occupied Properties)

- In `existing_tenants_template.csv`, add the occupants for each occupied property.
- Support **multiple tenants**: mark the main leaseholder with `is_primary_tenant = true`, and family members/roommates with `is_primary_tenant = false`.
- *Vacant properties do not need any rows in the tenants CSV.*

### Step 4: Organize Documents and Photos in Folders

- Create a folder named after the `property_code`:
    ```text
    storage/app/seed_assets/properties/GAU-0091/
    ├── mou.pdf              (Signed MOU between Dwelly and Owner)
    ├── poa.pdf              (Power of Attorney scan, if signatory is different)
    ├── tenancy.pdf          (Signed Tenancy Agreement with Tenant, if occupied)
    └── photos/
        ├── 01_front.jpg     (The first photo will automatically be the main cover photo!)
        ├── 02_living.jpg
        ├── 03_kitchen.jpg
        └── 04_bedroom.jpg
    ```
- _Don't worry if a photo or document is missing yet_ — the system will create a temporary placeholder so you can upload the real scan later through the admin dashboard!
- Save all files as **CSV UTF-8 (Comma delimited) (\*.csv)** when saving from Excel.

---

## 📑 File 1: Main Properties Spreadsheet (`existing_properties_template.csv`)

### Section A: Property Location & Identification

| Column Header    |  Required?  | Example              | Notes & Allowed Values                                                                            |
| ---------------- | :---------: | -------------------- | ------------------------------------------------------------------------------------------------- |
| `property_code`  | Recommended | `GAU-0091`           | Unique code for the property. Use city prefixes like `GAU-` for Guwahati or `BLR-` for Bangalore. |
| `building_name`  |   **YES**   | `Greenwood Enclave`  | Name of the apartment society, building, or villa project.                                        |
| `address_line_1` |   **YES**   | `Flat 4B, Tower 2`   | Flat number, door number, floor, or house number.                                                 |
| `address_line_2` |     No      | `Opposite State Zoo` | Landmark, street name, or additional address info.                                                |
| `locality`       |   **YES**   | `Zoo Road`           | Neighborhood or area name (e.g., `Whitefield`, `Christian Basti`, `Bellandur`).                    |
| `city`           |   **YES**   | `Guwahati`           | Must be either **`Guwahati`** or **`Bangalore`**.                                                 |
| `state`          |     No      | `Assam`              | State name (`Assam` or `Karnataka`). If left empty, it is filled automatically from the City.     |
| `pincode`        |   **YES**   | `781024`             | 6-digit postal code. Numbers only.                                                                |

---

### Section B: Property Specifications & Status

| Column Header      | Required? | Example          | Allowed Values (Must Match Exactly)                                        |
| ------------------ | :-------: | ---------------- | -------------------------------------------------------------------------- |
| `bhk_type`         |  **YES**  | `2 BHK`          | `1 BHK`, `2 BHK`, `3 BHK`, `4 BHK`, `5+ BHK`, or `1 RK`                    |
| `property_type`    |  **YES**  | `Apartment`      | `Apartment`, `Independent House`, `Villa`, `Builder Floor`, or `Studio`    |
| `furnishing_type`  |  **YES**  | `Semi-Furnished` | `Fully Furnished`, `Semi-Furnished`, or `Unfurnished`                      |
| `floor`            |    No     | `4`              | Floor number of this unit (e.g., `0` for ground floor, `4` for 4th floor). |
| `total_floors`     |    No     | `8`              | Total number of floors in the building.                                    |
| `floor_space_sqft` |    No     | `1150`           | Super built-up or carpet area in square feet (numbers only).               |
| `property_status`  |  **YES**  | `Occupied`       | Must be either **`Occupied`** or **`Vacant`** (or `Under Maintenance`).    |
| `is_listed`        |    No     | `true`           | `true` to display on the public website, `false` to keep private.          |
| `available_from`   |    No     | `2026-04-01`     | Date format: `YYYY-MM-DD`.                                                 |

---

### Section C: Owner & Banking Details

| Column Header        | Required? | Example                   | Notes                                            |
| -------------------- | :-------: | ------------------------- | ------------------------------------------------ |
| `owner_name`         |  **YES**  | `Dr. Himanta Barua`       | Full legal name of the property owner.           |
| `owner_phone`        |  **YES**  | `9876543210`              | 10-digit mobile number. **No `+91`, no spaces**. |
| `owner_email`        |  **YES**  | `himanta.barua@gmail.com` | Valid email address.                             |
| `owner_pan`          |    No     | `ABCDE1234F`              | 10-character PAN number.                         |
| `owner_aadhaar`      |    No     | `987654321012`            | 12-digit Aadhaar number.                         |
| `owner_bank_name`    |    No     | `HDFC Bank`               | Bank name for owner payouts.                     |
| `owner_bank_account` |    No     | `50100456789012`          | Bank account number.                             |
| `owner_bank_ifsc`    |    No     | `HDFC0000084`             | 11-character IFSC code.                          |

---

### Section D: Commercial Terms & MOU

| Column Header        | Required? | Example      | Notes                                                                                    |
| -------------------- | :-------: | ------------ | ---------------------------------------------------------------------------------------- |
| `mou_start_date`     |    No     | `2025-10-01` | Date signed with owner (`YYYY-MM-DD`). Defaults to current date if left empty.           |
| `mou_status`         |    No     | `converted`  | Usually `converted` for active managed properties.                                       |
| `is_rent_sharing`    |  **YES**  | `true`       | **`true`** for Rent Share model (% fee). **`false`** for Annual Subscription (0% fee).    |
| `mou_fee_percentage` |    No     | `8.0`        | Dwelly management fee % (e.g., `8.0`). Automatically set to `0.0` if `is_rent_sharing` is `false`. |
| `rent_amount`        |  **YES**  | `24000`      | Monthly rent in Rupees (**numbers only, no commas or ₹**).                               |
| `security_deposit`   |  **YES**  | `48000`      | Security deposit in Rupees (**numbers only, no commas**).                                |
| `society_fee`        |    No     | `2000`       | Monthly maintenance/society charges in Rupees (enter `0` if included).                    |

> ⚙️ **System Generated MOU Numbers**: The MOU number (e.g. `MOU-2026-00001`) is **automatically generated** by Dwelly's Numbering Service. You do not need to fill or track agreement codes.

---

### Section E: Signing Authority & Representative Details

In Dwelly, an owner may sign their MOU directly, or appoint an Authorized Signatory / Power of Attorney (POA) holder (e.g., family member, property manager, legal representative):

| Column Header            | Required?     | Example                 | Notes                                                                                 |
| ------------------------ | :-----------: | ----------------------- | ------------------------------------------------------------------------------------- |
| `is_signatory_different` |  **YES**      | `false`                 | **`false`** if owner signs directly (Self). **`true`** if signed by POA/representative. |
| `signatory_name`         | If different  | `Anupam Saikia`         | Full legal name of the authorized representative.                                     |
| `signatory_relation`     | If different  | `POA Holder / Son`      | Relationship to owner (e.g. `POA Holder / Son`, `Spouse`, `Legal Representative`).   |
| `signatory_phone`        | If different  | `9435099999`            | 10-digit mobile number of representative.                                             |
| `signatory_email`        | If different  | `anupam.saikia@yahoo.com`| Representative's email address.                                                       |
| `signatory_pan`          | No            | `DAEPS3456P`            | 10-character PAN of representative.                                                   |
| `signatory_aadhaar`      | No            | `887654321098`          | 12-digit Aadhaar of representative.                                                   |

> 📄 **POA Scan Upload**: If `is_signatory_different` is `true`, place the Power of Attorney deed or authorization letter inside the property's asset folder as `poa.pdf` (or `poa_deed.pdf`).

---

## 👥 File 2: Dedicated Tenants Spreadsheet (`existing_tenants_template.csv`)

Dwelly supports **multiple occupants per property** (such as a primary tenant plus a spouse, child, or roommate). All occupants for occupied properties are specified in this spreadsheet.

### Tenant Columns

| Column Header          | Required?     | Example                     | Notes                                                                                |
| ---------------------- | :-----------: | --------------------------- | ------------------------------------------------------------------------------------ |
| `property_code`        |   **YES**     | `GAU-0091`                  | **Must match the exact `property_code`** from File 1.                                |
| `is_primary_tenant`    |   **YES**     | `true`                      | **`true`** for the primary leaseholder/billing contact; **`false`** for co-occupants.|
| `name`                 |   **YES**     | `Abhishek Sen`              | Full legal name of the occupant.                                                     |
| `relationship`         |      No       | `Self` or `Spouse`          | Relationship to primary tenant (e.g., `Self`, `Spouse`, `Son`, `Daughter`, `Roommate`).|
| `phone`                | If Primary    | `9876500001`                | 10-digit mobile number (**no `+91`, no spaces**).                                    |
| `email`                | If Primary    | `abhishek.sen@gmail.com`    | Valid email address.                                                                 |
| `address`              |      No       | `Flat 3B, Zoo Road, GHY`    | Permanent or previous address.                                                       |
| `pan`                  |      No       | `XYZPE5678K`                | 10-character PAN number. (Header can also be named `pad` or `pan_number`).            |
| `aadhaar`              |      No       | `123456789012`              | 12-digit Aadhaar card number.                                                        |
| `parent_name`          |      No       | `Arun Sen`                  | Father's / Guardian's full name.                                                     |
| `voter_id`              |      No       | `ABC1234567`                | Voter ID card number.                                                                |
| `agreement_start_date` | If Primary    | `2026-01-01`                | Tenancy agreement start date (`YYYY-MM-DD`).                                         |
| `agreement_end_date`   | If Primary    | `2026-12-31`                | Tenancy agreement expiry date (`YYYY-MM-DD`).                                        |
| `lock_in_months`       |      No       | `6`                         | Lock-in period in months (defaults to 6).                                            |
| `notice_period_days`   |      No       | `30`                        | Notice period in days (defaults to 30).                                              |

> 💡 **Multi-Tenant Example**:
> - For property `GAU-0091`:
>   - Row 1: `property_code = GAU-0091`, `is_primary_tenant = true`, `name = Abhishek Sen`, `relationship = Self`, agreement dates specified.
>   - Row 2: `property_code = GAU-0091`, `is_primary_tenant = false`, `name = Priya Sen`, `relationship = Spouse`.
>
> ⚙️ **System Generated Agreement Codes**: Tenancy Agreement codes (e.g. `TNC-2026-00001`) are **automatically generated** by the system.

---

## 🛏️ File 3: Specifications Checklist Spreadsheet (`property_specifications_template.csv`)

This separate spreadsheet lets you record the exact number of rooms, society amenities, and appliance/furniture counts.

### 1. Linking Column

- `property_code`: **MUST match the exact `property_code` from File 1** (e.g. `GAU-0091`).

---

### 2. Rooms Columns (Enter Quantity Count or `yes` / `no`)

| Column Name              | Description                          | What to Type                      |
| ------------------------ | ------------------------------------ | --------------------------------- |
| `room_living_room`       | Living Hall                          | `1` (or count)                    |
| `room_kitchen`           | Kitchen                              | `1`                               |
| `room_master_bedroom`    | Master Bedroom                       | `1`                               |
| `room_second_bedroom`    | 2nd Bedroom                          | `1` or `0`                        |
| `room_third_bedroom`     | 3rd Bedroom                          | `1` or `0`                        |
| `room_guest_bedroom`     | Guest Bedroom                        | `1` or `0`                        |
| `room_attached_bathroom` | Attached Bathrooms (inside bedrooms) | Type count: `1`, `2`, or `3`      |
| `room_common_bathroom`   | Common / Hall Bathroom               | Type count: `1` or `2`            |
| `room_balcony`           | Balconies                            | Type count: `1`, `2`, or `3`      |
| `room_pooja_room`        | Pooja / Prayer Room                  | Type `yes` or `no` (or `1` / `0`) |
| `room_servant_room`      | Servant / Utility Room               | Type `yes` or `no` (or `1` / `0`) |
| `room_study_room`        | Study Room / Home Office             | Type `yes` or `no` (or `1` / `0`) |

---

### 3. Society Amenities Columns (Type `yes` or `no`)

| Column Name             | Amenity Description                  | Allowed Values |
| ----------------------- | ------------------------------------ | -------------- |
| `amenity_lift`          | Elevator / Lift                      | `yes` or `no`  |
| `amenity_power_backup`  | Generator / Inverter Power Backup    | `yes` or `no`  |
| `amenity_security`      | 24/7 Security Guard / CCTV           | `yes` or `no`  |
| `amenity_parking`       | Dedicated Covered / Open Car Parking | `yes` or `no`  |
| `amenity_gym`           | Society Fitness Gym                  | `yes` or `no`  |
| `amenity_swimming_pool` | Swimming Pool                        | `yes` or `no`  |
| `amenity_clubhouse`     | Community Club House                 | `yes` or `no`  |

---

### 4. Inventory Checklist Columns (Enter Quantity Number)

Enter how many items exist in the property:

| Column Name           | Appliance / Furniture Item          | Example Count |
| --------------------- | ----------------------------------- | :-----------: |
| `inv_fan`             | Ceiling Fans                        |      `4`      |
| `inv_light`           | Tube lights / LED fixtures          |      `8`      |
| `inv_ac`              | Air Conditioners (ACs)              |      `2`      |
| `inv_bed`             | Beds with Mattress                  |      `2`      |
| `inv_wardrobe`        | Wardrobes / Cupboards               |      `3`      |
| `inv_sofa`            | Sofa sets                           |      `1`      |
| `inv_dining_set`      | Dining Table & Chairs               |      `1`      |
| `inv_geyser`          | Water Heaters / Geysers             |      `2`      |
| `inv_fridge`          | Refrigerator                        |      `1`      |
| `inv_tv`              | Television (TV)                     |      `1`      |
| `inv_washing_machine` | Washing Machine                     |      `1`      |
| `inv_microwave`       | Microwave Oven                      |      `1`      |
| `inv_water_purifier`  | RO Water Purifier                   |      `1`      |
| `inv_chimney`         | Kitchen Exhaust Chimney             |      `1`      |
| `inv_keys`            | Number of physical keys handed over |      `3`      |

> 💡 _If an item is not present, simply enter `0` or leave it empty._

---

## 📁 Part 4: Documents & Photos Folder Organization

Organize all scans and photos inside a folder named after the `property_code`:

```text
storage/app/seed_assets/properties/
│
├── GAU-0091/                      <--- (Folder named exactly as property_code)
│   ├── mou.pdf                   <--- Owner MOU scan
│   ├── poa.pdf                   <--- Power of Attorney scan (if signatory is different)
│   ├── tenancy.pdf               <--- Tenant Agreement scan (if occupied)
│   └── photos/                   <--- Subfolder for all photos
│       ├── 01_elevation.jpg      <--- "01_" will be the main cover picture on website!
│       ├── 02_living_room.jpg
│       ├── 03_kitchen.jpg
│       ├── 04_master_bed.jpg
│       └── 05_bathroom.jpg
│
├── BLR-0091/
│   ├── mou.pdf
│   ├── poa.pdf
│   ├── tenancy.pdf
│   └── photos/
│       ├── 01_front.jpg
│       └── 02_hall.jpg
```

### Supported File Formats:

- **Documents**: `.pdf`
- **Photos**: `.jpg`, `.jpeg`, `.png`, `.webp`

---

## 🚫 Top 8 Mistakes to Avoid

| #   | What NOT to do                                                                                              | What to do instead                                         |
| --- | ----------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| 1   | **Do not write currency symbols or commas**:<br>❌ `₹25,000` or `25,000`                                    | Type plain numbers:<br>✅ `25000`                          |
| 2   | **Do not add country codes or spaces in phone numbers**:<br>❌ `+91-98765-43210` or `98765 43210`           | 10 continuous digits only:<br>✅ `9876543210`              |
| 3   | **Do not use DD/MM/YYYY for dates**:<br>❌ `15/01/2026` or `15-Jan-2026`                                    | Always use Year-Month-Day:<br>✅ `2026-01-15`              |
| 4   | **Do not put tenant info for Vacant properties**:<br>❌ Putting "N/A" or old tenant details                 | Leave vacant properties out of the tenants CSV completely. |
| 5   | **Do not invent new city names**:<br>❌ `Guwahati City` or `Bengaluru`                                      | Use standard branch names:<br>✅ `Guwahati` or `Bangalore` |
| 6   | **Do not delete or rename CSV column headers**:<br>❌ Changing `rent_amount` to `Monthly Rent`              | Keep Row 1 exactly as provided in the template.            |
| 7   | **Do not hardcode agreement or MOU numbers**:<br>❌ Trying to invent custom MOU codes                        | The system generates sequential `MOU-*` and `TNC-*` codes. |
| 8   | **Do not save as `.xlsx` (Excel Workbook)**:<br>❌ `properties.xlsx`                                        | Save as **CSV UTF-8 (Comma delimited) (\*.csv)**.          |

---

## ❓ Frequently Asked Questions (FAQ)

**Q: What if I don't have the signed MOU PDF, POA deed, or tenancy agreement PDF right now?**  
A: That is completely fine! You can still submit the spreadsheets. The system will automatically generate a temporary authentic placeholder document so previews and admin viewers work seamlessly. When you receive the physical document later, you can replace it directly in the Dwelly admin dashboard.

**Q: What is the difference between `is_rent_sharing = true` and `is_rent_sharing = false`?**  
A:
- When `is_rent_sharing` is `true`, the property is enrolled under the **Rent share** model where Dwelly manages the property for a monthly commission percentage (`mou_fee_percentage`, e.g. 8%).
- When `is_rent_sharing` is `false`, the property is enrolled under the **Annual subscription** model where the owner pays an annual management subscription with 0% commission on rent.

**Q: How do secondary tenants work?**  
A:
For any property with more than one resident (e.g. spouse, child, roommate), add a separate row in `existing_tenants_template.csv` using the same `property_code`, setting `is_primary_tenant = false`. They will be registered in Dwelly and listed on the tenancy agreement as co-occupants.

**Q: How do I choose which photo appears first on the website?**  
A: Prefix the image filenames with numbers in the `photos/` folder:
- `01_main_exterior.jpg` (will become the featured cover photo)
- `02_living_room.jpg`
- `03_bedroom.jpg`

**Q: Can I use `1` and `0` instead of `yes` and `no` for amenities?**  
A: Yes! Both `yes`/`no` and `1`/`0` work interchangeably.

**Q: What should I enter for `society_fee` if maintenance is included in rent?**  
A: Enter `0`.

**Q: Who do I contact if the system reports an error during import?**  
A: Ask the tech team to run the validation check (`--dry-run`). The system will provide the exact row number and column name that needs correction.
