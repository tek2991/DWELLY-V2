<?php

namespace Tek2991\Accounting\Utilities;

use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Enums\AccountType;

class AccountCode
{
    /**
     * Suggest a code within the parent's account type range.
     */
    public static function generateForParent(Account $parent, ?int $companyId = null): string
    {
        $maxCode = Account::query()
            ->where('parent_id', $parent->id)
            ->when($companyId !== null, fn($query) => $query->where('company_id', $companyId))
            ->orderByRaw('CAST(code AS UNSIGNED) DESC')
            ->value('code');

        if ($maxCode === null) {
            return $parent->code . '10';
        }

        return (string) (((int) $maxCode) + 10);
    }

    /**
     * Suggest a code within the account type range.
     */
    public static function generateForType(AccountType $type, ?int $companyId = null): string
    {
        $start = $type->getCodeRangeStart();
        $end   = $type->getCodeRangeEnd();

        $query = Account::query()
            ->where('type', $type)
            ->whereBetween('code', [(string) $start, (string) $end]);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $maxCode = $query
            ->orderByRaw('CAST(code AS UNSIGNED) DESC')
            ->value('code');

        if ($maxCode === null) {
            return (string) $start;
        }

        $next = ((int) $maxCode) + 10;

        return (string) min($next, $end);
    }
}
