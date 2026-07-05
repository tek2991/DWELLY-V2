<?php

namespace Tek2991\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\State;

class DemoContactsSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('en_IN');
        $allStates = State::all();
        
        for ($i = 0; $i < 25; $i++) {
            $type = $faker->randomElement([ContactType::Customer, ContactType::Vendor, ContactType::Both]);
            $state = $allStates->isNotEmpty() ? $allStates->random() : null;
            $isTaxRegistered = $i < 2; // 1 or 2 tax registered contacts
            
            $contactData = [
                'type' => $type,
                'email' => $faker->companyEmail,
                'phone' => $faker->phoneNumber,
                'tax_id' => strtoupper($faker->bothify('?????####?')), // Fake PAN
                'state_id' => $state?->id,
                'billing_address' => $faker->address,
                'shipping_address' => $faker->address,
            ];
            
            if ($isTaxRegistered && $state) {
                $contactData['is_tax_registered'] = true;
                $contactData['gst_registration_type'] = \Tek2991\Accounting\Enums\GstRegistrationType::Regular;
                $gstStateCode = str_pad($state->gst_state_code ?? '27', 2, '0', STR_PAD_LEFT);
                $contactData['gstin'] = $gstStateCode . $contactData['tax_id'] . '1Z' . $faker->randomLetter;
            }
            
            Contact::firstOrCreate([
                'name' => $faker->company,
            ], $contactData);
        }
    }
}
