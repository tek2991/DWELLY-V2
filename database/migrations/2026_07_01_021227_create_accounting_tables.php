<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function prefix(): string
    {
        return config('accounting.table_prefix', 'acc_');
    }

    public function up(): void
    {
        $prefix = $this->prefix();

        // ──────────────────────────────────────────────────────────
        // Chart of Accounts
        // ──────────────────────────────────────────────────────────
        Schema::create("{$prefix}accounts", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained("{$prefix}accounts")
                ->nullOnDelete();
            $table->foreignId('contact_id')
                ->nullable()
                ->constrained("{$prefix}contacts")
                ->nullOnDelete();
            
            $table->string('type', 20);              // AccountType enum
            $table->string('reporting_class', 30)->nullable();  // ReportingClass enum
            $table->string('system_role', 30)->nullable();      // SystemRole enum
            $table->boolean('is_control_account')->default(false);
            
            $table->string('code', 20)->nullable();
            $table->string('name');
            $table->string('currency_code', 3)->default(config('accounting.default_currency', 'INR'));
            $table->text('description')->nullable();
            
            $table->boolean('archived')->default(false);
            $table->boolean('default')->default(false);
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('code', "{$prefix}accounts_code_unique");
            $table->index('type', "{$prefix}accounts_type_idx");
            $table->index('reporting_class', "{$prefix}accounts_rep_class_idx");
            $table->index('system_role', "{$prefix}accounts_sys_role_idx");
            $table->index('archived', "{$prefix}accounts_archived_idx");
        });

        // ──────────────────────────────────────────────────────────
        // Transactions
        // ──────────────────────────────────────────────────────────
        Schema::create("{$prefix}transactions", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained("{$prefix}branches")->restrictOnDelete();
            $table->foreignId('account_id')
                ->nullable()
                ->constrained("{$prefix}accounts")
                ->nullOnDelete();
            $table->string('type', 20);       // TransactionType enum
            $table->string('description');
            $table->text('notes')->nullable();
            
            $table->string('reference')->nullable();
            $table->nullableMorphs('reference', "{$prefix}txn_reference_idx"); // reference_type, reference_id for Property etc.
            
            $table->bigInteger('amount')->default(0); // stored in minor units (cents)
            $table->boolean('pending')->default(false);
            $table->boolean('reviewed')->default(false);
            $table->boolean('allow_reversal')->default(true);
            $table->date('posted_at')->nullable();
            $table->string('voucherable_type', 100)->nullable();
            $table->unsignedBigInteger('voucherable_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['voucherable_type', 'voucherable_id'], "{$prefix}txn_voucherable_idx");
            $table->index('branch_id', "{$prefix}txn_branch_idx");
            $table->index('account_id', "{$prefix}txn_account_idx");
            $table->index('type', "{$prefix}txn_type_idx");
            $table->index('posted_at', "{$prefix}txn_posted_idx");
            $table->index(['branch_id', 'posted_at'], "{$prefix}txn_branch_posted_idx");
        });

        // ──────────────────────────────────────────────────────────
        // Journal Entries (double-entry lines)
        // ──────────────────────────────────────────────────────────
        Schema::create("{$prefix}journal_entries", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained("{$prefix}transactions")
                ->cascadeOnDelete();
            $table->foreignId('account_id')
                ->constrained("{$prefix}accounts")
                ->restrictOnDelete();
            
            $table->unsignedBigInteger('cost_centre_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            
            $table->string('type', 10);       // JournalEntryType enum: 'debit' or 'credit'
            $table->bigInteger('amount');      // stored in minor units (cents)
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('transaction_id', "{$prefix}je_txn_idx");
            $table->index('account_id', "{$prefix}je_account_idx");
            $table->index('type', "{$prefix}je_type_idx");
            $table->index(['account_id', 'type'], "{$prefix}je_account_type_idx");
            $table->index('cost_centre_id', "{$prefix}je_cost_centre_idx");
            $table->index('project_id', "{$prefix}je_project_idx");
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();

        Schema::dropIfExists("{$prefix}journal_entries");
        Schema::dropIfExists("{$prefix}transactions");
        Schema::dropIfExists("{$prefix}accounts");
    }
};
