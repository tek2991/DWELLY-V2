# Dwelly-V2

Dwelly-V2 is a comprehensive property management and accounting application built on Laravel and Filament.

## Setup Instructions

1. **Clone the repository**
2. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```
3. **Environment Setup**:
   Copy the example `.env` file:
   ```bash
   cp .env.example .env
   ```
   Generate application key:
   ```bash
   php artisan key:generate
   ```

### Default Credentials (Seeded)
When seeded via any seeder (`CsvDataSeeder`, `SystemBaseSeeder`, or default `DatabaseSeeder`):
* **Operations Dashboard**: `/operations`
* **Default Password**: `password` (for all seeded staff accounts)

| User | Email | Role | Branch Access |
|---|---|---|---|
| **Dwelly Admin** | `admin@dwelly.in` | `Business Owner` | Guwahati (`GHY`), Bangalore (`BLR`) |
| **Rahul Goswami** | `rahul.operations@dwelly.in` | `Operations Executive` | Guwahati (`GHY`) |
| **Ananya Sharma** | `ananya.reviewer@dwelly.in` | `Operations Manager` | Guwahati (`GHY`), Bangalore (`BLR`) |

---

## 🗄️ Database Seeding & Data Workflows

The application supports multiple modular seeding workflows depending on whether you want a clean database with real CSV data, a complete mock demo environment, or system-only foundational data.

### 1. Mode A: Clean Foundation + CSV Imports (Recommended for Real Data)
Seeds all foundational system data (roles, users, branches, accounting chart of accounts, reference data) and imports real properties, tenants, and specifications from the CSV files:

```bash
php artisan migrate:fresh
php artisan db:seed --class=CsvDataSeeder
```
*(Alternatively, `php artisan db:seed --class=CsvPropertySeeder`)*

#### What gets imported:
* **Properties**: Reads from `database/seeders/data/existing_properties_data.csv` (fallback: `existing_properties_template.csv`).
* **Tenants**: Automatically paired from `existing_tenants_data.csv` (fallback: `existing_tenants_template.csv`).
* **Specifications**: Automatically paired from `property_specifications_data.csv` (fallback: `property_specifications_template.csv`).
* **Missing Emails**: If an owner or tenant email is blank in the CSV, the system automatically generates `{phonenumber}@no-email.com` to ensure Party and accounting subledger records are created seamlessly.
* **Vacant Units**: Properties marked as `Vacant` without tenant records are imported as vacant units without creating phantom agreements.

### 2. Mode B: Complete Mock Demo Environment (Development Testing)
Seeds foundational system data plus full demonstration records (mock tenants, mock properties, maintenance requests, vendor quotes, and demo accounting invoices/bills):

```bash
php artisan migrate:fresh --seed
# or:
php artisan db:seed
```

### 3. Mode C: Foundational System Setup Only (Zero Properties / Zero Tenants)
Seeds only the foundational system data without any property or tenant records (useful before manual entry or testing API endpoints):

```bash
php artisan migrate:fresh
php artisan db:seed --class=SystemBaseSeeder
```

---

## 🧹 Storage Clean-up (Before Fresh Seeding)

To perform a clean reset of media attachments and logs while preserving required raw seeding assets (`seed_assets`):

```bash
# Clean generated public & private media uploads (keeping .gitignore)
find storage/app/public -mindepth 1 -maxdepth 1 ! -name ".gitignore" -exec rm -rf {} +
find storage/app/private -mindepth 1 -maxdepth 1 ! -name ".gitignore" -exec rm -rf {} +

# Truncate laravel log & clear caches
> storage/logs/laravel.log
php artisan optimize:clear
```

> [!IMPORTANT]
> **Never delete `storage/app/seed_assets/`**: It holds master photos, sample signed MOUs, and lease PDFs used by the importers and seeders.

---

## PDF Generation (Important!)

The application uses the `tek2991/accounting` package which relies on `spatie/laravel-pdf` and **Puppeteer** (via Browsershot) for generating high-quality PDFs. 

To ensure PDFs generate correctly, you must specify the absolute paths to Node, NPM, and Google Chrome in your `.env` file. These paths must be accurate for your environment.

Add the following to your `.env`:

```env
BROWSERSHOT_NODE_BINARY=/path/to/your/node
BROWSERSHOT_NPM_BINARY=/path/to/your/npm
BROWSERSHOT_CHROME_BINARY=/path/to/your/google-chrome
```

> [!TIP]
> **How to find these paths:**
> Open your terminal and run the following commands to find the absolute paths on your system:
> - For Node: `which node` (e.g., `/usr/bin/node` or `~/.nvm/versions/node/.../bin/node`)
> - For NPM: `which npm` (e.g., `/usr/bin/npm`)
> - For Chrome: `which google-chrome` or `which chromium-browser`

If Puppeteer fails or these variables are not set correctly, the system will attempt to fallback to **DOMPDF** (a pure PHP renderer), but the formatting may differ from the Puppeteer version.

---

## Running the Application

Start the development server:
```bash
composer run dev
```
