<?php

namespace Tek2991\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Models\Setting;

class DocumentNumberService
{
    public function nextInvoiceNumber(int $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $setting = Setting::where('company_id', $companyId)->lockForUpdate()->first();
            
            if (!$setting) {
                $setting = Setting::create(['company_id' => $companyId]);
            }

            $prefix = $setting->invoice_prefix ?? 'INV-';
            $nextNumber = $setting->invoice_next_number ?? 1;
            
            $setting->invoice_next_number = $nextNumber + 1;
            $setting->save();
            
            return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
        });
    }

    public function nextBillNumber(int $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $setting = Setting::where('company_id', $companyId)->lockForUpdate()->first();
            
            if (!$setting) {
                $setting = Setting::create(['company_id' => $companyId]);
            }

            $prefix = $setting->bill_prefix ?? 'BILL-';
            $nextNumber = $setting->bill_next_number ?? 1;

            $number = $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);

            $setting->bill_next_number = $nextNumber + 1;
            $setting->save();

            return $number;
        });
    }

    public function nextCreditNoteNumber(int $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $setting = Setting::where('company_id', $companyId)->lockForUpdate()->first();
            
            if (!$setting) {
                $setting = Setting::create(['company_id' => $companyId]);
            }

            $prefix = $setting->credit_note_prefix ?? 'CN-';
            $nextNumber = $setting->credit_note_next_number ?? 1;

            $number = $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);

            $setting->credit_note_next_number = $nextNumber + 1;
            $setting->save();

            return $number;
        });
    }

    public function nextDebitNoteNumber(int $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $setting = Setting::where('company_id', $companyId)->lockForUpdate()->first();
            
            if (!$setting) {
                $setting = Setting::create(['company_id' => $companyId]);
            }

            $prefix = $setting->debit_note_prefix ?? 'DN-';
            $nextNumber = $setting->debit_note_next_number ?? 1;

            $number = $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);

            $setting->debit_note_next_number = $nextNumber + 1;
            $setting->save();

            return $number;
        });
    }
}
