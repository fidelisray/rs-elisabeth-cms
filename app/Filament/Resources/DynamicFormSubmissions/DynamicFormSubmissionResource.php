<?php

namespace App\Filament\Resources\DynamicFormSubmissions;

use App\Enums\DynamicFormMode;
use App\Enums\FeedbackTicketStatus;
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

    protected static ?string $navigationLabel = 'Form Submissions';

    protected static ?string $modelLabel = 'Form Submission';

    protected static ?string $pluralModelLabel = 'Form Submissions';

    protected static string|\UnitEnum|null $navigationGroup = 'Patient Feedback';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'ticket_number';

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof DynamicFormSubmission) {
            return null;
        }

        return filled($record->ticket_number)
            ? $record->ticket_number
            : 'Submission #'.$record->id;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = DynamicFormSubmission::query()
            ->where('status', FeedbackTicketStatus::Baru->value)
            ->whereHas('form', fn ($q) => $q->whereNull('dynamic_forms.deleted_at')->where('mode', DynamicFormMode::Feedback->value))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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
