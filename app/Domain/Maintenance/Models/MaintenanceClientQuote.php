<?php

namespace App\Domain\Maintenance\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MaintenanceClientQuote extends DomainModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'maintenance_client_quotes';

    protected $fillable = [
        'maintenance_request_id',
        'quote_number',
        'version',
        'total_amount',
        'subtotal_amount',
        'margin_percentage',
        'margin_amount',
        'tax_id',
        'gst_percentage',
        'tax_amount',
        'valid_until',
        'owner_amount',
        'tenant_amount',
        'dwelly_amount',
        'status',
        'generated_at',
        'awarded_vendor_quote_ids',
        'rejection_reason',
        'rejection_action',
        'approved_at',
        'approval_notes',
        'approved_by_type',
        'approval_channel',
    ];

    protected $casts = [
        'version' => 'integer',
        'tax_id' => 'integer',
        'awarded_vendor_quote_ids' => 'array',
        'total_amount' => 'decimal:2',
        'subtotal_amount' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'margin_amount' => 'decimal:2',
        'gst_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'owner_amount' => 'decimal:2',
        'tenant_amount' => 'decimal:2',
        'dwelly_amount' => 'decimal:2',
        'valid_until' => 'date',
        'approved_at' => 'datetime',
        'generated_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('generated_quote_pdf');
        $this->addMediaCollection('client_quote_files');
        $this->addMediaCollection('approval_proof_files');
        $this->addMediaCollection('vendor_work_photos');
        $this->addMediaCollection('vendor_invoice_files');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quote) {
            if (empty($quote->quote_number)) {
                $quote->quote_number = self::generateQuoteNumber();
            }

            if (blank($quote->margin_percentage)) {
                $quote->margin_percentage = (float) \App\Domain\Shared\Services\SettingService::get('financials.default_margin_percentage', 10.00);
            }

            if (blank($quote->gst_percentage)) {
                $quote->gst_percentage = (float) \App\Domain\Shared\Services\SettingService::get('financials.default_gst_percentage', 18.00);
            }

            if (blank($quote->valid_until)) {
                $quote->valid_until = now()->addDays((int) \App\Domain\Shared\Services\SettingService::get('financials.default_quotation_validity_days', 14));
            }
        });
    }

    public static function generateQuoteNumber(): string
    {
        $year = date('Y');
        $latest = static::where('quote_number', 'like', "QTE-{$year}-%")
            ->orderBy('quote_number', 'desc')
            ->first();

        if ($latest) {
            $lastNumber = (int) substr($latest->quote_number, -5);
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '00001';
        }

        return "QTE-{$year}-{$newNumber}";
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceClientQuoteItem::class, 'maintenance_client_quote_id')->orderBy('sort_order');
    }

    public function vendorQuotes(): HasMany
    {
        return $this->hasMany(MaintenanceVendorQuote::class, 'maintenance_request_id', 'maintenance_request_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(\Tek2991\Accounting\Models\Tax::class, 'tax_id');
    }

    /**
     * Get itemized breakdown of tax components (e.g. CGST, SGST, IGST)
     */
    public function getTaxComponentsBreakdown(): array
    {
        $taxAmount = (float) ($this->tax_amount ?? 0);
        $subtotal = (float) ($this->subtotal_amount ?: $this->items->sum('total_price'));
        $gstPct = (float) ($this->gst_percentage ?: 18.00);

        if ($this->tax && $this->tax->components && $this->tax->components->isNotEmpty()) {
            $components = [];
            $intrastate = $this->tax->components->filter(fn ($c) => $c->type === \Tek2991\Accounting\Enums\TaxComponentType::Intrastate || $c->type === 'intrastate');
            $componentsToUse = $intrastate->isNotEmpty() ? $intrastate : $this->tax->components;

            foreach ($componentsToUse as $comp) {
                $rate = (float) $comp->rate;
                $compAmount = round($subtotal * ($rate / 100), 2);
                $components[] = [
                    'name' => $comp->name,
                    'rate' => $rate,
                    'amount' => $compAmount,
                ];
            }
            return $components;
        }

        // Standard Indian GST breakdown (CGST + SGST)
        if ($gstPct > 0) {
            $halfPct = round($gstPct / 2, 2);
            $halfAmount = round($subtotal * ($halfPct / 100), 2);
            $secondHalf = round($taxAmount - $halfAmount, 2);
            return [
                ['name' => 'CGST', 'rate' => $halfPct, 'amount' => $halfAmount],
                ['name' => 'SGST', 'rate' => $halfPct, 'amount' => $secondHalf > 0 ? $secondHalf : $halfAmount],
            ];
        }

        return [];
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval']);
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function recalculateTotals(): self
    {
        $subtotal = 0.0;
        $vendorTotal = 0.0;

        foreach ($this->items as $item) {
            $qty = (float) ($item->quantity ?? 1);
            $clientRate = (float) ($item->unit_price ?? $item->unit_rate ?? 0);
            $subtotal += (float) ($item->total_price ?? round($qty * $clientRate, 2));

            $vCost = (float) ($item->vendor_cost ?? 0);
            $vendorTotal += round($qty * $vCost, 2);
        }

        // Fallback: If no client line items or vendor cost from items is 0, compute from vendor quotes
        if ($vendorTotal === 0.0 && $this->vendorQuotes()->exists()) {
            $awardedQuotes = $this->vendorQuotes()
                ->where(function ($q) {
                    $q->where('work_order_awarded', true)
                      ->orWhereNotNull('work_order_number')
                      ->orWhereNotNull('bill_id');
                })->get();

            if ($awardedQuotes->isEmpty()) {
                $awardedQuotes = $this->vendorQuotes()->get();
            }

            $vendorTotal = (float) $awardedQuotes->sum('quoted_cost');
        }

        $payer = $this->maintenanceRequest?->payer_type?->value ?? (string) $this->maintenanceRequest?->payer_type;
        $isDwelly = in_array($payer, ['dwelly', 'dwelly_absorbs', 'dwelly_absorbed']);

        if ($isDwelly) {
            $this->subtotal_amount = $vendorTotal;
            $this->margin_amount = 0.00;
            $this->tax_amount = 0.00;
            $this->total_amount = $vendorTotal;
            $this->dwelly_amount = $vendorTotal;
            $this->owner_amount = 0.00;
            $this->tenant_amount = 0.00;
            return $this;
        }

        $marginPct = (float) ($this->margin_percentage ?: \App\Domain\Shared\Services\SettingService::get('financials.default_margin_percentage', 10.00));
        $taxPct = (float) ($this->gst_percentage ?: \App\Domain\Shared\Services\SettingService::get('financials.default_gst_percentage', 18.00));

        $marginAmount = ($vendorTotal > 0 && $subtotal >= $vendorTotal)
            ? round($subtotal - $vendorTotal, 2)
            : round($subtotal * ($marginPct / 100), 2);

        $taxAmount = round($subtotal * ($taxPct / 100), 2);
        $total = round($subtotal + $taxAmount, 2);

        $this->subtotal_amount = $subtotal;
        $this->margin_amount = $marginAmount;
        $this->tax_amount = $taxAmount;
        $this->total_amount = $total;

        if ($payer === 'tenant' || $payer === 'dwelly_invoice_tenant') {
            $this->tenant_amount = $total;
            $this->owner_amount = 0.00;
            $this->dwelly_amount = 0.00;
        } elseif ($payer === 'owner' || $payer === 'dwelly_invoice_owner') {
            $this->owner_amount = $total;
            $this->tenant_amount = 0.00;
            $this->dwelly_amount = 0.00;
        } elseif ($payer === 'split' || $payer === 'dwelly_invoice_split') {
            $currOwner = (float) ($this->owner_amount ?? 0);
            $currTenant = (float) ($this->tenant_amount ?? 0);
            if ($currOwner + $currTenant > 0 && abs(($currOwner + $currTenant) - $total) < 0.01) {
                // Keep existing split amounts
            } else {
                $half = round($total / 2, 2);
                $this->owner_amount = $half;
                $this->tenant_amount = round($total - $half, 2);
            }
            $this->dwelly_amount = 0.00;
        } else {
            $this->owner_amount = $total;
            $this->tenant_amount = 0.00;
            $this->dwelly_amount = 0.00;
        }

        $this->save();
        return $this;
    }

    /**
     * Get IDs of contractor vendor quotes included in the client quotation line items
     */
    public function getIncludedVendorQuoteIds(): array
    {
        $ids = $this->items()
            ->whereNotNull('vendor_quote_id')
            ->pluck('vendor_quote_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->toArray();

        if (! empty($ids)) {
            return $ids;
        }

        // Fallback: match via defect items or if single vendor quote
        $defectItemIds = $this->items()
            ->whereNotNull('maintenance_request_item_id')
            ->pluck('maintenance_request_item_id')
            ->filter()
            ->unique()
            ->toArray();

        if (! empty($defectItemIds)) {
            $vendorQuotes = $this->vendorQuotes()->get();
            foreach ($vendorQuotes as $vq) {
                $vqDefectIds = (array) ($vq->maintenance_request_item_ids ?? []);
                if (! empty(array_intersect($defectItemIds, $vqDefectIds))) {
                    $ids[] = (string) $vq->id;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
