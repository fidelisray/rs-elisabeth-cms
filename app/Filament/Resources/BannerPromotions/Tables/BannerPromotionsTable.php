<?php

namespace App\Filament\Resources\BannerPromotions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BannerPromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->afterReordering(function (): void {
                \Illuminate\Support\Facades\Cache::forget('local_cms_banner_promotions_');
            })
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Preview')
                    ->disk('s3')
                    ->width(120)
                    ->height(68), // Menjaga aspek rasio 16:9 pada preview tabel

                TextColumn::make('title')
                    ->label('Judul Banner')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('created_by')
                    ->label('Dibuat Oleh')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y, H:i'),

                TextColumn::make('updated_by')
                    ->label('Diperbarui Oleh')
                    ->searchable(),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y, H:i'),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Aktif'),
            ])
            ->filters([
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
