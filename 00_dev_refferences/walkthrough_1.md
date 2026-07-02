# Branch Management Migration Walkthrough

The branch management system has been successfully decoupled from the `accounting` package and integrated into the main application. This gives you full control over branch assignment and access policies in the host app, while the accounting package simply "listens" to the selected branch context.

## What changed?

### 1. Database & Migrations
- Deleted the `create_branches_table` migration from the accounting package.
- Created `2026_07_01_xxxx_create_branches_table.php` in the main app's `database/migrations` directory.
- Created `2026_07_01_xxxx_create_branch_user_table.php` in the main app to act as a pivot table for assigning branches to users.

### 2. Models & Relationships
- Moved the `Branch` model from `Tek2991\Accounting\Models\Branch` to `App\Models\Branch`.
- Added a `branches()` `belongsToMany` relationship to the `App\Models\User` model.
- Updated all references across the accounting package to point to `App\Models\Branch`.

### 3. Filament Resources
- Moved `BranchResource` and its associated pages out of the accounting package and into `App\Filament\Resources\Settings\Branches\BranchResource`.
- It will now be automatically discovered and shown in your main app's panels.

### 4. Role-based Multi-Branch Visibility
- Enhanced the `BranchContext::applyQueryScope()` logic to restrict data visibility.
- If a user selects **"All Branches"**, the system checks if they have the `'admin'` role:
  - **Admins:** See data across *all* active branches in the system.
  - **Non-admins:** See data restricted *only* to the branches assigned to them via the `branch_user` pivot.
- Updated the `BranchSelector` Livewire component to only list branches the user has access to.

### 5. Package Documentation
- Updated `packages/tek2991/accounting/README.md` to explicitly state that the host application must provide a `branches` table migration, and documented the required fields.

## Next Steps for You
Since the foundation is laid out, you can now:
1. Run `php artisan migrate` to create the new tables.
2. (Optional) Create a `UserResource` in the main app to manage the assignment of users to branches easily via the UI.
3. Test logging in as a non-admin to confirm the dropdown accurately filters to only their assigned branches.
