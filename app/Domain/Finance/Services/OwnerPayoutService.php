<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Actions\ProcessOwnerPayoutAction;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Tek2991\Accounting\Models\Invoice;

class OwnerPayoutService
{
    public function __construct(
        protected RentBillingService $rentBillingService,
        protected AccountingProvisioningService $provisioningService,
        protected ProcessOwnerPayoutAction $processPayoutAction
    ) {}

    /**
     * Calculate owner payout details, billing period, proration, and deductions for a property and month/year.
     */
    public function calculatePayoutDetails(Property $property, int $month, int $year, array $options = []): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();

        // 1. Identify Owner
        $owner = $property->owner;
        if (! $owner && $property->id) {
            $mouPartyId = \App\Domain\Mou\Models\Mou::where('property_id', $property->id)
                ->whereNotNull('party_id')
                ->latest()
                ->value('party_id');
            if ($mouPartyId) {
                $owner = Party::find($mouPartyId);
            }
        }
        if (! $owner) {
            $owner = Party::whereHas('roles', fn ($q) => $q->where('name', 'owner'))
                ->orWhereHas('ownerProfile')
                ->first();
        }

        if (! $owner) {
            return [
                'eligible' => false,
                'reason' => 'No owner party linked to property.',
                'property_id' => $property->id,
                'property_name' => $property->building_name ?? $property->name ?? 'Property',
                'owner_id' => null,
                'owner_name' => 'Unknown Owner',
                'billing_period_start' => null,
                'billing_period_end' => null,
                'formatted_period' => 'N/A',
                'is_first_month' => false,
                'is_prorated' => false,
                'days_active' => 0,
                'total_days_in_month' => (int) $monthStart->daysInMonth,
                'gross_rent' => 0.0,
                'management_fee_percent' => 10.0,
                'management_fee' => 0.0,
                'advance_balance' => 0.0,
                'advance_offset' => 0.0,
                'reserve_deduction' => 0.0,
                'net_payout' => 0.0,
                'bank_details_formatted' => 'No Bank Details',
            ];
        }

        // 2. Identify Active Agreement
        $agreement = $property->agreements()->where('status', 'active')->first();
        if (! $agreement) {
            return [
                'eligible' => false,
                'reason' => 'No active tenancy agreement found.',
                'property_id' => $property->id,
                'property_name' => $property->building_name ?? $property->name ?? 'Property',
                'owner_id' => $owner->id,
                'owner_name' => $owner->display_name,
                'billing_period_start' => null,
                'billing_period_end' => null,
                'formatted_period' => 'No Active Tenancy',
                'is_first_month' => false,
                'is_prorated' => false,
                'days_active' => 0,
                'total_days_in_month' => (int) $monthStart->daysInMonth,
                'gross_rent' => 0.0,
                'management_fee_percent' => 10.0,
                'management_fee' => 0.0,
                'advance_balance' => 0.0,
                'advance_offset' => 0.0,
                'reserve_deduction' => 0.0,
                'net_payout' => 0.0,
                'bank_details_formatted' => $this->formatOwnerBankDetails($owner),
            ];
        }

        // 3. Compute Billing Period & Rent via RentBillingService
        $rentCalc = $this->rentBillingService->calculateBillingDetails($agreement, $month, $year);

        if (! $rentCalc['eligible']) {
            return [
                'eligible' => false,
                'reason' => $rentCalc['reason'],
                'property_id' => $property->id,
                'property_name' => $property->building_name ?? $property->name ?? 'Property',
                'owner_id' => $owner->id,
                'owner_name' => $owner->display_name,
                'billing_period_start' => null,
                'billing_period_end' => null,
                'formatted_period' => $rentCalc['formatted_period'],
                'is_first_month' => false,
                'is_prorated' => false,
                'days_active' => 0,
                'total_days_in_month' => (int) $monthStart->daysInMonth,
                'gross_rent' => 0.0,
                'management_fee_percent' => 10.0,
                'management_fee' => 0.0,
                'advance_balance' => 0.0,
                'advance_offset' => 0.0,
                'reserve_deduction' => 0.0,
                'net_payout' => 0.0,
                'bank_details_formatted' => $this->formatOwnerBankDetails($owner),
            ];
        }

        // 4. Check for generated Rent Invoice
        $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $existingInvoice = Invoice::where('reference_type', \App\Domain\Agreement\Models\TenancyAgreement::class)
            ->where('reference_id', $agreement->id)
            ->where(function ($q) use ($monthName, $rentCalc) {
                $q->where('notes', 'like', "%{$monthName}%")
                  ->orWhere(function ($sub) use ($rentCalc) {
                      if (! empty($rentCalc['billing_period_start'])) {
                          $sub->whereDate('billing_period_start', $rentCalc['billing_period_start']);
                      }
                  });
            })
            ->first();

