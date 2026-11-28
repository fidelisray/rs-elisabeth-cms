<?php

namespace App\Filament\Resources\BannerPromotions\Tables;

use App\Filament\Support\AccessibleTableColumns;
use App\Filament\Support\ContentTrashActions;
use App\Support\ApiCacheInvalidator;
use App\Support\TranslatedContentOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BannerPromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->afterReordering(function (): void {
                ApiCacheInvalidator::forModule('banner_promotions');
            })
            ->columns([
                AccessibleTableColumns::thumbnail('image_path', 120, 68, 'title'),

                TextColumn::make('title')
                    ->label(__('Judul Banner'))
                    ->getStateUsing(fn ($record): string => AccessibleTableColumns::indonesianPrimary($record, 'title'))
                    ->description(fn ($record): ?string => AccessibleTableColumns::englishSubtitle($record, 'title'))
                    ->searchable()
                    ->sortable(query: TranslatedContentOrder::column('title'))
                    ->limit(50),

                TextColumn::make('slug')
                    ->label(__('Identifier'))
                    ->sortable(query: TranslatedContentOrder::column('slug'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_by')
                    ->label(__('Dibuat Oleh'))
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label(__('Dibuat'))
                    ->dateTime('d M Y, H:i'),

                TextColumn::make('updated_by')
                    ->label(__('Diperbarui Oleh'))
                    ->searchable(),

                TextColumn::make('updated_at')
                    ->label(__('Diperbarui'))
                    ->dateTime('d M Y, H:i'),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Aktif')),
            ])
            ->filters([
                ContentTrashActions::trashedFilter(),
                // TernaryFilter::make('is_active')
                //     ->label('Status Aktif')
                //     ->trueLabel('Aktif saja')
                //     ->falseLabel('Non-aktif saja')
                //     ->placeholder('Semua'),
                // SelectFilter::make('category')
                //     ->label('Kategori')
                //     ->options([
                //         'promo' => 'Promo',
                //         'hospital_info' => 'Info Rumah Sakit',
                //         'announcement' => 'Pengumuman',
                //         'event' => 'Acara',
                //         'health_tips' => 'Tips Kesehatan',
                //     ]),
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
