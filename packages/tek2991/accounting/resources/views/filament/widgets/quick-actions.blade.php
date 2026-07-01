<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <x-filament::button
                tag="a"
                href="{{ \Tek2991\Accounting\Filament\Resources\Sales\Invoices\InvoiceResource::getUrl('create') }}"
                icon="heroicon-o-document-plus"
                color="primary"
                style="width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem; height: auto;"
            >
                Create Invoice
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ \Tek2991\Accounting\Filament\Resources\Purchases\Bills\BillResource::getUrl('create') }}"
                icon="heroicon-o-document-text"
                color="danger"
                style="width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem; height: auto;"
            >
                Create Bill
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ \Tek2991\Accounting\Filament\Resources\Contacts\ContactResource::getUrl('create') }}"
                icon="heroicon-o-user-plus"
                color="gray"
                style="width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem; height: auto;"
            >
                Add Customer
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ \Tek2991\Accounting\Filament\Resources\Contacts\ContactResource::getUrl('create') }}"
                icon="heroicon-o-building-office"
                color="gray"
                style="width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem; height: auto;"
            >
                Add Vendor
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
