<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class PropertyPricingVersion extends DomainModel
{
    protected $table = 'property_pricing_versions';

    protected $fillable = [
        'property_id',
        'effective_from',
        'effective_to',
        'rent',
        'security_deposit',
        'society_fee',
        'pricing_model',
        'fee_percentage',
        'booking_amount',
        'notes',
        'created_by',
    ];}