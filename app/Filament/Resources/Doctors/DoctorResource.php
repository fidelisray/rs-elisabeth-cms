<?php

namespace App\Filament\Resources\Doctors;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Doctors\Pages\ListDoctors;
use App\Filament\Resources\Doctors\Schemas\DoctorForm;
use App\Filament\Resources\Doctors\Tables\DoctorTable;
use App\Models\Doctor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class DoctorResource extends Resource
{
    use Translatable;

    protected static ?string $model = Doctor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::DoctorsLibrary;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('Profil Dokter');
    }

    public static function getModelLabel(): string
    {
        return __('model.doctor');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Profil Dokter');
    }

    /**
     * Menonaktifkan aksi create manual karena seluruh data dokter
     * bersumber dari sinkronisasi SIMRS MedInfras.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Menonaktifkan aksi delete untuk menjaga integritas data master SIMRS.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DoctorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DoctorTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDoctors::route('/'),
            'edit' => EditDoctor::route('/{record}/edit'),
        ];
    }
}
