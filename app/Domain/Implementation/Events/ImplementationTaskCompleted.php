<?php

namespace App\Domain\Implementation\Events;

use App\Domain\Implementation\Models\ImplementationTask;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImplementationTaskCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ImplementationTask $task
    ) {}
}
