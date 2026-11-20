<?php

namespace App\Livewire;

use App\Domain\Communication\Models\PublicInquiry;
use App\Domain\Opportunity\Actions\GenerateOpportunityNumberAction;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Property\Models\PropertyType;
use Livewire\Attributes\On;
use Livewire\Component;

class RentOutModal extends Component
{
    public bool $isOpen = false;

    public string $name = '';
    public string $phone = '';
    public string $email = '';
    public int $num_of_properties = 1;

    public array $property_type = [];
    public array $bedroom = [];
    public array $bathroom = [];
    public array $availability = [];
    public array $building_name = [];
    public array $address = [];

    public bool $success = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'phone' => 'required|string|max:20',
        'email' => 'nullable|email|max:255',
        'property_type.*' => 'nullable|string',
        'bedroom.*' => 'nullable|integer',
        'bathroom.*' => 'nullable|integer',
        'availability.*' => 'nullable|boolean',
        'building_name.*' => 'required|string|max:255',
        'address.*' => 'nullable|string|max:500',
    ];

    #[On('open-rent-modal')]
    public function openModal()
    {
        $this->initializeDefaults();
        $this->success = false;
        $this->isOpen = true;
    }

    public function mount()
    {
        $this->initializeDefaults();
    }

    private function initializeDefaults()
    {
        $defaultType = null;
        try {
            $defaultType = PropertyType::where('is_active', true)->first()?->id;
        } catch (\Throwable $e) {}

        for ($i = 0; $i < $this->num_of_properties; $i++) {
            if (!isset($this->property_type[$i])) $this->property_type[$i] = $defaultType;
            if (!isset($this->bedroom[$i])) $this->bedroom[$i] = 2;
            if (!isset($this->bathroom[$i])) $this->bathroom[$i] = 2;
            if (!isset($this->availability[$i])) $this->availability[$i] = 1;
            if (!isset($this->building_name[$i])) $this->building_name[$i] = '';
            if (!isset($this->address[$i])) $this->address[$i] = '';
        }
    }

    public function updatedNumOfProperties($value)
    {
        $this->num_of_properties = max(1, min(4, (int) $value));
        $this->initializeDefaults();
    }

    public function closeModal()
    {
        $this->isOpen = false;
        $this->reset(['name', 'phone', 'email', 'building_name', 'address', 'success']);
    }

    public function submit()
    {
        $this->validate();

        $propertiesPayload = [];

        for ($i = 0; $i < $this->num_of_properties; $i++) {
            $propData = [
                'property_type_id' => $this->property_type[$i] ?? null,
                'bedroom' => $this->bedroom[$i] ?? 1,
                'bathroom' => $this->bathroom[$i] ?? 1,
                'availability' => $this->availability[$i] ?? 1,
                'building_name' => $this->building_name[$i] ?? '',
                'address' => $this->address[$i] ?? '',
            ];
            $propertiesPayload[] = $propData;

            // Create opportunity in V2 CRM pipeline
            try {
                $oppNumber = app(GenerateOpportunityNumberAction::class)->execute();
                Opportunity::create([
                    'number' => $oppNumber,
                    'title' => ($this->building_name[$i] ?: 'Rental Property') . ' - ' . $this->name,
                    'status' => OpportunityStatus::NEW->value,
                    'owner_name' => $this->name,
                    'owner_phone' => $this->phone,
                    'owner_email' => $this->email,
                    'address' => $this->address[$i] ?: null,
                    'estimated_property_type_id' => $this->property_type[$i] ?: null,
                    'estimated_bhk' => ($this->bedroom[$i] ?? 1) . ' BHK',
                    'internal_summary' => "Submitted via Website Rent-Out Modal. Bathrooms: " . ($this->bathroom[$i] ?? 1) . ", Vacancy: " . (!empty($this->availability[$i]) ? 'Vacant' : 'Occupied'),
                ]);
            } catch (\Throwable $e) {
                // Ignore opportunity creation failure, inquiry will still be saved
            }
        }

        PublicInquiry::create([
            'type' => 'rent_out',
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'message' => 'Rent Out Submission (' . $this->num_of_properties . ' properties)',
            'payload' => $propertiesPayload,
            'status' => 'new',
        ]);

        $this->success = true;
    }

    public function render()
    {
        $propertyTypes = collect([]);
        try {
            $propertyTypes = PropertyType::where('is_active', true)->get();
        } catch (\Throwable $e) {}

        return view('livewire.rent-out-modal', compact('propertyTypes'));
    }
}
