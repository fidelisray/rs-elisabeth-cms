<?php

namespace App\Filament\Resources\FacilityServices\Tables;

use App\Support\ApiCacheInvalidator;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FacilityServiceTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Preview')
                    ->disk(config('filesystems.media_disk'))
                    ->width(120)
                    ->height(68),

                // TextColumn::make('sort_order')
                //     ->label('Urutan')
                //     ->sortable()
                //     ->alignCenter(),

                TextColumn::make('name')
                    ->label('Nama Fasilitas')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
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

            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->afterReordering(function (): void {
                ApiCacheInvalidator::forModule('facilities');
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
