<?php

namespace App\Filament\Resources\Promotions\Tables;

use App\Filament\Support\AccessibleTableColumns;
use App\Filament\Support\ContentTrashActions;
use App\Models\Promotion;
use App\Support\TranslatedContentOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                AccessibleTableColumns::thumbnail('image_path', 60, 85, 'title'),

                TextColumn::make('title')
                    ->label(__('Judul Promosi'))
                    ->getStateUsing(fn (Promotion $record): string => AccessibleTableColumns::indonesianPrimary($record, 'title'))
                    ->description(fn (Promotion $record): ?string => AccessibleTableColumns::englishSubtitle($record, 'title'))
                    ->sortable(query: TranslatedContentOrder::column('title'))
                    ->searchable(),

                TextColumn::make('slug')
                    ->label(__('Identifier'))
                    ->sortable(query: TranslatedContentOrder::column('slug'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('start_date')
                    ->label(__('Mulai'))
                    ->state(fn (Promotion $record): ?string => $record->start_date?->translatedFormat('d M Y') ?? $record->created_at?->translatedFormat('d M Y'))
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label(__('Berakhir'))
                    ->state(fn (Promotion $record): string => $record->end_date?->translatedFormat('d M Y') ?? __('Aktif tanpa batas waktu'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('Dibuat Pada'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('Aktif'))
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label(__('Diperbarui'))
                    ->dateTime('d M Y, H:i')
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
