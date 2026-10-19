<x-filament-panels::page.simple>
    <form wire:submit="authenticate" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-col gap-3">
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>

    <div class="text-center text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ filament()->getLoginUrl() }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
            Kembali ke halaman login
        </a>
    </div>
</x-filament-panels::page.simple>
