<?php

namespace App\Filament\Resources\DynamicForms\Tables;

use App\Enums\DynamicFormMode;
use App\Enums\DynamicFormStatus;
use App\Filament\Resources\DynamicForms\DynamicFormResource;
use App\Filament\Support\ContentTrashActions;
use App\Livewire\DynamicFormLivePreview;
use App\Models\DynamicForm;
use App\Services\DynamicForm\DynamicFormVersionService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

class DynamicFormsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Judul Formulir')
                    ->description(fn (DynamicForm $record): string => '/api/v1/cms/forms/'.$record->slug)
                    ->searchable(['title', 'slug'])
                    ->sortable(),

                TextColumn::make('mode')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn (DynamicFormMode|string $state): string => $state instanceof DynamicFormMode
                        ? $state->label()
                        : (DynamicFormMode::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (DynamicFormMode|string $state): string => ($state instanceof DynamicFormMode ? $state : DynamicFormMode::tryFrom((string) $state)) === DynamicFormMode::Feedback
                        ? 'warning'
                        : 'info'),

                TextColumn::make('locale')
                    ->label('Bahasa')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (DynamicFormStatus|string $state): string => $state instanceof DynamicFormStatus
                        ? $state->label()
                        : (DynamicFormStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (DynamicFormStatus|string $state): string => match ($state instanceof DynamicFormStatus ? $state : DynamicFormStatus::tryFrom((string) $state)) {
                        DynamicFormStatus::Published => 'success',
                        DynamicFormStatus::Closed => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('publishedVersion.version_number')
                    ->label('Versi Aktif')
                    ->formatStateUsing(fn ($state): string => $state ? 'v'.$state : 'Draf')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'primary' : 'gray'),

                TextColumn::make('submissions_count')
                    ->counts('submissions')
                    ->label('Respons')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('mode')
                    ->label('Mode')
                    ->options(DynamicFormMode::options()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(DynamicFormStatus::options()),
                SelectFilter::make('locale')
                    ->label('Bahasa')
                    ->options(['id' => 'ID', 'en' => 'EN']),
                ContentTrashActions::trashedFilter(),
            ])
            ->recordActions([
                Action::make('analytics')
                    ->label('Ringkasan & Analitik')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->url(fn (DynamicForm $record): string => DynamicFormResource::getUrl('analytics', ['record' => $record]))
                    ->visible(fn (DynamicForm $record): bool => ! $record->trashed() && Gate::allows('view', $record)),

                Action::make('preview')
                    ->label('Pratinjau Interaktif')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (DynamicForm $record): string => 'Pratinjau: '.$record->title)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup Pratinjau')
                    ->modalContent(fn (DynamicForm $record): HtmlString => new HtmlString(
                        Livewire::mount(DynamicFormLivePreview::class, ['formId' => $record->id])
                    ))
                    ->visible(fn (DynamicForm $record): bool => ! $record->trashed() && Gate::allows('view', $record)),

                Action::make('publish')
                    ->label('Publikasikan Versi')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Publikasikan Versi Formulir')
                    ->modalDescription('Skema versi aktif akan dibekukan dan langsung tersedia pada API Web Frontend.')
                    ->authorize(fn (DynamicForm $record): bool => Gate::allows('update', $record))
                    ->visible(fn (DynamicForm $record): bool => ! $record->trashed()
                        && ($record->latestDraftVersion() !== null || $record->status === DynamicFormStatus::Draft))
                    ->action(function (DynamicForm $record): void {
                        try {
                            $version = app(DynamicFormVersionService::class)->publishDraftVersion($record);
                            Notification::make()
                                ->title("Versi v{$version->version_number} berhasil dipublikasikan")
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Publikasi ditolak')
                                ->body(collect($e->errors())->flatten()->implode(' '))
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('close')
                    ->label('Tutup Form')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->authorize(fn (DynamicForm $record): bool => Gate::allows('update', $record))
                    ->visible(fn (DynamicForm $record): bool => ! $record->trashed() && $record->status === DynamicFormStatus::Published)
                    ->action(function (DynamicForm $record): void {
                        app(DynamicFormVersionService::class)->closeForm($record);
                        Notification::make()->title('Formulir ditutup sementara')->success()->send();
                    }),

                Action::make('reopen')
                    ->label('Buka Kembali')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (DynamicForm $record): bool => Gate::allows('update', $record))
                    ->visible(fn (DynamicForm $record): bool => ! $record->trashed()
                        && $record->status === DynamicFormStatus::Closed
                        && $record->published_version_id !== null)
                    ->action(function (DynamicForm $record): void {
                        app(DynamicFormVersionService::class)->reopenForm($record);
                        Notification::make()->title('Formulir dibuka kembali')->success()->send();
                    }),

                Action::make('duplicate')
                    ->label('Duplikat Form')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->authorize(fn (): bool => Gate::allows('create', DynamicForm::class))
                    ->visible(fn (DynamicForm $record): bool => ! $record->trashed())
                    ->fillForm(fn (DynamicForm $record): array => [
                        'title' => $record->title.' (Salinan)',
                        'locale' => $record->locale === 'id' ? 'en' : 'id',
                    ])
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul Formulir Baru')
                            ->required()
                            ->maxLength(255),
                        Select::make('locale')
                            ->label('Bahasa Formulir Baru')
                            ->options([
                                'id' => 'Bahasa Indonesia (ID)',
                                'en' => 'English (EN)',
                            ])
                            ->required(),
                    ])
                    ->action(function (DynamicForm $record, array $data): void {
                        $copy = app(DynamicFormVersionService::class)->duplicateForm(
                            $record,
                            (string) ($data['title'] ?? ''),
                            null,
                            (string) ($data['locale'] ?? $record->locale)
                        );
                        Notification::make()
                            ->title("Formulir [{$copy->title}] berhasil diduplikasi sebagai draf")
                            ->success()
                            ->send();
                    }),

                EditAction::make()
                    ->visible(fn (DynamicForm $record): bool => ! $record->trashed()),
                ContentTrashActions::restoreAction(),
                ContentTrashActions::forceDeleteAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ContentTrashActions::restoreBulkAction(),
                    ContentTrashActions::forceDeleteBulkAction(),
                ]),
            ])
            ->paginationPageOptions([10, 25, 50]);
    }
}
