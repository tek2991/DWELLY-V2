<?php

namespace App\Domain\Audit\Enums;

enum ItemCondition: string
{
    case EXCELLENT = 'excellent';
    case GOOD = 'good';
    case FAIR = 'fair';
    case POOR = 'poor';
    case DAMAGED = 'damaged';
    case MISSING = 'missing';
    case NOT_APPLICABLE = 'not_applicable';

    public function getLabel(): string
    {
        return match ($this) {
            self::EXCELLENT => 'Excellent',
            self::GOOD => 'Good',
            self::FAIR => 'Fair',
            self::POOR => 'Poor',
            self::DAMAGED => 'Damaged',
            self::MISSING => 'Missing',
            self::NOT_APPLICABLE => 'Not Applicable',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EXCELLENT => 'success',
            self::GOOD => 'success',
            self::FAIR => 'warning',
            self::POOR => 'danger',
            self::DAMAGED => 'danger',
            self::MISSING => 'gray',
            self::NOT_APPLICABLE => 'gray',
        };
    }
}
