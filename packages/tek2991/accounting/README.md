# 🧾 Tek2991 Accounting Plugin for Filament

> *"Because nothing screams 'fun weekend project' quite like double-entry bookkeeping!"* 🎉

Welcome to the **Tek2991 Accounting Plugin** for Filament! This package transforms your Laravel application into a robust, double-entry accounting powerhouse. Forget about manually calculating debits and credits—this plugin handles the heavy lifting so you can focus on building your actual app instead of reinventing the (financial) wheel.

## ✨ Features That Make Your Accountant Cry Tears of Joy

- **💸 Double-Entry Magic:** Every invoice, bill, and manual journal entry automatically balances. Because if it doesn't balance, is it even accounting?
- **🏦 Chart of Accounts:** Fully customizable accounts spanning Assets, Liabilities, Equity, Revenue, and Expenses. Create sub-accounts to your heart's content.
- **🧾 Invoices & Bills:** Complete lifecycle management for customer sales (Invoices) and vendor purchases (Bills). Track drafts, post them to the ledger, and collect payments.
- **🔄 Credit & Debit Notes:** Made a mistake? Customer returned an item? Issue Credit and Debit Notes to reverse revenue/expenses without breaking a sweat.
- **🔒 Fiscal Period Lockouts:** Prevent *that one guy* from back-dating transactions into last year's closed books. Period locking enforced right at the database transaction layer.
- **🛠️ Pessimistic Locking:** Database-level `lockForUpdate()` ensures that concurrent requests don't double-post your journals. Take *that*, race conditions!

## 🚀 Installation

*Note: You'll need a Filament panel installed and configured in your Laravel app.*

composer require tek2991/accounting
```

### PDF Generation Prerequisites

This package relies on `spatie/laravel-pdf` and defaults to generating PDFs via **Puppeteer** (using Browsershot). Ensure you have Node and NPM available in your environment, and that Puppeteer is installed:

```bash
npm install -g puppeteer
```

For the system to locate the required binaries, especially in environments where `$PATH` may not resolve correctly, you must specify their absolute paths in your `.env` file:

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

If Puppeteer is unavailable or fails, the plugin will automatically fallback to **DOMPDF** (pure PHP) to ensure your PDFs still generate.

### Publish & Migrate

Publish the package migrations and migrate your database:

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --tag="accounting-migrations"
php artisan notifications:table
php artisan migrate
```

### Register the Plugin

Add the plugin to your Filament Panel provider (e.g., `app/Providers/Filament/AdminPanelProvider.php`):

```php
use Tek2991\Accounting\AccountingPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(AccountingPlugin::make())
        ->databaseNotifications() // Required for export completion notifications
        // ...
}
```

### ⚠️ Important: Branch Management

The accounting package **does not** manage or migrate branches itself, as this is typically handled by the main application's tenancy/location logic. You **must** create a branches migration in your main app. 

Your branches table must include the following fields to be compatible:
- `organization_id` (foreign key)
- `gst_registration_id` (foreign key, nullable)
- `name` (string)
- `code` (string, unique)
- `address`, `city`, `district`, `state_id`, `postal_code`, `phone`, `email` (nullable)
- `is_active` (boolean, default true)


## 🎮 How to Use (Without Breaking the Law)

### 1. Set Up Your Chart of Accounts
Head over to **Settings > Chart of Accounts**. The plugin comes with a basic structure, but you can add your specific Bank Accounts, Expense categories, and Revenue streams here. 

### 2. Configure Taxes
Taxes are inevitable. Go to **Settings > Taxes** and set up your tax rates. Map them to your Liability (for sales) and Expense (for purchases) accounts so the system knows where to route the government's cut.

### 3. Start Invoicing
Go to **Sales > Invoices**. Create a draft, add some line items, and hit **Post**.
*Boom!* The system just debited Accounts Receivable and credited Revenue behind the scenes. You're officially making money! 🤑

### 4. Lock Your Periods
When the month ends, go to **Settings > Fiscal Periods**, create the period, and lock it. Relax knowing your historical data is safe from tampering.

## 🐛 Troubleshooting

**"I keep getting an 'Unbalanced Journal Entry' error!"**
- That's not a bug, that's Accounting 101. Make sure your debits equal your credits, or the Ghost of Luca Pacioli will haunt your server.

**"I tried to edit a posted invoice and it yelled at me."**
- Posted documents are immutable. You must Cancel the document or issue a Credit Note. This is a feature, not a bug!

## 📜 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information. Now go forth and balance those books! ⚖️