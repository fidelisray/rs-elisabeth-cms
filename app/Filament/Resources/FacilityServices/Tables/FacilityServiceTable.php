<?php

namespace App\Filament\Resources\FacilityServices\Tables;

use App\Filament\Support\AccessibleTableColumns;
use App\Filament\Support\BilingualTableColumns;
use App\Filament\Support\ContentTrashActions;
use App\Support\ApiCacheInvalidator;
use App\Support\TranslatedContentOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FacilityServiceTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                AccessibleTableColumns::thumbnail('image_path', 120, 68, 'name'),

                TextColumn::make('name')
                    ->label(__('Nama Fasilitas'))
                    ->getStateUsing(fn ($record): string => AccessibleTableColumns::indonesianPrimary($record, 'name'))
                    ->description(fn ($record): ?string => AccessibleTableColumns::englishSubtitle($record, 'name'))
                    ->searchable()
                    ->sortable(query: TranslatedContentOrder::column('name')),

                TextColumn::make('slug')
                    ->label(__('Identifier'))
                    ->sortable(query: TranslatedContentOrder::column('slug'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('category')
                    ->label(__('Kategori'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __($state) : '')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('Dibuat'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('Diperbarui'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                BilingualTableColumns::contentLanguageBadge(
                    ['name', 'slug', 'short_description', 'description'],
                    ['wa_link_text', 'wa_prefilled_message'],
                ),

                IconColumn::make('is_active')
                    ->label(__('Aktif'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),

            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->afterReordering(function (): void {
                ApiCacheInvalidator::forModule('facilities');
            })
            ->filters([
                ContentTrashActions::trashedFilter(),
                TernaryFilter::make('is_active')
                    ->label(__('Status Aktif'))
                    ->trueLabel(__('Aktif saja'))
                    ->falseLabel(__('Non-aktif saja'))
                    ->placeholder(__('Semua')),
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
