<?php

namespace App\Domain\Party\Models;

use App\Domain\Shared\Models\DomainModel;

class VendorProfile extends DomainModel
{
    protected $table = 'vendor_profiles';

    public $timestamps = false;
}