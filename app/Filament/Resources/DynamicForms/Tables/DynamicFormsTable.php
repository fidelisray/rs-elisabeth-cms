<?php

namespace App\Filament\Resources\DynamicForms\Tables;

use App\Enums\DynamicFormMode;
use App\Enums\DynamicFormStatus;
use App\Filament\Resources\DynamicForms\Actions\DynamicFormShareAction;
use App\Filament\Resources\DynamicForms\DynamicFormResource;
use App\Filament\Support\ContentTrashActions;
use App\Livewire\DynamicFormLivePreview;
use App\Models\DynamicForm;
use App\Services\DynamicForm\DynamicFormVersionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                    ->label(__('Judul Formulir'))
                    ->description(fn (DynamicForm $record): string => '/formulir/'.$record->slug)
                    ->wrap()
                    ->extraCellAttributes(['style' => 'max-width: 15rem; white-space: normal; overflow-wrap: anywhere;'])
                    ->searchable(['title', 'slug'])
                    ->sortable(),

                TextColumn::make('mode')
                    ->label(__('Mode'))
                    ->badge()
                    ->formatStateUsing(fn (DynamicFormMode|string $state): string => $state instanceof DynamicFormMode
                        ? $state->label()
                        : (DynamicFormMode::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (DynamicFormMode|string $state): string => ($state instanceof DynamicFormMode ? $state : DynamicFormMode::tryFrom((string) $state)) === DynamicFormMode::Feedback
                        ? 'warning'
                        : 'info')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('locale')
                    ->label(__('Bahasa'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (DynamicFormStatus|string $state): string => $state instanceof DynamicFormStatus
                        ? $state->label()
                        : (DynamicFormStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (DynamicFormStatus|string $state): string => match ($state instanceof DynamicFormStatus ? $state : DynamicFormStatus::tryFrom((string) $state)) {
                        DynamicFormStatus::Published => 'success',
                        DynamicFormStatus::Closed => 'danger',
                        default => 'gray',
                    })
                    ->description(function (DynamicForm $record): ?string {
                        if ($record->status === DynamicFormStatus::Draft) {
                            return null;
                        }

                        $draft = $record->latestDraftVersion();

                        return $draft ? __('Draf v:version belum terbit', ['version' => $draft->version_number]) : null;
                    }),

                TextColumn::make('is_public_listed')
                    ->label(__('Katalog Web'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => (bool) $state ? __('Katalog Publik') : __('Link / QR Saja'))
                    ->color(fn (mixed $state): string => (bool) $state ? 'success' : 'gray')
                    ->sortable(),

                TextColumn::make('publishedVersion.version_number')
                    ->label(__('Versi Aktif'))
                    ->formatStateUsing(fn ($state): string => $state ? 'v'.$state : __('Draf'))
                    ->badge()
                    ->color(fn ($state): string => $state ? 'primary' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('submissions_count')
                    ->counts('submissions')
                    ->label(__('Respons'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('Diperbarui'))
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('mode')
                    ->label(__('Mode'))
                    ->options(fn (): array => DynamicFormMode::options()),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(fn (): array => DynamicFormStatus::options()),
                TernaryFilter::make('is_public_listed')
                    ->label(__('Katalog Web'))
                    ->placeholder(__('Semua'))
                    ->trueLabel(__('Katalog Publik'))
                    ->falseLabel(__('Link / QR Saja')),
                SelectFilter::make('locale')
                    ->label(__('Bahasa'))
                    ->options(['id' => 'ID', 'en' => 'EN']),
                ContentTrashActions::trashedFilter(),
            ])
            ->recordActions([
                Action::make('analytics')
                    ->label(__('Analitik'))
                    ->tooltip(__('Ringkasan & Analitik'))
                    ->icon(Heroicon::OutlinedChartBar)
                    ->url(fn (DynamicForm $record): string => DynamicFormResource::getUrl('analytics', ['record' => $record]))
                    ->visible(fn (DynamicForm $record): bool => ! $record->trashed() && Gate::allows('view', $record)),

                DynamicFormShareAction::make(),

                ActionGroup::make([
                    Action::make('preview')
                        ->label(__('Pratinjau Interaktif'))
                        ->icon(Heroicon::OutlinedEye)
                        ->modalHeading(fn (DynamicForm $record): string => __('Pratinjau: :title', ['title' => $record->title]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel(__('Tutup Pratinjau'))
                        ->modalContent(fn (DynamicForm $record): HtmlString => new HtmlString(
                            Livewire::mount(DynamicFormLivePreview::class, ['formId' => $record->id])
                        ))
                        ->visible(fn (DynamicForm $record): bool => ! $record->trashed() && Gate::allows('view', $record)),

                    Action::make('publish')
                        ->label(__('Publikasikan Versi'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading(__('Publikasikan Versi Formulir'))
                        ->modalDescription(__('Skema versi aktif akan dibekukan dan langsung tersedia pada API Web Frontend.'))
                        ->authorize(fn (DynamicForm $record): bool => Gate::allows('update', $record))
                        ->visible(fn (DynamicForm $record): bool => ! $record->trashed()
                            && ($record->latestDraftVersion() !== null || $record->status === DynamicFormStatus::Draft))
                        ->action(function (DynamicForm $record): void {
                            try {
                                $version = app(DynamicFormVersionService::class)->publishDraftVersion(
                                    $record,
                                    consolidateIntoEmptyPublished: true,
                                );
                                Notification::make()
                                    ->title(__('Versi v:version berhasil dipublikasikan', ['version' => $version->version_number]))
                                    ->success()
                                    ->send();
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->title(__('Publikasi ditolak'))
                                    ->body(collect($e->errors())->flatten()->implode(' '))
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('close')
                        ->label(__('Tutup Form'))
                        ->icon(Heroicon::OutlinedLockClosed)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->authorize(fn (DynamicForm $record): bool => Gate::allows('update', $record))
                        ->visible(fn (DynamicForm $record): bool => ! $record->trashed() && $record->status === DynamicFormStatus::Published)
                        ->action(function (DynamicForm $record): void {
                            app(DynamicFormVersionService::class)->closeForm($record);
                            Notification::make()->title(__('Formulir ditutup sementara'))->success()->send();
                        }),

                    Action::make('reopen')
                        ->label(__('Buka Kembali'))
                        ->icon(Heroicon::OutlinedLockOpen)
                        ->color('success')
                        ->requiresConfirmation()
                        ->authorize(fn (DynamicForm $record): bool => Gate::allows('update', $record))
                        ->visible(fn (DynamicForm $record): bool => ! $record->trashed()
                            && $record->status === DynamicFormStatus::Closed
                            && $record->published_version_id !== null)
                        ->action(function (DynamicForm $record): void {
                            app(DynamicFormVersionService::class)->reopenForm($record);
                            Notification::make()->title(__('Formulir dibuka kembali'))->success()->send();
                        }),

                    Action::make('duplicate')
                        ->label(__('Duplikat Form'))
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->authorize(fn (): bool => Gate::allows('create', DynamicForm::class))
                        ->visible(fn (DynamicForm $record): bool => ! $record->trashed())
                        ->fillForm(fn (DynamicForm $record): array => [
                            'title' => $record->title.' ('.__('Salinan').')',
                            'locale' => $record->locale === 'id' ? 'en' : 'id',
                        ])
                        ->schema([
                            TextInput::make('title')
                                ->label(__('Judul Formulir Baru'))
                                ->required()
                                ->maxLength(255),
                            Select::make('locale')
                                ->label(__('Bahasa Formulir Baru'))
                                ->options(fn (): array => [
                                    'id' => __('Bahasa Indonesia (ID)'),
                                    'en' => __('Bahasa Inggris (EN)'),
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
                                ->title(__('Formulir [:title] berhasil diduplikasi sebagai draf', ['title' => $copy->title]))
                                ->success()
                                ->send();
                        }),

                    EditAction::make()
                        ->visible(fn (DynamicForm $record): bool => ! $record->trashed()),
                    ContentTrashActions::restoreAction(),
                    ContentTrashActions::forceDeleteAction(),
                ])
                    ->label(__('Aksi lainnya'))
                    ->tooltip(__('Aksi lainnya')),
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
