<?php

namespace App\Domain\Finance\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerPayout extends DomainModel
{
    protected $table = 'owner_payouts';

    protected $fillable = [
        'branch_id',
        'owner_id',
        'property_id',
        'transaction_id',
        'commission_invoice_id',
        'period_start',
        'period_end',
        'rent_collected',
        'management_fee',
        'advance_offset',
        'reserve_deduction',
        'amount',
        'status',
        'notes',
        'document_snapshot',
        'pdf_path',
        'pdf_generated_at',
        'pdf_checksum',
        'processed_at',
    ];

    protected $casts = [
        'rent_collected' => 'decimal:2',
        'management_fee' => 'decimal:2',
        'advance_offset' => 'decimal:2',
        'reserve_deduction' => 'decimal:2',
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'document_snapshot' => 'array',
        'pdf_generated_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'owner_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(\Tek2991\Accounting\Models\Transaction::class, 'transaction_id');
    }

    public function commissionInvoice(): BelongsTo
    {
        return $this->belongsTo(\Tek2991\Accounting\Models\Invoice::class, 'commission_invoice_id');
    }

    public function getPeriodFormattedAttribute(): ?string
    {
        if ($this->period_start && $this->period_end) {
            return $this->period_start->format('d M Y') . ' – ' . $this->period_end->format('d M Y');
        } elseif ($this->period_start) {
            return $this->period_start->format('d M Y');
        }
        return null;
    }

    public function hasStoredPdf(): bool
    {
        return !empty($this->pdf_path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($this->pdf_path);
    }

    public function getStoredPdfAbsolutePath(): ?string
    {
        if ($this->hasStoredPdf()) {
            return \Illuminate\Support\Facades\Storage::disk('local')->path($this->pdf_path);
        }
        return null;
    }
}
