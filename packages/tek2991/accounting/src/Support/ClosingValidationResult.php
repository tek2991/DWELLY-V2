<?php

namespace Tek2991\Accounting\Support;

class ClosingValidationResult
{
    protected array $errors = [];

    public function addError(string $message): self
    {
        $this->errors[] = $message;
        return $this;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
