<?php

namespace Tek2991\Accounting\Enums;

enum FiscalPeriodStatus: string
{
    case Open = 'open';
    case SoftClosed = 'soft_closed';

    public function getLabel(): string
    {
        return match($this) {
            self::Open => 'Open',
            self::SoftClosed => 'Soft Closed',
        };
    }
}
