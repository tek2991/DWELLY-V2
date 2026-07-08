<?php

namespace App\Domain\Mou\Actions;

use App\Domain\Mou\Models\Mou;
use App\Domain\Shared\Actions\GenerateSequenceNumberAction;

class GenerateMouNumberAction
{
    public function execute(): string
    {
        // Using a shared action or logic to generate something like MOU-YYYY-XXXXX
        // Since we are mocking the shared action for now, we'll generate it directly.
        $prefix = 'MOU';
        $year = now()->format('Y');
        
        $latestMou = Mou::withTrashed()->where('number', 'like', "{$prefix}-{$year}-%")->orderBy('id', 'desc')->first();
        
        $sequence = 1;
        if ($latestMou) {
            $parts = explode('-', $latestMou->number);
            $sequence = (int) end($parts) + 1;
        }
        
        return sprintf("%s-%s-%06d", $prefix, $year, $sequence);
    }
}
