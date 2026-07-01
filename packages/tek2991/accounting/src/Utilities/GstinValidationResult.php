<?php

namespace Tek2991\Accounting\Utilities;

class GstinValidationResult
{
    public function __construct(
        public readonly bool $isValidFormat,
        public readonly bool $isValidStateCode,
        public readonly array $errors = []
    ) {}
}
