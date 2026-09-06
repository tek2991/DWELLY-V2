<?php

namespace App\Domain\Auth\Enums;

enum RoleName: string
{
    case BUSINESS_OWNER = 'Business Owner';
    case CITY_MANAGER = 'City Manager';
    case SUPPLY_MANAGER = 'Supply Manager';
    case DEMAND_MANAGER = 'Demand Manager';
    case OPERATIONS_MANAGER = 'Operations Manager';
    case OPERATIONS_EXECUTIVE = 'Operations Executive';
    case ACCOUNTANT = 'Accountant';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isSystem(string|self $role): bool
    {
        $value = $role instanceof self ? $role->value : $role;

        return in_array($value, self::values(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::BUSINESS_OWNER => 'Business Owner',
            self::CITY_MANAGER => 'City Manager',
            self::SUPPLY_MANAGER => 'Supply Manager',
            self::DEMAND_MANAGER => 'Demand Manager',
            self::OPERATIONS_MANAGER => 'Operations Manager',
            self::OPERATIONS_EXECUTIVE => 'Operations Executive',
            self::ACCOUNTANT => 'Accountant',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BUSINESS_OWNER => 'Super administrator with unrestricted authority across all business verticals, finances, and system settings.',
            self::CITY_MANAGER => 'Regional leader overseeing supply, demand, local operations, branch performance, and field staff within assigned cities.',
            self::SUPPLY_MANAGER => 'Supply pipeline lead managing lead origination, owner MOUs, and property acquisition workflows.',
            self::DEMAND_MANAGER => 'Demand generation and leasing specialist managing tenant enquiries, site viewings, and rental offers.',
            self::OPERATIONS_MANAGER => 'Operational leader responsible for property onboarding review, inventory audits, maintenance supervision, and deboarding quality gates.',
            self::OPERATIONS_EXECUTIVE => 'Field execution associate performing physical inspections, key handovers, maintenance coordination, and move-in/out checklists.',
            self::ACCOUNTANT => 'Financial fiduciary managing rent demands, collections, owner payout batches, ledger accounting, and statutory compliance.',
        };
    }
}
