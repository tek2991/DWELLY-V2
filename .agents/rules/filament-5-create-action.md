---
trigger: always_on
---

### Filament 5 Development Guidelines

1. **Unified Actions Namespace (CRITICAL)**
   Filament 5 has completely unified all actions into the global `Filament\Actions` namespace. 
   - **DO NOT USE**: `Filament\Tables\Actions\*` or `Filament\Forms\Actions\*`
   - **USE**: `Filament\Actions\Action`, `Filament\Actions\CreateAction`, `Filament\Actions\DeleteBulkAction`, etc., across all contexts (Tables, Forms, Pages, and RelationManagers).

2. **Unified Get/Set Utilities (CRITICAL)**
   The `Get` and `Set` closure utilities have been abstracted out of the Forms package into the core Schemas package.
   - **DO NOT USE**: `Filament\Forms\Get` or `Filament\Forms\Set`
   - **USE**: `Filament\Schemas\Components\Utilities\Get` and `Filament\Schemas\Components\Utilities\Set`

3. **Native Component CSS & Slots**
   To avoid Tailwind purging custom CSS classes in custom widget views, always prefer native Filament Blade components (`<x-filament::section>`, `<x-filament::grid>`).
   - For section header actions in Filament 5, use the exact slot name `<x-slot name="headerActions">` or place the buttons directly inside the `<x-slot name="heading">` using a flex container if explicit layout control is needed. Avoid older slot names like `headerEnd`.

4. **Spatie Media Library Plugin**
   The Spatie Media Library components are no longer bundled into the core `filament/forms` package.
   - To use `Filament\Forms\Components\SpatieMediaLibraryFileUpload`, ensure the `filament/spatie-laravel-media-library-plugin` package is installed via Composer.
