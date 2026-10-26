<?php

namespace App\Filament\Resources\Doctors\Tables;

use App\Models\Doctor;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DoctorTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('paramedic_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Dokter')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('specialty_name')
                    ->label('Spesialisasi')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_profile_complete')
                    ->label('Kelengkapan Profil')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-circle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (Doctor $record): string => $record->is_profile_complete
                        ? 'Profil 4 data marketing lengkap'
                        : 'Belum dilengkapi (menggunakan fallback bawaan)'),

                IconColumn::make('is_active')
                    ->label('Tampil di Web')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                SelectFilter::make('specialty_name')
                    ->label('Filter Spesialisasi')
                    ->options(fn () => Doctor::query()
                        ->whereNotNull('specialty_name')
                        ->distinct()
                        ->orderBy('specialty_name')
                        ->pluck('specialty_name', 'specialty_name')
                        ->toArray()
                    ),

                TernaryFilter::make('is_active')
                    ->label('Status Tampil di Web')
                    ->trueLabel('Aktif Saja')
                    ->falseLabel('Nonaktif Saja'),

                TernaryFilter::make('profile_status')
                    ->label('Kelengkapan 4 Data')
                    ->queries(
                        true: fn (Builder $query) => $query
                            ->whereNotNull('bio->id')->where('bio->id', '!=', '')
                            ->whereNotNull('bio->en')->where('bio->en', '!=', '')
                            ->whereNotNull('location->id')->where('location->id', '!=', '')
                            ->whereNotNull('location->en')->where('location->en', '!=', '')
                            ->whereNotNull('experience->id')->where('experience->id', '!=', '')
                            ->whereNotNull('experience->en')->where('experience->en', '!=', '')
                            ->whereNotNull('education->id')->where('education->id', '!=', '')
                            ->whereNotNull('education->en')->where('education->en', '!=', ''),
                        false: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereNull('bio->id')->orWhere('bio->id', '=', '')
                                ->orWhereNull('bio->en')->orWhere('bio->en', '=', '')
                                ->orWhereNull('location->id')->orWhere('location->id', '=', '')
                                ->orWhereNull('location->en')->orWhere('location->en', '=', '')
                                ->orWhereNull('experience->id')->orWhere('experience->id', '=', '')
                                ->orWhereNull('experience->en')->orWhere('experience->en', '=', '')
                                ->orWhereNull('education->id')->orWhere('education->id', '=', '')
                                ->orWhereNull('education->en')->orWhere('education->en', '=', '');
                        }),
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Kelola Profil'),
            ])
            ->toolbarActions([
                // Tidak ada Bulk Delete (sesuai batasan: no delete)
            ]);
    }
}
