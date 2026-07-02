<?php

namespace Tek2991\Accounting\Services;

use App\Models\Branch;

use Illuminate\Support\Facades\DB;

use Tek2991\Accounting\Models\DocumentSequence;

class DocumentNumberService
{
    public function nextInvoiceNumber(?Branch $branch = null): string
    {
        return $this->generateNextNumber($branch, 'invoice', 'INV');
    }

    public function nextBillNumber(?Branch $branch = null): string
    {
        return $this->generateNextNumber($branch, 'bill', 'BILL');
    }

    public function nextCreditNoteNumber(?Branch $branch = null): string
    {
        return $this->generateNextNumber($branch, 'credit_note', 'CN');
    }

    public function nextDebitNoteNumber(?Branch $branch = null): string
    {
        return $this->generateNextNumber($branch, 'debit_note', 'DN');
    }
    
    public function nextPaymentNumber(?Branch $branch = null): string
    {
        return $this->generateNextNumber($branch, 'payment', 'PAY');
    }

    protected function generateNextNumber(?Branch $branch, string $type, string $defaultPrefix): string
    {
        return DB::transaction(function () use ($branch, $type, $defaultPrefix) {
            $branchId = $branch ? $branch->id : null;
            $branchCode = $branch ? $branch->code : 'ORG';

            $sequence = DocumentSequence::where('branch_id', $branchId)
                ->where('document_type', $type)
                ->lockForUpdate()
                ->first();
            
            if (!$sequence) {
                // Determine prefix (e.g. GHY-INV-2026-)
                $year = date('Y'); // For simplicity, using calendar year. Can be extended to fiscal year.
                $prefix = "{$branchCode}-{$defaultPrefix}-{$year}-";
                
                $sequence = DocumentSequence::create([
                    'branch_id' => $branchId,
                    'document_type' => $type,
                    'prefix' => $prefix,
                    'next_number' => 1
                ]);
            }

            $prefix = $sequence->prefix;
            $nextNumber = $sequence->next_number;
            
            $sequence->next_number = $nextNumber + 1;
            $sequence->save();
            
            return $prefix . str_pad((string)$nextNumber, 6, '0', STR_PAD_LEFT);
        });
    }
}
