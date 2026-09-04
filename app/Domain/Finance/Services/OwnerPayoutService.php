<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Actions\ProcessOwnerPayoutAction;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
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
        $allUnpaidMaintenanceInvoices = Invoice::where('reference_type', \App\Domain\Maintenance\Models\MaintenanceRequest::class)
            ->whereIn('reference_id', $maintenanceRequestIds)
            ->whereIn('status', [
                \Tek2991\Accounting\Enums\InvoiceStatus::Draft,
                \Tek2991\Accounting\Enums\InvoiceStatus::Sent,
                \Tek2991\Accounting\Enums\InvoiceStatus::PartiallyPaid,
            ])
            ->get();

        $selectedIds = $options['selected_maintenance_invoice_ids'] ?? null;
        $unpaidMaintenanceInvoices = $selectedIds !== null 
            ? $allUnpaidMaintenanceInvoices->whereIn('id', $selectedIds)
            : $allUnpaidMaintenanceInvoices;

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
        $otherAdvance = isset($options['other_advance_offset']) ? (float) $options['other_advance_offset'] : 0.0;
        $totalAdvanceRequired = $maintenanceOffset + $generalAdvBalance + $otherAdvance;

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
            'property_url' => \App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $property->id]),
            'agreement_id' => $agreement?->id,
            'agreement_code' => $agreement?->code,
            'agreement_url' => $agreement ? \App\Filament\Resources\TenancyAgreements\TenancyAgreementResource::getUrl('edit', ['record' => $agreement->id]) : null,
            'owner_id' => $owner->id,
            'owner_name' => $owner->display_name,
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
        $summary = $this->bulkProcessOwnerPayoutsWithSummary($month, $year, [], $actor, [
            'bank_account_id' => $bankAccountId,
        ]);

        return $summary['count'];
    }

    /**
     * Bulk process owner payouts with comprehensive execution summary.
     */
    public function bulkProcessOwnerPayoutsWithSummary(
        int $month,
        int $year,
        array $selectedPropertyIds = [],
        ?User $actor = null,
        array $options = []
    ): array {
        $preview = $this->getBulkPayoutPreview($month, $year);
        $selectedIds = !empty($selectedPropertyIds) ? array_map('strval', $selectedPropertyIds) : null;

        $count = 0;
        $totalAmount = 0.0;
        $totalManagementFee = 0.0;
        $totalAdvanceOffset = 0.0;
        $generatedPayouts = [];

        foreach ($preview['items'] as $item) {
            if ($item['status'] === 'ready') {
                if ($selectedIds !== null && !in_array((string) $item['property_id'], $selectedIds, true)) {
                    continue;
                }

                $property = Property::find($item['property_id']);
                if ($property) {
                    $payout = $this->processPayoutAction->execute(
                        $property,
                        $item['billing_period_start'],
                        $item['billing_period_end'],
                        $actor,
                        [
                            'rent_collected' => $item['gross_rent'],
                            'management_fee_percent' => $item['management_fee_percent'],
                            'advance_offset' => $item['advance_offset'],
                            'reserve_deduction' => $item['reserve_deduction'],
                            'bank_account_id' => $options['bank_account_id'] ?? null,
                            'payout_date' => $options['payout_date'] ?? null,
                            'maintenance_invoice_ids' => $item['maintenance_invoice_ids'] ?? [],
                            'notes' => $options['notes'] ?? "Monthly Owner Payout for {$preview['month_name']} (Period: {$item['formatted_period']})",
                        ]
                    );

                    $count++;
                    $totalAmount += (float) $payout->amount;
                    $totalManagementFee += (float) $payout->management_fee;
                    $totalAdvanceOffset += (float) $payout->advance_offset;

                    $generatedPayouts[] = [
                        'payout_id' => $payout->id,
                        'property_id' => $property->id,
                        'property_name' => $property->building_name ?? 'Property',
                        'owner_name' => $property->owner?->display_name ?? 'Owner',
                        'net_amount' => (float) $payout->amount,
                        'management_fee' => (float) $payout->management_fee,
                        'commission_invoice_number' => $payout->commissionInvoice?->invoice_number,
                    ];
                }
            }
        }

        return [
            'count' => $count,
            'total_amount' => $totalAmount,
            'total_management_fee' => $totalManagementFee,
            'total_advance_offset' => $totalAdvanceOffset,
            'month' => $month,
            'year' => $year,
            'month_name' => $preview['month_name'],
            'generated_payouts' => $generatedPayouts,
        ];
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

    /**
     * Get pending maintenance invoices and options for a property.
     */
    public function getPendingMaintenanceOptions(Property $property): array
    {
        $maintenanceRequestIds = \App\Domain\Maintenance\Models\MaintenanceRequest::where('property_id', $property->id)->pluck('id');
        $unpaidMaintenanceInvoices = Invoice::where('reference_type', \App\Domain\Maintenance\Models\MaintenanceRequest::class)
            ->whereIn('reference_id', $maintenanceRequestIds)
            ->whereIn('status', [
                \Tek2991\Accounting\Enums\InvoiceStatus::Draft,
                \Tek2991\Accounting\Enums\InvoiceStatus::Sent,
                \Tek2991\Accounting\Enums\InvoiceStatus::PartiallyPaid,
            ])
            ->get();

        $options = [];
        foreach ($unpaidMaintenanceInvoices as $mInv) {
            $amt = (float) ($mInv->balance_due > 0 ? $mInv->balance_due : $mInv->grand_total);
            if ($amt > 0) {
                $req = \App\Domain\Maintenance\Models\MaintenanceRequest::find($mInv->reference_id);
                $ticketNumber = $req?->ticket_number ?? 'TKT-' . substr($mInv->id, -4);
                $title = $req?->title ?? 'Maintenance Work';
                $label = "🔧 {$ticketNumber}: {$title} (Inv #{$mInv->invoice_number}) — ₹" . number_format($amt, 2);
                $options[$mInv->id] = $label;
            }
        }

        return $options;
    }

    /**
     * Compile data structure for Owner Payout Statement & Remittance Advice PDF.
     */
    public function getPayoutStatementData(OwnerPayout $payout): array
    {
        $payout->loadMissing(['owner.bankAccounts', 'property', 'commissionInvoice', 'transaction']);

        $owner = $payout->owner;
        $property = $payout->property;
        $commissionInvoice = $payout->commissionInvoice;
        $transaction = $payout->transaction;

        $agreement = $property?->agreements()->where('status', 'active')->first();
        if (!$agreement && $payout->period_start) {
            $agreement = $property?->agreements()
                ->where('start_date', '<=', $payout->period_end)
                ->where(function ($q) use ($payout) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $payout->period_start);
                })
                ->first();
        }

        $tenantRole = $agreement?->roles()->where('is_primary', true)->first() ?? $agreement?->roles()->first();
        $tenant = $tenantRole?->party ?? $agreement?->tenantParty;

        $primaryBank = $owner?->bankAccounts()->where('is_primary', true)->first() ?? $owner?->bankAccounts()->first();

        // Linked maintenance deductions
        $maintenanceDeductions = [];
        if ($payout->advance_offset > 0 && $transaction) {
            $mInvoices = Invoice::where('reference_type', \App\Domain\Maintenance\Models\MaintenanceRequest::class)
                ->where('notes', 'like', "%{$transaction->reference}%")
                ->get();
            foreach ($mInvoices as $mInv) {
                $maintenanceDeductions[] = [
                    'invoice_number' => $mInv->invoice_number,
                    'description' => $mInv->notes ?: "Maintenance Work for {$property?->building_name}",
                    'amount' => (float) $mInv->grand_total,
                ];
            }
        }

        // Maintenance activity log for the property
        $maintenanceActivity = [];
        if ($property) {
            $mRequests = \App\Domain\Maintenance\Models\MaintenanceRequest::where('property_id', $property->id)
                ->where(function ($q) use ($payout) {
                    if ($payout->period_start && $payout->period_end) {
                        $q->whereBetween('created_at', [$payout->period_start->startOfDay(), $payout->period_end->endOfDay()])
                          ->orWhereBetween('resolved_at', [$payout->period_start->startOfDay(), $payout->period_end->endOfDay()]);
                    }
                })
                ->latest()
                ->take(10)
                ->get();

            foreach ($mRequests as $req) {
                $maintenanceActivity[] = [
                    'ticket_number' => $req->ticket_number,
                    'title' => $req->title,
                    'category' => $req->category?->name ?? 'General Repair',
                    'status' => is_object($req->status) && method_exists($req->status, 'getLabel') ? $req->status->getLabel() : (string) $req->status,
                    'created_at' => $req->created_at?->format('d M Y'),
                    'cost' => (float) (Invoice::where('reference_type', \App\Domain\Maintenance\Models\MaintenanceRequest::class)->where('reference_id', $req->id)->sum('grand_total') ?: ($req->estimated_cost ?? 0.0)),
                ];
            }
        }

        $totalCharges = (float) ($payout->management_fee + $payout->advance_offset + $payout->reserve_deduction);

        return [
            'payout' => $payout,
            'owner' => $owner,
            'property' => $property,
            'agreement' => $agreement,
            'tenant' => $tenant,
            'commission_invoice' => $commissionInvoice,
            'transaction' => $transaction,
            'primary_bank' => $primaryBank,
            'maintenance_deductions' => $maintenanceDeductions,
            'maintenance_activity' => $maintenanceActivity,
            'gross_rent' => (float) $payout->rent_collected,
            'management_fee' => (float) $payout->management_fee,
            'advance_offset' => (float) $payout->advance_offset,
            'reserve_deduction' => (float) $payout->reserve_deduction,
            'total_charges' => $totalCharges,
            'net_payout' => (float) $payout->amount,
            'formatted_period' => $payout->period_formatted,
        ];
    }

    /**
     * Compile comprehensive point-in-time document snapshot for the Owner Payout.
     */
    public function compilePayoutDocumentSnapshot(OwnerPayout $payout): array
    {
        $statementData = $this->getPayoutStatementData($payout);
        $owner = $statementData['owner'];
        $property = $statementData['property'];
        $primaryBank = $statementData['primary_bank'];
        $commissionInvoice = $statementData['commission_invoice'];
        $branch = $payout->branch ?? \App\Models\Branch::first();

        return [
            'document_type' => 'owner_payout_statement',
            'version' => '1.0',
            'snapshot_created_at' => now()->toIso8601String(),
            'payout_id' => $payout->id,
            'period_start' => $payout->period_start?->toDateString(),
            'period_end' => $payout->period_end?->toDateString(),
            'formatted_period' => $payout->period_formatted,
            'processed_at' => $payout->processed_at?->toIso8601String() ?? now()->toIso8601String(),
            'owner' => [
                'id' => $owner?->id,
                'display_name' => $owner?->display_name ?? 'Property Owner',
                'email' => $owner?->email,
                'phone' => $owner?->phone,
                'pan_number' => $owner?->individual?->pan_number,
            ],
            'property' => [
                'id' => $property?->id,
                'code' => $property?->code,
                'building_name' => $property?->building_name ?? $property?->name,
                'address' => $property?->address_line_1,
                'city' => $property?->city,
            ],
            'disbursement_bank' => [
                'bank_name' => $primaryBank?->bank_name ?? 'N/A',
                'account_number' => $primaryBank?->account_number ?? 'N/A',
                'account_number_masked' => $primaryBank ? ('••••' . substr($primaryBank->account_number ?? '0000', -4)) : 'N/A',
                'ifsc_code' => $primaryBank?->ifsc_code ?? 'N/A',
                'account_holder' => $primaryBank?->account_holder_name ?? $owner?->display_name,
            ],
            'company' => [
                'name' => 'Dwelly Living Private Limited',
                'branch_name' => $branch?->name ?? 'Headquarters',
                'support_email' => 'finance@dwelly.in',
                'support_phone' => '+91 98765 43210',
            ],
            'commission_invoice' => [
                'id' => $commissionInvoice?->id,
                'invoice_number' => $commissionInvoice?->invoice_number,
                'amount' => (float) ($commissionInvoice?->grand_total ?? $payout->management_fee),
                'status' => $commissionInvoice?->status->value ?? 'paid',
            ],
            'gross_rent' => (float) $payout->rent_collected,
            'management_fee' => (float) $payout->management_fee,
            'advance_offset' => (float) $payout->advance_offset,
            'reserve_deduction' => (float) $payout->reserve_deduction,
            'net_payout' => (float) $payout->amount,
            'maintenance_deductions' => $statementData['maintenance_deductions'],
            'transaction_reference' => $payout->transaction?->reference ?? ("PAYOUT-" . substr($payout->id, -8)),
            'notes' => $payout->notes,
        ];
    }

    /**
     * Generate, store immutable PDF file, and update OwnerPayout metadata with snapshot and SHA-256 checksum.
     */
    public function generateAndStorePayoutPdf(OwnerPayout $payout, bool $force = false): string
    {
        $disk = Storage::disk('local');

        if (!$force && !empty($payout->pdf_path) && $disk->exists($payout->pdf_path)) {
            return $disk->path($payout->pdf_path);
        }

        $snapshot = $payout->document_snapshot ?: $this->compilePayoutDocumentSnapshot($payout);
        $statementData = $this->getPayoutStatementData($payout);

        $pdf = Pdf::loadView('pdf.owner_payout_statement', [
            'payout' => $payout,
            'statementData' => $statementData,
            'snapshot' => $snapshot,
        ]);

        $pdfOutput = $pdf->output();
        $checksum = hash('sha256', $pdfOutput);

        $year = $payout->period_start ? $payout->period_start->format('Y') : date('Y');
        $month = $payout->period_start ? $payout->period_start->format('m') : date('m');
        $filename = "payout_{$payout->id}.pdf";
        $relativePath = "documents/owner_payouts/{$year}/{$month}/{$filename}";

        $disk->put($relativePath, $pdfOutput);

        $payout->updateQuietly([
            'document_snapshot' => $snapshot,
            'pdf_path' => $relativePath,
            'pdf_generated_at' => now(),
            'pdf_checksum' => $checksum,
        ]);

        return $disk->path($relativePath);
    }
}
