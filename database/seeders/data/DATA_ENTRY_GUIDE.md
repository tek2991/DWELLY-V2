# 📋 Dwelly Property Onboarding — Data Entry Guide

Welcome! This guide explains step-by-step how to enter existing properties, owners, tenants, agreements, and photos into the Dwelly system using Excel or Google Sheets.

---

## 🧭 Overview: What You Need to Prepare

For every property you onboard, there are **3 parts**:

```
1. Main Properties Spreadsheet   ---> existing_properties_template.csv
2. Checklist Spreadsheet         ---> property_specifications_template.csv
3. Documents & Photos Folder     ---> Folder named with the Property Code
```

> 🔑 **The Golden Key**: Every property must have a **unique Property Code** (e.g., `GAU-0091`, `BLR-0015`).
> This exact code connects the spreadsheet, the checklist, and the photo folder together.

---

## ⚡ Quick Start: 3-Step Workflow

### Step 1: Open the CSV Files in Excel or Google Sheets

- Open `existing_properties_template.csv`
- Open `property_specifications_template.csv`
- **Important**: Keep the first row (the column headers) unchanged. Do not rename, remove, or rearrange any column headers.

### Step 2: Fill in the Details

- Fill in one row per property in `existing_properties_template.csv`.
- In `property_specifications_template.csv`, enter the matching `property_code` in the first column, then fill in the number of rooms, amenities (`yes`/`no`), and inventory counts.

### Step 3: Organize Documents and Photos in Folders

- Create a folder named after the `property_code`:
    ```text
    storage/app/seed_assets/properties/GAU-0091/
    ├── mou.pdf              (Signed MOU between Dwelly and Owner)
    ├── tenancy.pdf          (Signed Tenancy Agreement with Tenant, if occupied)
    └── photos/
        ├── 01_front.jpg     (The first photo will automatically be the main cover photo!)
        ├── 02_living.jpg
        ├── 03_kitchen.jpg
        └── 04_bedroom.jpg
    ```
- _Don't worry if a photo or document is missing yet_ — the system will create a temporary placeholder so you can upload the real scan later through the admin dashboard!

### Step 4: Save Files as "CSV UTF-8"

- When saving from Excel, select: **Save As** ➔ File Format: **CSV UTF-8 (Comma delimited) (\*.csv)**.

---

## 📑 File 1: Main Properties Spreadsheet (`existing_properties_template.csv`)

### Section A: Property Location & Identification

| Column Header    |  Required?  | Example              | Notes & Allowed Values                                                                            |
| ---------------- | :---------: | -------------------- | ------------------------------------------------------------------------------------------------- |
| `property_code`  | Recommended | `GAU-0091`           | Unique code for the property. Use city prefixes like `GAU-` for Guwahati or `BLR-` for Bangalore. |
| `building_name`  |   **YES**   | `Greenwood Enclave`  | Name of the apartment society, building, or villa project.                                        |
| `address_line_1` |   **YES**   | `Flat 4B, Tower 2`   | Flat number, door number, floor, or house number.                                                 |
| `address_line_2` |     No      | `Opposite State Zoo` | Landmark, street name, or additional address info.                                                |
| `locality`       |   **YES**   | `Zoo Road`           | Neighborhood or area name (e.g., `Whitefield`, `Christian Basti`).                                |
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

| Column Header        | Required? | Example          | Notes                                                      |
| -------------------- | :-------: | ---------------- | ---------------------------------------------------------- |
| `mou_number`         |    No     | `MOU-2026-00091` | MOU agreement number (auto-generated if empty).            |
| `mou_start_date`     |    No     | `2025-10-01`     | Date signed with owner (`YYYY-MM-DD`).                     |
| `mou_status`         |    No     | `converted`      | Usually `converted` for active managed properties.         |
| `mou_fee_percentage` |    No     | `8.0`            | Dwelly management fee % (defaults to 8%).                  |
| `rent_amount`        |  **YES**  | `24000`          | Monthly rent in Rupees (**numbers only, no commas or ₹**). |
| `security_deposit`   |  **YES**  | `48000`          | Security deposit in Rupees (**numbers only, no commas**).  |
| `society_fee`        |    No     | `2000`           | Monthly maintenance/society charges in Rupees.             |

---

### Section E: Tenant & Tenancy Agreement _(ONLY IF PROPERTY IS OCCUPIED)_

> ⚠️ **CRITICAL RULE**:
>
> - If `property_status` is **`Occupied`**, you **MUST** fill in `tenant_name`, `tenant_phone`, and `tenant_email`.
> - If `property_status` is **`Vacant`**, **LEAVE ALL TENANT COLUMNS COMPLETELY BLANK**.

| Column Header          | Required when Occupied? | Example                  | Notes                                             |
| ---------------------- | :---------------------: | ------------------------ | ------------------------------------------------- |
| `tenant_name`          |         **YES**         | `Abhishek Sen`           | Tenant's full legal name.                         |
| `tenant_phone`         |         **YES**         | `9876500001`             | 10-digit mobile number (**no `+91`, no spaces**). |
| `tenant_email`         |         **YES**         | `abhishek.sen@gmail.com` | Tenant's email address.                           |
| `tenant_pan`           |           No            | `XYZPE5678K`             | Tenant PAN card number.                           |
| `tenant_aadhaar`       |           No            | `123456789012`           | 12-digit Aadhaar number.                          |
| `agreement_code`       |           No            | `TNC-2026-00091`         | Tenancy agreement code (auto-generated if empty). |
| `agreement_start_date` |         **YES**         | `2026-01-01`             | Tenancy start date (`YYYY-MM-DD`).                |
| `agreement_end_date`   |         **YES**         | `2026-12-31`             | Tenancy end date (`YYYY-MM-DD`).                  |
| `lock_in_months`       |           No            | `6`                      | Lock-in period in months (defaults to 6).         |
| `notice_period_days`   |           No            | `30`                     | Notice period in days (defaults to 30).           |

---

## 🛏️ File 2: Specifications Checklist Spreadsheet (`property_specifications_template.csv`)

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

## 📁 File 3: Documents & Photos Folder Organization

Organize all scans and photos inside a folder named after the `property_code`:

```text
storage/app/seed_assets/properties/
│
├── GAU-0091/                      <--- (Folder named exactly as property_code)
│   ├── mou.pdf                   <--- Owner MOU scan
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
| 4   | **Do not put tenant info for Vacant properties**:<br>❌ Putting "N/A" or old tenant details                 | Leave all tenant columns blank if status is `Vacant`.      |
| 5   | **Do not invent new city names**:<br>❌ `Guwahati City` or `Bengaluru`                                      | Use standard branch names:<br>✅ `Guwahati` or `Bangalore` |
| 6   | **Do not delete or rename CSV column headers**:<br>❌ Changing `rent_amount` to `Monthly Rent`              | Keep Row 1 exactly as provided in the template.            |
| 7   | **Do not leave Property Code blank if you have photos**:<br>❌ Naming folder `Greenwood` and CSV `GAU-0091` | The folder name must match the `property_code` in the CSV. |
| 8   | **Do not save as `.xlsx` (Excel Workbook)**:<br>❌ `properties.xlsx`                                        | Save as **CSV UTF-8 (Comma delimited) (\*.csv)**.          |

---

## ❓ Frequently Asked Questions (FAQ)

**Q: What if I don't have the signed MOU PDF or tenancy agreement PDF right now?**  
A: That is completely fine! You can still submit the spreadsheet. The system will automatically create a temporary placeholder document. When you receive the physical document later, you can upload it directly on the Dwelly admin website.

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
