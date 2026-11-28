<?php

namespace App\Filament\Resources\News\Tables;

use App\Filament\Support\AccessibleTableColumns;
use App\Filament\Support\BilingualTableColumns;
use App\Filament\Support\ContentTrashActions;
use App\Models\News;
use App\Support\TranslatedContentOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                AccessibleTableColumns::thumbnail('image_path', 80, 50, 'title'),

                TextColumn::make('title')
                    ->label(__('Judul Berita'))
                    ->getStateUsing(fn (News $record): string => AccessibleTableColumns::indonesianPrimary($record, 'title'))
                    ->description(fn (News $record): ?string => AccessibleTableColumns::englishSubtitle($record, 'title'))
                    ->sortable(query: TranslatedContentOrder::column('title'))
                    ->searchable(),

                TextColumn::make('slug')
                    ->label(__('Identifier'))
                    ->sortable(query: TranslatedContentOrder::column('slug'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('category')
                    ->label(__('Kategori'))
                    ->badge()
                    ->searchable(),

                TextColumn::make('author')
                    ->label(__('Penulis'))
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label(__('Dibuat Pada'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                BilingualTableColumns::contentLanguageBadge(['title', 'slug', 'shorts', 'content', 'category']),

                IconColumn::make('is_published')
                    ->label(__('Publikasi'))
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label(__('Diperbarui'))
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([
                ContentTrashActions::trashedFilter(),
                TernaryFilter::make('is_published')
                    ->label(__('Status Publikasi'))
                    ->trueLabel(__('Aktif / Tayang'))
                    ->falseLabel(__('Draf / Nonaktif'))
                    ->placeholder(__('Semua')),
                SelectFilter::make('category')
                    ->label(__('Kategori'))
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
