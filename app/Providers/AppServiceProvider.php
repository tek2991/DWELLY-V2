<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\Tek2991\Accounting\Contracts\CompanyAccessor::class, function () {
            return new class implements \Tek2991\Accounting\Contracts\CompanyAccessor {
                public function getCurrentCompanyId(): ?int {
                    return \App\Models\Company::first()?->id;
                }
                public function getCurrentCompany(): ?\Illuminate\Database\Eloquent\Model {
                    return \App\Models\Company::first();
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
