<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Tek2991\Accounting\Enums\DiscountType;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'line_type',
        'item_id',
        'sort_order',
        'description',
        'hsn_sac_code',
        'quantity',
        'unit_price',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'line_total',
        'tax_id',
        'tax_snapshot',
        'tax_amount',
        'income_account_id',
        'gross_amount',
        'line_discount_amount',
        'allocated_document_discount',
        'net_amount',
    ];

    protected $casts = [
        'line_type' => \Tek2991\Accounting\Enums\DocumentLineType::class,
        'quantity' => 'decimal:4',
        'discount_rate' => 'decimal:4',
        'discount_type' => DiscountType::class,
        'tax_snapshot' => 'array',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'invoice_items';
    }

    // Amount Accessors/Mutators (Minor Units)
    protected function unitPrice(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function discountAmount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function lineTotal(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function taxAmount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function grossAmount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function lineDiscountAmount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function allocatedDocumentDiscount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }
    protected function netAmount(): Attribute { return Attribute::make(get: fn ($v) => $v !== null ? $v / 100 : 0, set: fn ($v) => (int) round($v * 100)); }

    // Relationships
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class, 'invoice_id'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class, 'item_id'); }
    public function tax(): BelongsTo { return $this->belongsTo(Tax::class, 'tax_id'); }
    public function incomeAccount(): BelongsTo { return $this->belongsTo(Account::class, 'income_account_id'); }
}
