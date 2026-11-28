<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Filament\Support\AccessibleTableColumns;
use App\Filament\Support\BilingualTableColumns;
use App\Filament\Support\ContentTrashActions;
use App\Support\TranslatedContentOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                AccessibleTableColumns::thumbnail('image_path', 80, 50, 'title'),
                TextColumn::make('title')
                    ->label(__('Judul'))
                    ->getStateUsing(fn ($record): string => AccessibleTableColumns::indonesianPrimary($record, 'title'))
                    ->description(fn ($record): ?string => AccessibleTableColumns::englishSubtitle($record, 'title'))
                    ->sortable(query: TranslatedContentOrder::column('title'))
                    ->searchable(),
                TextColumn::make('slug')
                    ->label(__('Identifier'))
                    ->sortable(query: TranslatedContentOrder::column('slug'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tags')
                    ->label(__('Kategori'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('author')
                    ->label(__('Penulis'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('Dibuat Pada'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('views')
                    ->label(__('Dilihat'))
                    ->numeric()
                    ->sortable(),
                BilingualTableColumns::contentLanguageBadge(['title', 'slug', 'shorts', 'content', 'tags']),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Aktif')),
                TextColumn::make('updated_at')
                    ->label(__('Diperbarui Pada'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ContentTrashActions::trashedFilter(),
                TernaryFilter::make('is_active')
                    ->label(__('Status Publikasi'))
                    ->trueLabel(__('Aktif / Tayang'))
                    ->falseLabel(__('Draf / Nonaktif'))
                    ->placeholder(__('Semua'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('is_active', 'yes'),
                        false: fn (Builder $query): Builder => $query->where('is_active', '!=', 'yes'),
                    ),
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
}
