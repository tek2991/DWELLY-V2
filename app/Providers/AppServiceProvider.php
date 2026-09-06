<?php

namespace App\Providers;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Audit\Models\Audit;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Observers\PartyObserver;
use App\Domain\Property\Models\Property;
use App\Models\Company;
use App\Policies\AccountingAccountPolicy;
use App\Policies\AccountingBankAccountPolicy;
use App\Policies\AccountingTransactionPolicy;
use App\Policies\AuditPolicy;
use App\Policies\MaintenanceQuotationPolicy;
use App\Policies\MaintenanceRequestPolicy;
use App\Policies\MouPolicy;
use App\Policies\OpportunityPolicy;
use App\Policies\OwnerPayoutPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\TenancyAgreementPolicy;
use App\Policies\TenantDeboardingPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Tek2991\Accounting\Contracts\CompanyAccessor;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\BankAccount;
use Tek2991\Accounting\Models\Transaction;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CompanyAccessor::class, function () {
            return new class implements CompanyAccessor
            {
                public function getCurrentCompanyId(): ?int
                {
                    return Company::first()?->id;
                }

                public function getCurrentCompany(): ?Model
                {
                    return Company::first();
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Party::observe(PartyObserver::class);

        // Register Domain Model Policies
        Gate::policy(Opportunity::class, OpportunityPolicy::class);
        Gate::policy(Mou::class, MouPolicy::class);
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(TenancyAgreement::class, TenancyAgreementPolicy::class);
        Gate::policy(Audit::class, AuditPolicy::class);
        Gate::policy(MaintenanceRequest::class, MaintenanceRequestPolicy::class);
        Gate::policy(MaintenanceClientQuote::class, MaintenanceQuotationPolicy::class);
        Gate::policy(TenantDeboarding::class, TenantDeboardingPolicy::class);
        Gate::policy(OwnerPayout::class, OwnerPayoutPolicy::class);
        Gate::policy(Account::class, AccountingAccountPolicy::class);
        Gate::policy(BankAccount::class, AccountingBankAccountPolicy::class);
        Gate::policy(Transaction::class, AccountingTransactionPolicy::class);
        Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        Gate::policy(\App\Models\Role::class, \App\Policies\RolePolicy::class);
        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);
        Gate::policy(\App\Models\Branch::class, \App\Policies\BranchPolicy::class);
    }
}
