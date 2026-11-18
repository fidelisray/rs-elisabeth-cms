<?php

namespace App\Filament\Resources\DynamicFormSubmissions\Tables;

use App\Enums\DynamicFormMode;
use App\Enums\FeedbackTicketStatus;
use App\Models\DynamicFormVersion;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DynamicFormSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('ticket_number')
                    ->label('Nomor Tiket')
                    ->placeholder('-')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('form.title')
                    ->label('Formulir')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('form.mode')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn (DynamicFormMode|string|null $state): string => $state instanceof DynamicFormMode
                        ? $state->label()
                        : (DynamicFormMode::tryFrom((string) $state)?->label() ?? '-'))
                    ->color(fn (DynamicFormMode|string|null $state): string => ($state instanceof DynamicFormMode ? $state : DynamicFormMode::tryFrom((string) $state)) === DynamicFormMode::Feedback
                        ? 'warning'
                        : 'info'),

                TextColumn::make('version.version_number')
                    ->label('Versi')
                    ->formatStateUsing(fn ($state): string => $state ? 'v'.$state : '-')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status Tiket')
                    ->placeholder('-')
                    ->badge()
                    ->formatStateUsing(fn (FeedbackTicketStatus|string|null $state): string => $state instanceof FeedbackTicketStatus
                        ? $state->label()
                        : (FeedbackTicketStatus::tryFrom((string) $state)?->label() ?? '-'))
                    ->color(fn (FeedbackTicketStatus|string|null $state): string => match ($state instanceof FeedbackTicketStatus ? $state : FeedbackTicketStatus::tryFrom((string) $state)) {
                        FeedbackTicketStatus::Baru => 'warning',
                        FeedbackTicketStatus::Diproses => 'info',
                        FeedbackTicketStatus::Selesai => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('locale')
                    ->label('Bahasa')
                    ->formatStateUsing(fn (?string $state): string => strtoupper((string) $state))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('submitted_at')
                    ->label('Dikirim Pada')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('dynamic_form_id')
                    ->label('Formulir')
                    ->relationship('form', 'title')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('mode')
                    ->label('Mode Formulir')
                    ->options(DynamicFormMode::options())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereHas('form', fn (Builder $formQuery) => $formQuery->where('mode', $value));
                    }),

                SelectFilter::make('status')
                    ->label('Status Tiket Feedback')
                    ->options(FeedbackTicketStatus::options()),

                SelectFilter::make('dynamic_form_version_id')
                    ->label('Versi Formulir')
                    ->options(fn (): array => DynamicFormVersion::query()
                        ->with('form:id,title')
                        ->orderByDesc('id')
                        ->limit(100)
                        ->get()
                        ->mapWithKeys(fn (DynamicFormVersion $v): array => [
                            $v->id => ($v->form?->title ?? 'Form #'.$v->dynamic_form_id).' (v'.$v->version_number.')',
                        ])->all()),

                Filter::make('submitted_at')
                    ->label('Rentang Tanggal Kirim')
                    ->schema([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('submitted_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('submitted_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->paginationPageOptions([10, 25, 50]);
    }
}
