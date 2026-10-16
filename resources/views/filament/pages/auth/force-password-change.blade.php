<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center gap-3">
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>

    <form id="force-logout-form" method="POST" action="{{ route('filament.admin.auth.logout') }}" class="hidden">
        @csrf
    </form>
</x-filament-panels::page>