<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Models\NumberingSequence;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NumberingService
{
    /**
     * Entity configurations mapping to models and unique code columns.
     */
    protected static array $entityConfig = [
        'tenancy' => [
            'prefix' => 'TNC',
            'model' => \App\Domain\Agreement\Models\TenancyAgreement::class,
            'column' => 'code',
            'pad_length' => 5,
        ],
        'mou' => [
            'prefix' => 'MOU',
            'model' => \App\Domain\Mou\Models\Mou::class,
            'column' => 'number',
            'pad_length' => 5,
        ],
        'task' => [
            'prefix' => 'TSK',
            'model' => \App\Domain\Task\Models\Task::class,
            'column' => 'task_number',
            'pad_length' => 5,
        ],
        'audit' => [
            'prefix' => 'AUD',
            'model' => \App\Domain\Audit\Models\Audit::class,
            'column' => 'audit_number',
            'pad_length' => 5,
        ],
        'property' => [
            'prefix' => 'PRO',
            'model' => \App\Domain\Property\Models\Property::class,
            'column' => 'code',
            'pad_length' => 5,
        ],
        'opportunity' => [
            'prefix' => 'OPP',
            'model' => \App\Domain\Opportunity\Models\Opportunity::class,
            'column' => 'number',
            'pad_length' => 5,
        ],
    ];

    /**
     * Generate the next number for a specific entity type using pessimistic locking.
     */
    public static function generate(string $entityType): string
    {
        return DB::transaction(function () use ($entityType) {
            $config = static::$entityConfig[$entityType] ?? null;

            $sequence = NumberingSequence::where('entity_type', $entityType)->lockForUpdate()->first();

            $prefix = $config['prefix'] ?? match ($entityType) {
                'tenancy' => 'TNC',
                'mou' => 'MOU',
                'audit' => 'AUD',
                'task' => 'TSK',
                'opportunity' => 'OPP',
                default => strtoupper(substr($entityType, 0, 3)),
            };

            $padLength = $config['pad_length'] ?? 5;
            $currentYear = Carbon::now()->year;

            if (! $sequence) {
                $initialSequence = static::getInitialSequence($entityType, $currentYear, $prefix);

                $sequence = NumberingSequence::create([
                    'entity_type' => $entityType,
                    'prefix' => $prefix,
                    'pad_length' => $padLength,
                    'include_year' => true,
                    'year' => $currentYear,
                    'last_sequence' => $initialSequence,
                ]);
            }

            // Reset sequence if year has changed and sequence tracks year
            if ($sequence->include_year && $sequence->year !== $currentYear) {
                $sequence->last_sequence = static::getInitialSequence($entityType, $currentYear, $prefix);
                $sequence->year = $currentYear;
            }

            // Increment and verify candidate code does not collide with existing records
            do {
                $sequence->last_sequence += 1;
                $number = str_pad((string) $sequence->last_sequence, $sequence->pad_length, '0', STR_PAD_LEFT);
                $candidate = $sequence->include_year
                    ? "{$sequence->prefix}-{$currentYear}-{$number}"
                    : "{$sequence->prefix}-{$number}";
            } while (static::codeExists($entityType, $candidate));

            $sequence->save();

            return $candidate;
        });
    }

    /**
     * Determine highest existing numeric sequence from stored records for an entity and year.
     */
    public static function getInitialSequence(string $entityType, int $year, string $prefix): int
    {
        $config = static::$entityConfig[$entityType] ?? null;
        if (! $config) {
            return 0;
        }

        $modelClass = $config['model'];
        $column = $config['column'];

        if (! class_exists($modelClass)) {
            return 0;
        }

        $usesSoftDeletes = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass));
        $query = $usesSoftDeletes ? $modelClass::withTrashed() : $modelClass::query();

        $records = $query->where($column, 'like', "{$prefix}-{$year}-%")
            ->pluck($column);

        $max = 0;
        foreach ($records as $recordCode) {
            if (preg_match('/' . preg_quote($prefix, '/') . '-' . $year . '-(\d+)/', (string) $recordCode, $matches)) {
                $val = (int) $matches[1];
                if ($val > $max) {
                    $max = $val;
                }
            }
        }

        return $max;
    }

    /**
     * Check if a candidate code already exists in the corresponding model table.
     */
    public static function codeExists(string $entityType, string $code): bool
    {
        $config = static::$entityConfig[$entityType] ?? null;
        if (! $config) {
            return false;
        }

        $modelClass = $config['model'];
        $column = $config['column'];

        if (! class_exists($modelClass)) {
            return false;
        }

        $usesSoftDeletes = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass));

        $query = $usesSoftDeletes
            ? $modelClass::withTrashed()
            : $modelClass::query();

        if ($query->where($column, $code)->exists()) {
            return true;
        }

        // Also check if candidate with alternative padding exists (e.g., 5-digit vs 6-digit legacy codes)
        if (preg_match('/^([A-Z]+-\d{4}-)(\d+)$/', $code, $matches)) {
            $prefixWithYear = $matches[1];
            $numeric = (int) $matches[2];

            $pad5 = sprintf('%s%05d', $prefixWithYear, $numeric);
            $pad6 = sprintf('%s%06d', $prefixWithYear, $numeric);

            $altQuery = $usesSoftDeletes
                ? $modelClass::withTrashed()
                : $modelClass::query();

            return $altQuery
                ->whereIn($column, [$pad5, $pad6])
                ->exists();
        }

        return false;
    }
}
