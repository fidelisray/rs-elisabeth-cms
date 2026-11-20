<?php

namespace App\Filament\Resources\DynamicFormSubmissions\Pages;

use App\Actions\FollowUpDynamicFormSubmission;
use App\Enums\DynamicFormFieldType;
use App\Enums\DynamicFormMode;
use App\Enums\FeedbackTicketStatus;
use App\Filament\Resources\DynamicFormSubmissions\DynamicFormSubmissionResource;
use App\Http\Controllers\Admin\DownloadDynamicFormUploadController;
use App\Models\DynamicFormSubmission;
use App\Models\DynamicFormUpload;
use App\Services\DynamicForm\DynamicFormLogicEvaluator;
use App\Services\DynamicForm\DynamicFormVersionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ViewDynamicFormSubmission extends ViewRecord
{
    protected static string $resource = DynamicFormSubmissionResource::class;

    protected string $view = 'filament.resources.dynamic-form-submissions.pages.view-submission';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('followUp')
                ->label('Tindak lanjut')
                ->color('primary')
                ->authorize(fn (): bool => Gate::allows('followUp', $this->getRecord()))
                ->visible(fn (): bool => Gate::allows('followUp', $this->getRecord()))
                ->schema([
                    Select::make('status')
                        ->label('Status Tindak Lanjut')
                        ->options(FeedbackTicketStatus::options())
                        ->required(),
                    Textarea::make('internal_note')
                        ->label('Catatan Internal')
                        ->rows(4)
                        ->maxLength(5000),
                ])
                ->fillForm(function (): array {
                    /** @var DynamicFormSubmission $record */
                    $record = $this->getRecord();
                    $statusVal = $record->status instanceof FeedbackTicketStatus
                        ? $record->status->value
                        : (string) ($record->status ?? FeedbackTicketStatus::Baru->value);

                    return [
                        'status' => $statusVal,
                        'internal_note' => $record->internal_note,
                    ];
                })
                ->action(function (array $data): void {
                    /** @var DynamicFormSubmission $record */
                    $record = $this->getRecord();
                    app(FollowUpDynamicFormSubmission::class)->handle($record, $data);
                    $record->refresh();
                    Notification::make()->title('Tindak lanjut disimpan')->success()->send();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var DynamicFormSubmission $submission */
        $submission = $this->getRecord();
        $submission->loadMissing(['form', 'version', 'uploads']);

        $version = $submission->version;
        $snapshot = $version && is_array($version->schema_snapshot) && ! empty($version->schema_snapshot['sections'])
            ? $version->schema_snapshot
            : ($version ? app(DynamicFormVersionService::class)->buildSchemaSnapshot($version) : ['sections' => []]);

        $answers = (array) ($submission->answers ?? []);
        $uploadsById = $submission->uploads->keyBy('id');
        $uploadsByToken = $submission->uploads->keyBy('token');
        $canDownload = Gate::allows('downloadAttachment', $submission);

        $renderedSections = [];
        $renderedKeys = [];

        foreach ((array) ($snapshot['sections'] ?? []) as $section) {
            $sectionItems = [];
            foreach ((array) ($section['fields'] ?? []) as $field) {
                $fieldKey = (string) ($field['field_key'] ?? '');
                $type = DynamicFormFieldType::tryFrom((string) ($field['type'] ?? ''));
                if (! $type || ! $type->isInput()) {
                    continue;
                }
                $renderedKeys[$fieldKey] = true;
                $rawValue = $answers[$fieldKey] ?? null;

                $sectionItems[] = $this->formatFieldDisplay(
                    $field,
                    $type,
                    $rawValue,
                    $uploadsById,
                    $uploadsByToken,
                    $canDownload
                );
            }

            if ($sectionItems !== []) {
                $renderedSections[] = [
                    'title' => (string) ($section['title'] ?? 'Bagian'),
                    'items' => $sectionItems,
                ];
            }
        }

        // Tangani jawaban jika ada kunci lama yang tidak lagi berada di snapshot.
        $orphanItems = [];
        foreach ($answers as $fieldKey => $rawValue) {
            if (isset($renderedKeys[$fieldKey])) {
                continue;
            }
            $orphanItems[] = [
                'label' => (string) $fieldKey,
                'field_key' => (string) $fieldKey,
                'type' => 'raw',
                'display_text' => is_scalar($rawValue) ? (string) $rawValue : json_encode($rawValue, JSON_UNESCAPED_UNICODE),
            ];
        }

        return [
            'submission' => $submission,
            'isFeedback' => $submission->form?->mode === DynamicFormMode::Feedback,
            'renderedSections' => $renderedSections,
            'orphanItems' => $orphanItems,
            'canDownload' => $canDownload,
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  Collection<int, DynamicFormUpload>  $uploadsById
     * @param  Collection<string, DynamicFormUpload>  $uploadsByToken
     * @return array<string, mixed>
     */
    private function formatFieldDisplay(
        array $field,
        DynamicFormFieldType $type,
        mixed $rawValue,
        $uploadsById,
        $uploadsByToken,
        bool $canDownload,
    ): array {
        $options = (array) ($field['options'] ?? []);
        $base = [
            'label' => (string) ($field['label'] ?? $field['field_key']),
            'field_key' => (string) $field['field_key'],
            'type' => $type->value,
            'type_label' => $type->label(),
            'is_answered' => $rawValue !== null && $rawValue !== '' && $rawValue !== [],
        ];

        if (! $base['is_answered']) {
            return array_merge($base, ['display_text' => 'Tidak dijawab (kosong / tersembunyi).']);
        }

        if ($type === DynamicFormFieldType::Radio) {
            $choiceMap = collect((array) ($options['choices'] ?? []))
                ->mapWithKeys(fn ($c): array => [(string) ($c['key'] ?? '') => (string) ($c['label'] ?? '')])
                ->all();
            $selected = is_array($rawValue) ? (string) ($rawValue['selected'] ?? '') : (string) $rawValue;
            if ($selected === DynamicFormLogicEvaluator::OTHER_CHOICE_KEY) {
                $otherText = is_array($rawValue) ? trim((string) ($rawValue['other_text'] ?? '')) : '';
                $text = $otherText !== '' ? "Lainnya: {$otherText}" : 'Lainnya';
            } else {
                $text = $choiceMap[$selected] ?? $selected;
            }

            return array_merge($base, ['display_text' => $text]);
        }

        if ($type === DynamicFormFieldType::Dropdown) {
            $choiceMap = collect((array) ($options['choices'] ?? []))
                ->mapWithKeys(fn ($c): array => [(string) ($c['key'] ?? '') => (string) ($c['label'] ?? '')])
                ->all();

            return array_merge($base, ['display_text' => $choiceMap[(string) $rawValue] ?? (string) $rawValue]);
        }

        if ($type === DynamicFormFieldType::Checkbox && is_array($rawValue)) {
            $choiceMap = collect((array) ($options['choices'] ?? []))
                ->mapWithKeys(fn ($c): array => [(string) ($c['key'] ?? '') => (string) ($c['label'] ?? '')])
                ->all();
            $selected = (array) ($rawValue['selected'] ?? $rawValue);
            $otherText = trim((string) ($rawValue['other_text'] ?? ''));

            $labels = collect($selected)->map(function ($key) use ($choiceMap, $otherText): string {
                $k = (string) $key;
                if ($k === DynamicFormLogicEvaluator::OTHER_CHOICE_KEY) {
                    return $otherText !== '' ? "Lainnya: {$otherText}" : 'Lainnya';
                }

                return $choiceMap[$k] ?? $k;
            })->values()->all();

            return $labels !== []
                ? array_merge($base, ['display_list' => $labels])
                : array_merge($base, ['display_text' => 'Tidak dijawab (kosong / tersembunyi).', 'is_answered' => false]);
        }

        if ($type === DynamicFormFieldType::MatrixGrid && is_array($rawValue)) {
            $colMap = collect((array) ($options['columns'] ?? []))
                ->mapWithKeys(fn ($c): array => [(string) ($c['key'] ?? '') => (string) ($c['label'] ?? '')])
                ->all();

            $rows = [];
            foreach ((array) ($options['rows'] ?? []) as $mRow) {
                $rowKey = (string) ($mRow['key'] ?? '');
                $cellVal = $rawValue[$rowKey] ?? null;
                $formattedCell = '-';
                if (is_array($cellVal)) {
                    $formattedCell = collect($cellVal)->map(fn ($k) => $colMap[(string) $k] ?? (string) $k)->implode(', ');
                } elseif ($cellVal !== null && $cellVal !== '') {
                    $formattedCell = $colMap[(string) $cellVal] ?? (string) $cellVal;
                }

                $rows[] = [
                    'row_label' => (string) ($mRow['label'] ?? $rowKey),
                    'answer_label' => $formattedCell,
                ];
            }

            return array_merge($base, ['matrix_rows' => $rows]);
        }

        if ($type === DynamicFormFieldType::FileUpload && is_array($rawValue)) {
            $files = [];
            foreach ($rawValue as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $upload = isset($item['upload_id'])
                    ? $uploadsById->get((int) $item['upload_id'])
                    : (isset($item['token']) ? $uploadsByToken->get((string) $item['token']) : null);

                $mimeType = (string) ($item['mime_type'] ?? $upload?->mime_type ?? '');
                $canPreview = $canDownload
                    && $upload
                    && DownloadDynamicFormUploadController::supportsInlinePreview($mimeType);

                $files[] = [
                    'upload_id' => $upload?->id ?? ($item['upload_id'] ?? null),
                    'original_name' => (string) ($item['original_name'] ?? $upload?->original_name ?? 'berkas'),
                    'mime_type' => $mimeType,
                    'size_bytes' => (int) ($item['size_bytes'] ?? $upload?->size_bytes ?? 0),
                    'preview_url' => $canPreview
                        ? route('filament.admin.dynamic-forms.uploads.download', ['upload' => $upload->id, 'inline' => 1])
                        : null,
                    'download_url' => ($canDownload && $upload)
                        ? route('filament.admin.dynamic-forms.uploads.download', ['upload' => $upload->id])
                        : null,
                ];
            }

            return array_merge($base, ['files' => $files]);
        }

        return array_merge($base, [
            'display_text' => is_scalar($rawValue) ? (string) $rawValue : json_encode($rawValue, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
