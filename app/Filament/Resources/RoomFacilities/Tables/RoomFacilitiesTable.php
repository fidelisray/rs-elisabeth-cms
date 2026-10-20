<?php

namespace App\Filament\Resources\RoomFacilities\Tables;

use App\Support\ApiCacheInvalidator;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RoomFacilitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                // Preview foto ruangan (thumbnail kecil)
                ImageColumn::make('image_path')
                    ->label('Preview')
                    ->disk(config('filesystems.media_disk'))
                    ->width(80)
                    ->height(45) // 16:9 thumbnail ratio
                    ->defaultImageUrl(asset('images/logo.png')),

                // Nama ruangan
                TextColumn::make('name')
                    ->label('Nama Ruangan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('room_size')
                    ->label('Luas')
                    ->searchable(),

                TextColumn::make('bed_count')
                    ->label('Bed')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
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
                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif saja')
                    ->falseLabel('Non-aktif saja')
                    ->placeholder('Semua'),
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
