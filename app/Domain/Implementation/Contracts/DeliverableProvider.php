<?php

namespace App\Domain\Implementation\Contracts;

use Illuminate\Database\Eloquent\Model;

interface DeliverableProvider
{
    /**
     * Validate the deliverable requirements against the entity.
     *
     * @param Model $entity The project subject (e.g. Property)
     * @param array|null $parameters Rules from workflow_deliverables
     * @return ValidationResult
     */
    public function validate(Model $entity, ?array $parameters): ValidationResult;
}
