<?php

namespace App\Domain\Party\Events;

use App\Domain\Party\Models\Party;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PartyCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Party $party,
        public string $profileType
    ) {}
}
