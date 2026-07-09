<?php

namespace App\Domain\Implementation\Contracts;

use Illuminate\Database\Eloquent\Model;

class ValidationResult
{
    public function __construct(
        public readonly bool $isPassed,
        public readonly ?string $reason = null
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    public static function fail(string $reason): self
    {
        return new self(false, $reason);
    }
}
