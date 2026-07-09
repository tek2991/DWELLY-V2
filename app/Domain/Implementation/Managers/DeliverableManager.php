<?php

namespace App\Domain\Implementation\Managers;

use App\Domain\Implementation\Contracts\DeliverableProvider;
use Exception;

class DeliverableManager
{
    /**
     * @var array<string, DeliverableProvider>
     */
    protected array $providers = [];

    /**
     * Register a deliverable provider.
     *
     * @param string $key
     * @param DeliverableProvider $provider
     */
    public function register(string $key, DeliverableProvider $provider): void
    {
        $this->providers[$key] = $provider;
    }

    /**
     * Get a registered provider by key.
     *
     * @param string $key
     * @return DeliverableProvider
     * @throws Exception
     */
    public function get(string $key): DeliverableProvider
    {
        if (!isset($this->providers[$key])) {
            throw new Exception("Deliverable provider [{$key}] not registered.");
        }

        return $this->providers[$key];
    }
}
