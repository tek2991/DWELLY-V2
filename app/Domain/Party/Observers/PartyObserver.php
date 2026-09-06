<?php

namespace App\Domain\Party\Observers;

use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Party\Models\Party;
use Illuminate\Support\Facades\Log;

class PartyObserver
{
    /**
     * Handle the Party "saved" event.
     */
    public function saved(Party $party): void
    {
        // Only synchronize if the core contact fields were changed
        if (! $party->wasChanged(['display_name', 'email', 'phone', 'state_id', 'accounting_contact_id'])) {
            return;
        }

        if ($party->accounting_contact_id || $party->accountingContact()->exists()) {
            try {
                $contact = $party->accountingContact;
                if ($contact) {
                    $contact->update([
                        'name' => $party->display_name,
                        'email' => $party->email,
                        'phone' => $party->phone,
                        'state_id' => $party->state_id,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("PartyObserver: Failed to synchronize accounting contact for party {$party->id}: {$e->getMessage()}");
            }
        }
    }
}
