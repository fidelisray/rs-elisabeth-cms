<?php

namespace App\Filament\Resources\DynamicFormSubmissions;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\DynamicFormSubmissions\Pages\ListDynamicFormSubmissions;
use App\Filament\Resources\DynamicFormSubmissions\Pages\ViewDynamicFormSubmission;
use App\Filament\Resources\DynamicFormSubmissions\Tables\DynamicFormSubmissionsTable;
use App\Models\DynamicFormSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DynamicFormSubmissionResource extends Resource
{
    protected static ?string $model = DynamicFormSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::PatientFeedback;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationLabel(): string
    {
        return __('Respons Formulir Kustom');
    }

    public static function getModelLabel(): string
    {
        return __('model.dynamic_form_submission');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Respons Formulir Kustom');
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof DynamicFormSubmission) {
            return null;
        }

        return __('Respons #:id', ['id' => $record->id]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return DynamicFormSubmissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDynamicFormSubmissions::route('/'),
            'view' => ViewDynamicFormSubmission::route('/{record}'),
        ];
    }
}
