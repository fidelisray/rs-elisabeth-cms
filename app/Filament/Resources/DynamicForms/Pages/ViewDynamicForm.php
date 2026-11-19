<?php

namespace App\Filament\Resources\DynamicForms\Pages;

use App\Filament\Resources\DynamicForms\Actions\DynamicFormShareAction;
use App\Filament\Resources\DynamicForms\DynamicFormResource;
use App\Livewire\DynamicFormLivePreview;
use App\Models\DynamicForm;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;

class ViewDynamicForm extends ViewRecord
{
    protected static string $resource = DynamicFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DynamicFormShareAction::make()
                ->record(fn (): DynamicForm => $this->getRecord()),

            Action::make('analytics')
                ->label('Ringkasan & Analitik')
                ->icon(Heroicon::OutlinedChartBar)
                ->url(fn (): string => DynamicFormResource::getUrl('analytics', ['record' => $this->getRecord()])),

            Action::make('preview')
                ->label('Pratinjau Interaktif')
                ->icon(Heroicon::OutlinedEye)
                ->visible(fn (): bool => ! $this->getRecord()->trashed())
                ->modalHeading(fn (): string => 'Pratinjau: '.$this->getRecord()->title)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup Pratinjau')
                ->modalContent(fn (): HtmlString => new HtmlString(
                    Livewire::mount(DynamicFormLivePreview::class, ['formId' => $this->getRecord()->id])
                )),

            EditAction::make()
                ->visible(fn (): bool => ! $this->getRecord()->trashed()),
        ];
    }
}
