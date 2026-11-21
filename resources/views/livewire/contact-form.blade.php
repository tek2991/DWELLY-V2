<div>
    @if ($success === false)
        <form wire:submit="submit">
            <h2 class="font-GraphikSemibold text-darker-3 text-3xl">Have queries?</h2>
            <p class="text-darker-2 text-lg">
                Drop your details and we will get back to you.
            </p>
            <div class="mt-4">
                <div class="my-4">
                    <label for="contact_name" class="font-GraphikMedium text-darker-3">Name*</label>
                    @error('name')
                        <span class="text-xs text-red-600 block">{{ $message }}</span>
                    @enderror
                    <input type="text" id="contact_name" wire:model="name"
                        class="w-full border-2 border-secondary rounded-sm p-2 outline-none focus:outline-none focus:ring-0 focus:border-piss-yellow transition duration-150">
                </div>
                <div class="my-4">
                    <label for="contact_phone" class="font-GraphikMedium text-darker-3">Phone*</label>
                    @error('phone')
                        <span class="text-xs text-red-600 block">{{ $message }}</span>
                    @enderror
                    <input type="tel" id="contact_phone" wire:model="phone"
                        class="w-full border-2 border-secondary rounded-sm p-2 outline-none focus:outline-none focus:ring-0 focus:border-piss-yellow transition duration-150">
                </div>
                <div class="my-4">
                    <label for="contact_email" class="font-GraphikMedium text-darker-3">Email</label>
                    @error('email')
                        <span class="text-xs text-red-600 block">{{ $message }}</span>
                    @enderror
                    <input type="email" id="contact_email" wire:model="email"
                        class="w-full border-2 border-secondary rounded-sm p-2 outline-none focus:outline-none focus:ring-0 focus:border-piss-yellow transition duration-150">
                </div>
                <div class="my-4">
                    <label for="contact_message" class="font-GraphikMedium text-darker-3">Message</label>
                    @error('message')
                        <span class="text-xs text-red-600 block">{{ $message }}</span>
                    @enderror
                    <textarea name="message" id="contact_message" cols="30" rows="5" wire:model="message"
                        class="w-full border-2 border-secondary rounded-sm p-2 outline-none focus:outline-none focus:ring-0 focus:border-piss-yellow transition duration-150"></textarea>
                </div>
                <div class="my-4">
                    <button type="submit"
                        class="w-full bg-piss-yellow text-darker-3 rounded-sm text-center p-4 text-lg hover:bg-darker-3 hover:text-piss-yellow transition-colors duration-200 ease-in-out font-GraphikMedium cursor-pointer shadow-xs">
                        SUBMIT
                    </button>
                </div>
            </div>
        </form>
    @else
        <div class="my-4 text-center">
            <img src="{{ url('resources/images/message_received.svg') }}" alt="Message Received" class="w-72 mx-auto">
            <p class="text-darker-2 text-lg text-center mt-4">
                We got your message! <br> Our team will get back to you shortly.
            </p>
        </div>
    @endif
</div>