        // 5. Determine Gross Rent
        $grossRent = isset($options['rent_collected'])
            ? (float) $options['rent_collected']
            : ($existingInvoice ? (float) $existingInvoice->grand_total : (float) $rentCalc['rent_amount']);

        // 6. Management Fee (Commission)
        $feePercent = isset($options['management_fee_percent'])
            ? (float) $options['management_fee_percent']
            : 10.0;
        $managementFee = round(($grossRent * $feePercent) / 100, 2);

        // 7. Maintenance Invoices & Advance Balance Offsets
        $maintenanceRequestIds = \App\Domain\Maintenance\Models\MaintenanceRequest::where('property_id', $property->id)->pluck('id');
        $unpaidMaintenanceInvoices = Invoice::where('reference_type', \App\Domain\Maintenance\Models\MaintenanceRequest::class)
            ->whereIn('reference_id', $maintenanceRequestIds)
            ->whereIn('status', [
                \Tek2991\Accounting\Enums\InvoiceStatus::Draft,
                \Tek2991\Accounting\Enums\InvoiceStatus::Sent,
                \Tek2991\Accounting\Enums\InvoiceStatus::PartiallyPaid,
            ])
            ->get();

        $maintenanceItems = [];
        $maintenanceOffset = 0.0;
        foreach ($unpaidMaintenanceInvoices as $mInv) {
            $amt = (float) ($mInv->balance_due > 0 ? $mInv->balance_due : $mInv->grand_total);
            if ($amt > 0) {
                $req = \App\Domain\Maintenance\Models\MaintenanceRequest::find($mInv->reference_id);
                $maintenanceItems[] = [
                    'id' => $mInv->id,
                    'invoice_number' => $mInv->invoice_number,
                    'ticket_number' => $req?->ticket_number ?? 'TKT-' . substr($mInv->id, -4),
                    'title' => $req?->title ?? 'Maintenance Work',
                    'amount' => $amt,
                ];
                $maintenanceOffset += $amt;
            }
        }

        $generalAdvBalance = $this->provisioningService->getOwnerAdvanceBalance($owner);
        $totalAdvanceRequired = $maintenanceOffset + $generalAdvBalance;

        $advanceOffset = isset($options['advance_offset'])
            ? (float) $options['advance_offset']
            : min($totalAdvanceRequired, max(0.0, $grossRent - $managementFee));

        $reserveDeduction = isset($options['reserve_deduction'])
            ? (float) $options['reserve_deduction']
            : 0.0;

        // 8. Net Payout
        $netPayout = max(0.0, round($grossRent - $managementFee - $advanceOffset - $reserveDeduction, 2));

