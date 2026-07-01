<?php

namespace Tek2991\Accounting\Support;

use Tek2991\Accounting\Contracts\CompanyAccessor;

class DefaultCompanyAccessor implements CompanyAccessor
{
    public function getCurrentCompanyId(): ?int
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        // Filament 4 tenant resolution
        if (class_exists(\Filament\Facades\Filament::class)) {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant !== null) {
                return $tenant->getKey();
            }
        }

        // Fallback: check for current_company_id on user
        if (method_exists($user, 'currentCompany')) {
            return $user->currentCompany?->getKey();
        }

        return null;
    }

    public function getCurrentCompany(): ?\Illuminate\Database\Eloquent\Model
    {
        if (class_exists(\Filament\Facades\Filament::class)) {
            return \Filament\Facades\Filament::getTenant();
        }

        $user = auth()->user();

        if ($user !== null && method_exists($user, 'currentCompany')) {
            return $user->currentCompany;
        }

        return null;
    }
}
