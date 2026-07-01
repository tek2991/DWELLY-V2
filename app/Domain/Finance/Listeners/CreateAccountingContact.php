<?php

namespace App\Domain\Finance\Listeners;

use App\Domain\Party\Events\PartyCreated;
use App\Domain\Finance\Services\AccountingBridgeService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateAccountingContact implements ShouldQueue
{
    /**
     * The name of the connection the job should be sent to.
     */
    public $connection = 'redis';

    /**
     * The name of the queue the job should be sent to.
     */
    public $queue = 'critical';

    public function __construct(private AccountingBridgeService $accounting) {}

    public function handle(PartyCreated $event): void
    {
        $this->accounting->syncContact($event->party, $event->profileType);
    }
}
