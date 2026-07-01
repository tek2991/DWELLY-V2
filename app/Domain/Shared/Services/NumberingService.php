<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Models\NumberingSequence;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NumberingService
{
    /**
     * Generate the next number for a specific entity type using pessimistic locking.
     */
    public static function generate(string $entityType): string
    {
        return DB::transaction(function () use ($entityType) {
            $sequence = NumberingSequence::where('entity_type', $entityType)->lockForUpdate()->first();
            
            if (!$sequence) {
                throw new \Exception("Numbering sequence not configured for entity: {$entityType}");
            }

            $currentYear = Carbon::now()->year;

            // Reset sequence if year has changed and sequence tracks year
            if ($sequence->include_year && $sequence->year !== $currentYear) {
                $sequence->last_sequence = 0;
                $sequence->year = $currentYear;
            }

            $sequence->last_sequence += 1;
            $sequence->save();

            $number = str_pad((string)$sequence->last_sequence, $sequence->pad_length, '0', STR_PAD_LEFT);

            if ($sequence->include_year) {
                return "{$sequence->prefix}-{$currentYear}-{$number}";
            }

            return "{$sequence->prefix}-{$number}";
        });
    }
}
