<?php

namespace App\Livewire;

use App\Domain\Communication\Models\PublicInquiry;
use Livewire\Component;

class ContactForm extends Component
{
    public $name = '';
    public $phone = '';
    public $email = '';
    public $message = '';

    public $success = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'phone' => 'required|string|max:20',
        'email' => 'nullable|email|max:255',
        'message' => 'nullable|string|max:1000',
    ];

    public function submit()
    {
        $this->validate();

        PublicInquiry::create([
            'type' => 'general',
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'message' => $this->message,
            'status' => 'new',
        ]);

        $this->reset(['name', 'phone', 'email', 'message']);
        $this->success = true;
    }

    public function render()
    {
        return view('livewire.contact-form');
    }
}
