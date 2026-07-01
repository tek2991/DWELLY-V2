<?php

namespace Tek2991\Accounting\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CompanyAccessor
{
    /**
     * Get the current company (tenant) ID.
     */
    public function getCurrentCompanyId(): ?int;

    /**
     * Get the current company (tenant) model instance.
     */
    public function getCurrentCompany(): ?Model;
}
