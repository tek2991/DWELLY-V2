<?php

namespace App\Domain\Implementation\Events;

use App\Domain\Implementation\Models\ImplementationProject;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImplementationProjectCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ImplementationProject $project
    ) {}
}
