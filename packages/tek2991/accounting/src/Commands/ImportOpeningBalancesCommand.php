<?php

namespace Tek2991\Accounting\Commands;

use Illuminate\Console\Command;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Services\TransactionService;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\TransactionType;
use Exception;

class ImportOpeningBalancesCommand extends Command
{
    protected $signature = 'accounting:opening-balances 
                            {company_id : The ID of the company} 
                            {file : Path to CSV file (account_code,debit,credit)}
                            {--date=1970-01-01 : The date for the opening balances transaction}';

    protected $description = 'Import opening balances from a CSV file (account_code, debit, credit)';

    public function handle(TransactionService $txnService)
    {
        $companyId = $this->argument('company_id');
        $file = $this->argument('file');
        $date = $this->option('date');

        if (!file_exists($file) || !is_readable($file)) {
            $this->error("Cannot read file: {$file}");
            return 1;
        }

        $header = null;
        $data = [];
        if (($handle = fopen($file, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    $header = array_map('strtolower', array_map('trim', $row));
                } else {
                    $data[] = array_combine($header, array_map('trim', $row));
                }
            }
            fclose($handle);
        }

        if (empty($data)) {
            $this->error("CSV is empty or invalid.");
            return 1;
        }

        $accounts = Account::where('company_id', $companyId)->get()->keyBy('code');

        $entries = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($data as $idx => $row) {
            $rowNum = $idx + 2;
            $code = $row['account_code'] ?? null;
            $debit = (int) round(((float) ($row['debit'] ?? 0)) * 100);
            $credit = (int) round(((float) ($row['credit'] ?? 0)) * 100);

            if (!$code) continue;

            if (!isset($accounts[$code])) {
                $this->error("Row {$rowNum}: Account with code '{$code}' not found.");
                return 1;
            }

            $accountId = $accounts[$code]->id;

            if ($debit > 0) {
                $entries[] = [
                    'account_id' => $accountId,
                    'type' => JournalEntryType::Debit,
                    'amount' => $debit / 100, // passed as major units, JournalEntry handles minor conversion
                    'description' => 'Opening Balance',
                ];
                $totalDebit += $debit;
            }

            if ($credit > 0) {
                $entries[] = [
                    'account_id' => $accountId,
                    'type' => JournalEntryType::Credit,
                    'amount' => $credit / 100, // passed as major units
                    'description' => 'Opening Balance',
                ];
                $totalCredit += $credit;
            }
        }

        // Exact integer comparison for trial balance
        if ($totalDebit !== $totalCredit) {
            $this->error("Trial balance does not match. Debits: " . ($totalDebit / 100) . ", Credits: " . ($totalCredit / 100));
            return 1;
        }

        try {
            $transaction = $txnService->createTransaction([
                'company_id' => $companyId,
                'type' => TransactionType::Journal,
                'description' => 'Opening Balances',
                'posted_at' => $date,
                'reference' => 'OB-' . date('Ymd'),
                'reviewed' => true,
                'pending' => false,
            ], $entries);

            $this->info("Successfully imported opening balances! Transaction ID: {$transaction->id}");
            return 0;
        } catch (Exception $e) {
            $this->error("Failed to create transaction: " . $e->getMessage());
            return 1;
        }
    }
}
