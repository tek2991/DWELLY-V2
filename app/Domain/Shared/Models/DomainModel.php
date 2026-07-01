<?php

namespace App\Domain\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

abstract class DomainModel extends Model
{
    use HasUlids;

    protected $guarded = [];
}