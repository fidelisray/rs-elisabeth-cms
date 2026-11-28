<?php

namespace App\Filament\Resources\RoomFacilities\Tables;

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

class RoomFacilitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                AccessibleTableColumns::thumbnail('image_path', 80, 45, 'name')
                    ->defaultImageUrl(asset('images/logo.png')),

                TextColumn::make('name')
                    ->label(__('Nama Ruangan'))
                    ->getStateUsing(fn ($record): string => AccessibleTableColumns::indonesianPrimary($record, 'name'))
                    ->description(fn ($record): ?string => AccessibleTableColumns::englishSubtitle($record, 'name'))
                    ->searchable()
                    ->sortable(query: TranslatedContentOrder::column('name'))
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label(__('Identifier'))
                    ->sortable(query: TranslatedContentOrder::column('slug'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('room_size')
                    ->label(__('Luas'))
                    ->searchable(),

                TextColumn::make('bed_count')
                    ->label(__('Tempat Tidur'))
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label(__('Dibuat Pada'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                BilingualTableColumns::contentLanguageBadge(
                    ['name', 'slug', 'tagline', 'description'],
                    ['whatsapp_text'],
                ),

                IconColumn::make('is_active')
                    ->label(__('Aktif'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label(__('Diperbarui'))
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->afterReordering(function (): void {
                ApiCacheInvalidator::forModule('room_facilities');
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
