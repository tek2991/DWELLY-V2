<?php

namespace App\Domain\Party\Models;

use App\Domain\Shared\Models\DomainModel;

class PartyIndividual extends DomainModel
{
    protected $table = 'party_individuals';

    public $timestamps = false;
}