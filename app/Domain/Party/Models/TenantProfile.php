<?php

namespace App\Domain\Party\Models;

use App\Domain\Shared\Models\DomainModel;

class TenantProfile extends DomainModel
{
    protected $table = 'tenant_profiles';

    public $timestamps = false;
}