<?php

namespace Tek2991\Accounting\Services;

use Tek2991\Accounting\Models\FiscalPeriod;
use Tek2991\Accounting\Models\FiscalPeriodEvent;
use Tek2991\Accounting\Events\FiscalPeriodClosed;
use Tek2991\Accounting\Events\FiscalPeriodReopened;
use Tek2991\Accounting\Enums\FiscalPeriodStatus;
use Tek2991\Accounting\Support\ClosingValidationResult;
use Tek2991\Accounting\Facades\Accounting;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Exceptions\FiscalPeriodLockedException;

class PeriodLockService
{
    public function assertNotClosed(string $date): void
    {
        $closedPeriod = FiscalPeriod::query()
            ->where('status', '!=', FiscalPeriodStatus::Open)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if ($closedPeriod) {
            throw new FiscalPeriodLockedException("The posting date falls within a closed fiscal period: {$closedPeriod->name}.");
        }
    }
    public function previewClose(FiscalPeriod $period): array
    {
        $result = new ClosingValidationResult();
        
        $checks = Accounting::getClosingChecks();
        foreach ($checks as $checkClass) {
            $check = app($checkClass);
            $check->validate($period, $result);
        }

        $projectedProfitLoss = $this->calculateProfitLoss($period);

        return [
            'result' => $result,
            'projected_profit_loss' => $projectedProfitLoss,
        ];
    }

    public function closePeriod(FiscalPeriod $period, \Illuminate\Contracts\Auth\Authenticatable $user): void
    {
        $preview = $this->previewClose($period);
        
        if ($preview['result']->fails()) {
            throw new \Exception("Cannot close period. Validation failed: " . implode(', ', $preview['result']->getErrors()));
        }

        $profitLoss = $preview['projected_profit_loss'];

        $period->status = FiscalPeriodStatus::SoftClosed;
        $period->closing_profit_loss = $profitLoss;
        $period->closed_at = now();
        $period->closed_by = $user->id;
        $period->save();

        $checksRun = Accounting::getClosingChecks();

        FiscalPeriodEvent::create([
            'fiscal_period_id' => $period->id,
            'event_type' => 'closed',
            'performed_by' => $user->id,
            'performed_at' => now(),
            'metadata' => [
                'closing_profit_loss' => $profitLoss,
                'validation_summary' => [
                    'errors' => $preview['result']->getErrors(),
                    'passed' => true,
                    'checks_run' => $checksRun,
                ],
            ],
        ]);

        FiscalPeriodClosed::dispatch($period);
    }

    public function reopenPeriod(FiscalPeriod $period, \Illuminate\Contracts\Auth\Authenticatable $user): void
    {
        $period->status = FiscalPeriodStatus::Open;
        $period->save();

        FiscalPeriodEvent::create([
            'fiscal_period_id' => $period->id,
            'event_type' => 'reopened',
            'performed_by' => $user->id,
            'performed_at' => now(),
            'metadata' => [],
        ]);

        FiscalPeriodReopened::dispatch($period);
    }

    protected function calculateProfitLoss(FiscalPeriod $period): int
    {
        $accountService = app(AccountService::class);
        $revenue = $accountService->getTypeTotal(AccountType::Revenue, $period->start_date->format('Y-m-d'), $period->end_date->format('Y-m-d'));
        $expense = $accountService->getTypeTotal(AccountType::Expense, $period->start_date->format('Y-m-d'), $period->end_date->format('Y-m-d'));
        
        return $revenue->getAmount() - $expense->getAmount();
    }
}