        return [
            'eligible' => true,
            'reason' => null,
            'property_id' => $property->id,
            'property_name' => $property->building_name ?? $property->name ?? 'Property',
            'owner_id' => $owner->id,
            'owner_name' => $owner->display_name,
            'agreement_code' => $agreement->code,
            'handover_date_formatted' => $rentCalc['handover_date_formatted'],
            'billing_period_start' => $rentCalc['billing_period_start'],
            'billing_period_end' => $rentCalc['billing_period_end'],
            'formatted_period' => $rentCalc['formatted_period'],
            'is_first_month' => $rentCalc['is_first_month'],
            'is_prorated' => $rentCalc['is_prorated'],
            'days_active' => $rentCalc['days_active'],
            'total_days_in_month' => $rentCalc['total_days_in_month'],
            'gross_rent' => $grossRent,
            'management_fee_percent' => $feePercent,
            'management_fee' => $managementFee,
            'advance_balance' => $generalAdvBalance,
            'maintenance_offset' => $maintenanceOffset,
            'maintenance_invoices' => $maintenanceItems,
            'maintenance_invoice_ids' => array_column($maintenanceItems, 'id'),
            'total_advance_required' => $totalAdvanceRequired,
            'advance_offset' => $advanceOffset,
            'reserve_deduction' => $reserveDeduction,
            'net_payout' => $netPayout,
            'bank_details_formatted' => $this->formatOwnerBankDetails($owner),
        ];
    }

    /**
     * Get preview of all owner payouts for a given billing month and year.
     */
    public function getBulkPayoutPreview(int $month, int $year): array
    {
        $properties = Property::whereHas('agreements', fn ($q) => $q->where('status', 'active'))
            ->with(['agreements' => fn ($q) => $q->where('status', 'active'), 'owner.bankAccounts'])
            ->get();

        $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();

        $items = [];
        $readyCount = 0;
        $alreadyProcessedCount = 0;
        $ineligibleCount = 0;
        $totalGrossRent = 0.0;
        $totalManagementFee = 0.0;
        $totalAdvanceOffset = 0.0;
        $totalMaintenanceOffset = 0.0;
        $totalNetPayout = 0.0;

        foreach ($properties as $property) {
            $details = $this->calculatePayoutDetails($property, $month, $year);

            // Check if already processed
            $existingPayout = null;
            if ($details['eligible']) {
                $existingPayout = OwnerPayout::where('property_id', $property->id)
                    ->where(function ($q) use ($monthStart, $details) {
                        if (! empty($details['billing_period_start'])) {
                            $q->whereDate('period_start', $details['billing_period_start']);
                        } else {
                            $q->whereMonth('period_start', $monthStart->month)->whereYear('period_start', $monthStart->year);
                        }
                    })
                    ->first();
            }

            if (! $details['eligible']) {
                $status = 'ineligible';
                $statusLabel = 'Skipped: ' . $details['reason'];
                $badgeColor = 'gray';
                $ineligibleCount++;
            } elseif ($existingPayout) {
                $status = 'already_processed';
                $statusLabel = "Already Processed (₹" . number_format($existingPayout->amount, 2) . ")";
                $badgeColor = 'info';
                $alreadyProcessedCount++;
            } else {
                $status = 'ready';
                $statusLabel = 'Ready to Disburse';
                $badgeColor = 'success';
                $readyCount++;
                $totalGrossRent += $details['gross_rent'];
                $totalManagementFee += $details['management_fee'];
                $totalAdvanceOffset += $details['advance_offset'];
                $totalMaintenanceOffset += ($details['maintenance_offset'] ?? 0.0);
                $totalNetPayout += $details['net_payout'];
            }

            $items[] = array_merge($details, [
                'status' => $status,
                'status_label' => $statusLabel,
                'badge_color' => $badgeColor,
                'existing_payout_id' => $existingPayout?->id,
            ]);
        }

        return [
            'month' => $month,
            'year' => $year,
            'month_name' => $monthName,
            'summary' => [
                'total_properties' => $properties->count(),
                'ready_count' => $readyCount,
                'already_processed_count' => $alreadyProcessedCount,
                'ineligible_count' => $ineligibleCount,
                'total_gross_rent' => $totalGrossRent,
                'total_management_fee' => $totalManagementFee,
                'total_advance_offset' => $totalAdvanceOffset,
                'total_maintenance_offset' => $totalMaintenanceOffset,
                'total_net_payout' => $totalNetPayout,
            ],
            'items' => $items,
        ];
    }

    /**
     * Bulk process owner payouts for all eligible properties.
     */
    public function bulkProcessOwnerPayouts(int $month, int $year, ?User $actor = null, ?int $bankAccountId = null): int
    {
        $preview = $this->getBulkPayoutPreview($month, $year);
        $count = 0;

        foreach ($preview['items'] as $item) {
            if ($item['status'] === 'ready') {
                $property = Property::find($item['property_id']);
                if ($property) {
                    $this->processPayoutAction->execute(
                        $property,
                        $item['billing_period_start'],
                        $item['billing_period_end'],
                        $actor,
                        [
                            'rent_collected' => $item['gross_rent'],
                            'management_fee_percent' => $item['management_fee_percent'],
                            'advance_offset' => $item['advance_offset'],
                            'reserve_deduction' => $item['reserve_deduction'],
                            'bank_account_id' => $bankAccountId,
                            'maintenance_invoice_ids' => $item['maintenance_invoice_ids'] ?? [],
                            'notes' => "Monthly Owner Payout for {$preview['month_name']} (Period: {$item['formatted_period']})",
                        ]
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    protected function formatOwnerBankDetails(?Party $owner): string
    {
        if (! $owner) {
            return 'No Bank Linked';
        }

        $primaryBank = $owner->bankAccounts()->where('is_primary', true)->first();
        if (! $primaryBank) {
            $primaryBank = $owner->bankAccounts()->first();
        }

        if ($primaryBank) {
            return "{$primaryBank->bank_name} (A/C: ••••" . substr($primaryBank->account_number ?? '0000', -4) . ", IFSC: {$primaryBank->ifsc_code})";
        }

        return 'No Bank Linked';
    }
}
