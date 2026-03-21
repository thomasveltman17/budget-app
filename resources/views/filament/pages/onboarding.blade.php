<x-filament-panels::page>
    <form wire:submit="complete">
        {{ $this->form }}
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
