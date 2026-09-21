<?php

namespace Tek2991\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\State;

class DemoContactsSeeder extends Seeder
{
    public function run(): void
    {
        $hasFaker = class_exists(\Faker\Factory::class);
        $faker = $hasFaker ? \Faker\Factory::create('en_IN') : null;
        $allStates = State::all();

        $companyNames = [
            'Apex Logistics Pvt Ltd',
            'BlueStar Facilities & Management',
            'Century Infotech Solutions',
            'Delta Engineering Works',
            'Everest Holdings Corp',
            'Frontline Security Services',
            'Greenfield Agro Products',
            'Horizon Real Estate Ventures',
            'Innovate Tech Labs',
            'Jupiter Capital Partners',
            'Krypton Facility Services',
            'Lumina Media & Communications',
            'Matrix Diagnostics & Healthcare',
            'Nexus Retail Solutions',
            'Optima Property Management',
            'Pioneer Electrical Contractors',
            'Quantum Dynamics India',
            'Radiant Industrial Supplies',
            'Sterling Commercial Enterprises',
            'Titanium Hardware & Paints',
            'Unified Telecom Network',
            'Vertex Architectural Consultants',
            'Wavefront Digital Systems',
            'Zenith Environmental Solutions',
            'Orbit Commercial Services',
        ];
        
        for ($i = 0; $i < count($companyNames); $i++) {
            $companyName = $companyNames[$i];
            $type = match ($i % 3) {
                0 => ContactType::Customer,
                1 => ContactType::Vendor,
                default => ContactType::Both,
            };
            $state = $allStates->isNotEmpty() ? $allStates[$i % $allStates->count()] : null;
            $isTaxRegistered = $i < 2; // 1 or 2 tax registered contacts
            
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $companyName));
            $pan = strtoupper(substr(md5($companyName), 0, 5)) . sprintf('%04d', ($i * 137 + 1000) % 9000 + 1000) . chr(65 + ($i % 26));

            $email = $faker ? $faker->companyEmail : "contact@{$slug}.in";
            $phone = $faker ? $faker->phoneNumber : '+91 98' . sprintf('%08d', ($i * 1234567 + 10000000) % 90000000 + 10000000);
            $address = "Plot No. " . ($i + 1) . ", Sector " . (($i % 15) + 1) . ", Industrial Area, " . ($state?->name ?? 'Assam');
            
            $contactData = [
                'type' => $type,
                'email' => $email,
                'phone' => $phone,
                'tax_id' => $pan,
                'state_id' => $state?->id,
                'billing_address' => $address,
                'shipping_address' => $address,
            ];
            
            if ($isTaxRegistered && $state) {
                $contactData['is_tax_registered'] = true;
                $contactData['gst_registration_type'] = \Tek2991\Accounting\Enums\GstRegistrationType::Regular;
                $gstStateCode = str_pad($state->gst_state_code ?? '27', 2, '0', STR_PAD_LEFT);
                $contactData['gstin'] = $gstStateCode . $contactData['tax_id'] . '1Z' . chr(65 + ($i % 26));
            }
            
            Contact::firstOrCreate([
                'name' => $companyName,
            ], $contactData);
        }
    }
}
