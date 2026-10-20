<?php

namespace App\Filament\Resources\Promotions\Tables;

use App\Models\Promotion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Preview')
                    ->disk(config('filesystems.media_disk'))
                    ->width(60)
                    ->height(85),

                TextColumn::make('title')
                    ->label('Judul Promosi')
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label('Mulai')
                    ->state(fn (Promotion $record): ?string => $record->start_date?->translatedFormat('d M Y') ?? $record->created_at?->translatedFormat('d M Y'))
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Berakhir')
                    ->state(fn (Promotion $record): string => $record->end_date?->translatedFormat('d M Y') ?? 'Aktif tanpa batas waktu')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
