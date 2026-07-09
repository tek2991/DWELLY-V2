<?php

namespace App\Domain\Implementation\Services;

use App\Domain\Implementation\Models\ImplementationDeliverable;
use App\Domain\Implementation\Managers\DeliverableManager;
use Illuminate\Database\Eloquent\Model;

class DeliverableValidationService
{
    public function __construct(
        protected DeliverableManager $deliverableManager
    ) {}

    /**
     * Verify a single deliverable.
     *
     * @param ImplementationDeliverable $deliverable
     * @param Model $entity The project subject (e.g. Property)
     * @return bool
     */
    public function verifyDeliverable(ImplementationDeliverable $deliverable, Model $entity): bool
    {
        try {
            $provider = $this->deliverableManager->get($deliverable->provider_key);
            $result = $provider->validate($entity, $deliverable->validation_parameters);

            if ($result->isPassed) {
                $deliverable->status = 'verified';
            } else {
                $deliverable->status = 'rejected';
                // Future: Create a Finding with $result->reason
            }

            $deliverable->save();

            return $result->isPassed;
        } catch (\Exception $e) {
            $deliverable->status = 'rejected';
            $deliverable->save();
            return false;
        }
    }
}
