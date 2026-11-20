<?php

namespace App\Livewire;

use App\Domain\Communication\Models\PublicInquiry;
use App\Domain\Property\Models\Property;
use Livewire\Attributes\On;
use Livewire\Component;

class BookPropertyModal extends Component
{
    public bool $isOpen = false;
    public ?string $property_id = null;
    public ?Property $property = null;

    public string $name = '';
    public string $phone = '';
    public string $email = '';
    public string $message = '';

    public bool $success = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'phone' => 'required|string|max:20',
        'email' => 'nullable|email|max:255',
        'message' => 'nullable|string|max:1000',
    ];

    #[On('open-book-modal')]
    public function openModal($property_id = null)
    {
        $this->property_id = $property_id;
        $this->property = $property_id ? Property::with(['localityRef', 'bhkType', 'propertyType'])->find($property_id) : null;
        $this->success = false;
        $this->isOpen = true;
    }

    public function closeModal()
    {
        $this->isOpen = false;
        $this->reset(['name', 'phone', 'email', 'message', 'property_id', 'property', 'success']);
    }

    public function submit()
    {
        $this->validate();

        PublicInquiry::create([
            'type' => 'booking',
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'message' => $this->message,
            'property_id' => $this->property_id,
            'payload' => [
                'property_code' => $this->property?->code,
                'building_name' => $this->property?->building_name,
            ],
            'status' => 'new',
        ]);

        $this->reset(['name', 'phone', 'email', 'message']);
        $this->success = true;
    }

    public function render()
    {
        return view('livewire.book-property-modal');
    }
}
