<?php

namespace App\Domain\Communication\Listeners;

use App\Domain\Agreement\Events\TenancyActivated;
use App\Domain\Communication\Services\CommunicationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeNotification implements ShouldQueue
{
    public function __construct(
        private CommunicationService $communication
    ) {}

    public function handle(TenancyActivated $event): void
    {
        $agreement = $event->agreement;
        
        // Find primary tenant
        $primaryRole = $agreement->roles()->where('is_primary', true)->first();
        if (!$primaryRole || !$primaryRole->party) {
            return;
        }

        $tenant = $primaryRole->party;

        // Dispatch Welcome Notification (Email or WhatsApp configured via templates)
        $this->communication->send('TenancyActivated', $tenant, [
            'name' => $tenant->display_name,
            'property' => $agreement->property->building_name,
            'start_date' => $agreement->start_date ? $agreement->start_date->format('M d, Y') : 'Immediately',
            'rent_amount' => $agreement->rent_amount,
        ]);
    }
}
