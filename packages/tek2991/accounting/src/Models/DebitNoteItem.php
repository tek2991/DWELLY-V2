<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DebitNoteItem extends Model
{
    protected $fillable = [
        'debit_note_id',
        'item_id',
        'sort_order',
        'description',
        'hsn_sac_code',
        'quantity',
        'unit_price',
        'line_total',
        'tax_id',
        'tax_snapshot',
        'tax_amount',
        'expense_account_id',
        'gross_amount',
        'line_discount_amount',
        'allocated_document_discount',
        'net_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'tax_snapshot' => 'array',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'debit_note_items';
    }

    // Amount Accessors/Mutators (Minor Units)
    protected function unitPrice(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function lineTotal(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function taxAmount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function grossAmount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function lineDiscountAmount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function allocatedDocumentDiscount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function netAmount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }

    // Relationships
    public function debitNote(): BelongsTo { return $this->belongsTo(DebitNote::class, 'debit_note_id'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class, 'item_id'); }
    public function tax(): BelongsTo { return $this->belongsTo(Tax::class, 'tax_id'); }
}
