<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Filament\Support\AccessibleTableColumns;
use App\Filament\Support\ContentTrashActions;
use App\Support\TranslatedContentOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                //
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
