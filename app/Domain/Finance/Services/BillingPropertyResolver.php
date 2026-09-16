<?php

namespace App\Domain\Finance\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Properties\PropertyResource;
use Illuminate\Database\Eloquent\Model;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Invoice;

class BillingPropertyResolver
{
    /**
     * Resolves the Property model associated with an Invoice or Bill record.
     */
    public static function resolveProperty(Model $record): ?Property
    {
        $refType = $record->reference_type;
        $refId = $record->reference_id;

        if (! $refType || ! $refId) {
            return null;
        }

        try {
            if ($refType === Property::class || is_subclass_of($refType, Property::class)) {
                return Property::find($refId);
            }

            if ($refType === MaintenanceRequest::class || is_subclass_of($refType, MaintenanceRequest::class)) {
                $maintenance = MaintenanceRequest::find($refId);
                return $maintenance?->property;
            }

            if ($refType === TenancyAgreement::class || is_subclass_of($refType, TenancyAgreement::class)) {
                $agreement = TenancyAgreement::find($refId);
                return $agreement?->property;
            }

            if ($refType === TenantDeboarding::class || is_subclass_of($refType, TenantDeboarding::class)) {
                $deboarding = TenantDeboarding::find($refId);
                return $deboarding?->property;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Returns a human-readable property and unit descriptor.
     */
    public static function resolvePropertyLabel(Model $record): string
    {
        $property = self::resolveProperty($record);

        if (! $property) {
            return '—';
        }

        $parts = [];
        if ($property->building_name) {
            $parts[] = $property->building_name;
        } elseif ($property->address_line_1) {
            $parts[] = $property->address_line_1;
        } elseif ($property->code) {
            $parts[] = "Property {$property->code}";
        } else {
            $parts[] = "Property #{$property->id}";
        }

        if ($property->address_line_2) {
            $parts[] = $property->address_line_2;
        }

        return implode(' • ', $parts);
    }

    /**
     * Returns the Filament edit/financials URL for the resolved property.
     */
    public static function resolvePropertyUrl(Model $record): ?string
    {
        $property = self::resolveProperty($record);
        if (! $property) {
            return null;
        }

        try {
            return PropertyResource::getUrl('edit', ['record' => $property->id]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Categorizes an Invoice into a high-level PMS bucket.
     *
     * @return array{key: string, label: string, color: string}
     */
    public static function categorizeInvoice(Invoice $record): array
    {
        $snapshotCategory = data_get($record->document_snapshot, 'invoice_category');

        if ($snapshotCategory === 'documentation_charge' || str_contains(strtolower($record->notes ?? ''), 'documentation')) {
            return ['key' => 'fee', 'label' => 'Documentation Fee', 'color' => 'purple'];
        }

        if ($record->reference_type === TenancyAgreement::class || str_contains(strtolower($record->notes ?? ''), 'rent demand') || str_contains(strtolower($record->notes ?? ''), 'rent for')) {
            return ['key' => 'rent', 'label' => 'Rent Demand', 'color' => 'info'];
        }

        if ($record->reference_type === MaintenanceRequest::class || str_contains(strtolower($record->notes ?? ''), 'maintenance') || str_contains(strtolower($record->notes ?? ''), 'ticket')) {
            return ['key' => 'maintenance', 'label' => 'Maintenance Charge', 'color' => 'warning'];
        }

        if ($record->reference_type === TenantDeboarding::class || str_contains(strtolower($record->notes ?? ''), 'damage') || str_contains(strtolower($record->notes ?? ''), 'deboarding')) {
            return ['key' => 'damage', 'label' => 'Move-Out Deduction', 'color' => 'danger'];
        }

        return ['key' => 'general', 'label' => 'General Invoice', 'color' => 'gray'];
    }

    /**
     * Categorizes a Bill into a high-level PMS bucket.
     *
     * @return array{key: string, label: string, color: string}
     */
    public static function categorizeBill(Bill $record): array
    {
        $notes = strtolower($record->notes ?? '');

        if ($record->reference_type === MaintenanceRequest::class || str_contains($notes, 'maintenance') || str_contains($notes, 'ticket') || str_contains($notes, 'work order')) {
            return ['key' => 'maintenance', 'label' => 'Work Order / Repair', 'color' => 'warning'];
        }

        if (str_contains($notes, 'electric') || str_contains($notes, 'power') || str_contains($notes, 'water') || str_contains($notes, 'gas') || str_contains($notes, 'bescom') || str_contains($notes, 'bwssb') || str_contains($notes, 'internet')) {
            return ['key' => 'utility', 'label' => 'Property Utility', 'color' => 'info'];
        }

        if (str_contains($notes, 'society') || str_contains($notes, 'hoa') || str_contains($notes, 'maintenance dues') || str_contains($notes, 'aoa')) {
            return ['key' => 'society', 'label' => 'Society / HOA Dues', 'color' => 'purple'];
        }

        if (str_contains($notes, 'clean') || str_contains($notes, 'paint') || str_contains($notes, 'pest') || str_contains($notes, 'turnover')) {
            return ['key' => 'turnover', 'label' => 'Turnover Service', 'color' => 'success'];
        }

        return ['key' => 'vendor', 'label' => 'Vendor Payable', 'color' => 'gray'];
    }
}
