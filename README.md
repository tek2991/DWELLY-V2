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

### PDF Generation (Important!)

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

## Running the Application

Run migrations and seed the database:
```bash
php artisan migrate:fresh --seed
```

Start the development server:
```bash
composer run dev
```
