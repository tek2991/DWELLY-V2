<?php

namespace Tek2991\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Tax;

class DemoTaxesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Taxes (GST 9% & 18%)
        $outputCgstAccount = Account::firstOrCreate([
            'name' => 'Output CGST',
        ], [
            'type' => AccountType::Liability,
            'reporting_class' => ReportingClass::CurrentLiability,
            'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
        ]);

        $outputSgstAccount = Account::firstOrCreate([
            'name' => 'Output SGST',
        ], [
            'type' => AccountType::Liability,
            'reporting_class' => ReportingClass::CurrentLiability,
            'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
        ]);

        $outputIgstAccount = Account::firstOrCreate([
            'name' => 'Output IGST',
        ], [
            'type' => AccountType::Liability,
            'reporting_class' => ReportingClass::CurrentLiability,
            'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
        ]);

        $inputCgstAccount = Account::firstOrCreate([
            'name' => 'Input CGST',
        ], [
            'type' => AccountType::Asset,
            'reporting_class' => ReportingClass::CurrentAsset,
            'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
        ]);

        $inputSgstAccount = Account::firstOrCreate([
            'name' => 'Input SGST',
        ], [
            'type' => AccountType::Asset,
            'reporting_class' => ReportingClass::CurrentAsset,
            'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
        ]);

        $inputIgstAccount = Account::firstOrCreate([
            'name' => 'Input IGST',
        ], [
            'type' => AccountType::Asset,
            'reporting_class' => ReportingClass::CurrentAsset,
            'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
        ]);

        // GST 18%
        $gst18 = Tax::firstOrCreate([
            'name' => 'GST 18%',
        ], [
            'description' => '9% CGST + 9% SGST | 18% IGST',
            'is_active' => true,
        ]);

        if ($gst18->wasRecentlyCreated) {
            $gst18->components()->create([
                'name' => 'CGST',
                'rate' => 9.00,
                'type' => \Tek2991\Accounting\Enums\TaxComponentType::Intrastate->value,
                'sales_account_id' => $outputCgstAccount->id,
                'purchase_account_id' => $inputCgstAccount->id,
            ]);
            $gst18->components()->create([
                'name' => 'SGST',
                'rate' => 9.00,
                'type' => \Tek2991\Accounting\Enums\TaxComponentType::Intrastate->value,
                'sales_account_id' => $outputSgstAccount->id,
                'purchase_account_id' => $inputSgstAccount->id,
            ]);
            $gst18->components()->create([
                'name' => 'IGST',
                'rate' => 18.00,
                'type' => \Tek2991\Accounting\Enums\TaxComponentType::Interstate->value,
                'sales_account_id' => $outputIgstAccount->id,
                'purchase_account_id' => $inputIgstAccount->id,
            ]);
        }
    }
}
