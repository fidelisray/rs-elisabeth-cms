<?php

namespace App\Filament\Resources\Feedbacks\Pages;

use App\Actions\FollowUpFeedback;
use App\Filament\Resources\Feedbacks\FeedbackResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewFeedback extends ViewRecord
{
    protected static string $resource = FeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('followUp')->label(__('Tindak lanjut'))
                ->authorize(fn (): bool => Gate::allows('update', $this->getRecord()))
                ->schema([
                    Select::make('status')
                        ->label(__('Status'))
                        ->options(fn (): array => FeedbackResource::statusOptions())
                        ->required(),
                    Textarea::make('internal_note')->label(__('Catatan internal'))->maxLength(5000),
                ])
                ->fillForm(fn (): array => $this->getRecord()->only(['status', 'internal_note']))
                ->action(function (array $data): void {
                    app(FollowUpFeedback::class)->handle($this->getRecord(), $data);
                    $this->getRecord()->refresh();
                    $this->refreshFormData(['status', 'internal_note']);
                    Notification::make()->title(__('Tindak lanjut disimpan'))->success()->send();
                }),
        ];
    }
}
