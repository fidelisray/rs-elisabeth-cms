<?php

namespace App\Filament\Resources\News\Tables;

use App\Filament\Support\ContentTrashActions;
use App\Models\News;
use App\Support\TranslatedContentOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Preview')
                    ->disk(config('filesystems.media_disk'))
                    ->width(80)
                    ->height(50),

                TextColumn::make('title')
                    ->label('Judul Berita')
                    ->sortable(query: TranslatedContentOrder::column('title'))
                    ->searchable(),

                TextColumn::make('slug')
                    ->label('Identifier')
                    ->sortable(query: TranslatedContentOrder::column('slug'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->searchable(),

                TextColumn::make('author')
                    ->label('Penulis')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label('Publish')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([
                ContentTrashActions::trashedFilter(),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(function ($livewire): array {
                        $locale = self::locale($livewire);

                        return News::query()->select("category->{$locale} as label")
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
        $locale = $livewire->activeLocale ?? 'id';

        return in_array($locale, config('app.supported_locales', ['id', 'en']), true) ? $locale : 'id';
    }
}
