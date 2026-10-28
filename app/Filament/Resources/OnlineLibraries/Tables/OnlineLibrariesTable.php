<?php

namespace App\Filament\Resources\OnlineLibraries\Tables;

use App\Filament\Support\ContentTrashActions;
use App\Models\OnlineLibrary;
use App\Support\TranslatedContentOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class OnlineLibrariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Judul Dokumen')
                    ->sortable(query: TranslatedContentOrder::column('title'))
                    ->searchable(),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->searchable(),

                TextColumn::make('file_name')
                    ->label('Nama File')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('file_size')
                    ->label('Ukuran File')
                    ->formatStateUsing(function (?int $state): string {
                        if (! $state) {
                            return '—';
                        }
                        if ($state >= 1048576) {
                            return number_format($state / 1048576, 2).' MB';
                        }

                        return number_format($state / 1024, 1).' KB';
                    })
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label('Publish')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Identifier')
                    ->sortable(query: TranslatedContentOrder::column('slug'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ContentTrashActions::trashedFilter(),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(function ($livewire): array {
                        $locale = self::locale($livewire);

                        return OnlineLibrary::query()->select("category->{$locale} as label")
                            ->whereNotNull("category->{$locale}")->distinct()
                            ->pluck('label', 'label')->filter()->all();
                    })
                    ->query(function (Builder $query, array $data, $livewire): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        $locale = self::locale($livewire);

                        return $query->where("category->{$locale}", $data['value']);
                    }),
            ])
            ->recordActions([
                Action::make('preview_pdf')
                    ->label('Buka PDF')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('info')
                    ->url(function (OnlineLibrary $record): ?string {
                        if (! $record->file_path) {
                            return null;
                        }

                        return Storage::disk(config('filesystems.media_disk'))->url($record->file_path);
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (OnlineLibrary $record): bool => ! empty($record->file_path)),

                Action::make('download_pdf')
                    ->label('Unduh')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('success')
                    ->action(function (OnlineLibrary $record) {
                        if (! $record->file_path) {
                            Notification::make()
                                ->title('File tidak ditemukan')
                                ->danger()
                                ->send();

                            return null;
                        }

                        $disk = Storage::disk(config('filesystems.media_disk'));
                        $downloadName = $record->file_name ?: basename($record->file_path);

                        return $disk->download($record->file_path, $downloadName);
                    })
                    ->visible(fn (OnlineLibrary $record): bool => ! empty($record->file_path)),

                EditAction::make(),
                ContentTrashActions::restoreAction(),
                ContentTrashActions::forceDeleteAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ContentTrashActions::restoreBulkAction(),
                    ContentTrashActions::forceDeleteBulkAction(),
                ]),
            ]);
    }

    private static function locale($livewire): string
    {
        return method_exists($livewire, 'getActiveLocale')
            ? $livewire->getActiveLocale()
            : ($livewire->activeLocale ?? app()->getLocale());
    }
}
