<?php

namespace App\Services\DynamicForm;

use App\Enums\DynamicFormFieldType;
use App\Enums\DynamicFormMode;
use App\Enums\DynamicFormStatus;
use App\Enums\FeedbackTicketStatus;
use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use App\Models\DynamicFormSubmissionAnswer;
use App\Models\DynamicFormUpload;
use App\Models\DynamicFormVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DynamicFormSubmissionService
{
    public function __construct(
        private readonly DynamicFormSubmissionValidator $validator,
    ) {}

    /**
     * Simpan submission secara transaksional dengan pengecekan kuota atomik (lockForUpdate).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function submit(DynamicForm $form, array $payload): DynamicFormSubmission
    {
        return DB::transaction(function () use ($form, $payload): DynamicFormSubmission {
            $lockedForm = DynamicForm::whereKey($form->id)->lockForUpdate()->firstOrFail();

            if ($lockedForm->status !== DynamicFormStatus::Published || ! $lockedForm->published_version_id) {
                throw ValidationException::withMessages([
                    'form' => [$lockedForm->defaultClosedMessage('not_published')],
                ]);
            }

            $availability = $lockedForm->evaluateAvailability();
            if (! $availability['is_accepting_responses']) {
                throw ValidationException::withMessages([
                    'form' => [$lockedForm->defaultClosedMessage($availability['closed_reason'])],
                ]);
            }

            /** @var DynamicFormVersion $version */
            $version = DynamicFormVersion::whereKey($lockedForm->published_version_id)
                ->where('status', 'published')
                ->firstOrFail();

            $isEnglish = (($payload['locale'] ?? $lockedForm->locale) === 'en');

            if (isset($payload['version_id']) && (int) $payload['version_id'] !== $version->id) {
                throw ValidationException::withMessages([
                    'version_id' => [
                        $isEnglish
                            ? 'The form version has been updated. Please reload the page before submitting.'
                            : 'Versi formulir telah diperbarui. Muat ulang formulir sebelum mengirim.',
                    ],
                ]);
            }

            $identifierHash = null;
            if (filled($payload['respondent_key'] ?? null)) {
                $identifierHash = hash(
                    'sha256',
                    $lockedForm->id.'|'.mb_strtolower(trim((string) $payload['respondent_key']))
                );

                if (! $lockedForm->allow_multiple_submissions) {
                    $duplicateExists = DynamicFormSubmission::where('dynamic_form_id', $lockedForm->id)
                        ->where('respondent_identifier_hash', $identifierHash)
                        ->exists();

                    if ($duplicateExists) {
                        throw ValidationException::withMessages([
                            'respondent_key' => [
                                $isEnglish
                                    ? 'You have already submitted this form.'
                                    : 'Anda sudah pernah mengisi formulir ini sebelumnya.',
                            ],
                        ]);
                    }
                }
            }

            $schemaSnapshot = (array) ($version->schema_snapshot ?? []);
            $validated = $this->validator->validate(
                $lockedForm,
                $schemaSnapshot,
                (array) ($payload['answers'] ?? [])
            );

            $isFeedback = $lockedForm->mode === DynamicFormMode::Feedback;
            $ticketNumber = $isFeedback ? $this->generateTicketNumber($lockedForm) : null;
            $status = $isFeedback ? FeedbackTicketStatus::Baru : null;
            $submittedAt = isset($payload['submitted_at']) ? now()->parse($payload['submitted_at']) : now();

            $submission = DynamicFormSubmission::create([
                'dynamic_form_id' => $lockedForm->id,
                'dynamic_form_version_id' => $version->id,
                'ticket_number' => $ticketNumber,
                'status' => $status,
                'answers' => $validated['answers'],
                'locale' => (string) ($payload['locale'] ?? $lockedForm->locale ?? app()->getLocale()),
                'source' => (string) ($payload['source'] ?? 'web_public'),
                'respondent_identifier_hash' => $identifierHash,
                'submitted_at' => $submittedAt,
            ]);

            if ($validated['upload_ids'] !== []) {
                $uploads = DynamicFormUpload::whereIn('id', $validated['upload_ids'])
                    ->lockForUpdate()
                    ->get();

                if ($uploads->count() !== count($validated['upload_ids'])) {
                    throw ValidationException::withMessages([
                        'answers' => [
                            $isEnglish
                                ? 'Some uploaded file tokens could not be found.'
                                : 'Sebagian token berkas lampiran tidak ditemukan.',
                        ],
                    ]);
                }

                foreach ($uploads as $upload) {
                    if ($upload->submission_id !== null || $upload->attached_at !== null) {
                        throw ValidationException::withMessages([
                            'answers' => [
                                $isEnglish
                                    ? "File token [{$upload->token}] has already been used."
                                    : "Token berkas [{$upload->token}] sudah terpakai.",
                            ],
                        ]);
                    }

                    $upload->update([
                        'submission_id' => $submission->id,
                        'attached_at' => now(),
                    ]);
                }
            }

            $this->indexSubmissionAnswers($lockedForm, $version, $submission, $schemaSnapshot, $validated['answers']);

            return $submission;
        });
    }

    private function generateTicketNumber(DynamicForm $form): string
    {
        $prefix = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) ($form->ticket_prefix ?: 'DFB')));
        if ($prefix === '') {
            $prefix = 'DFB';
        }

        do {
            $ticket = $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (DynamicFormSubmission::withTrashed()->where('ticket_number', $ticket)->exists());

        return $ticket;
    }

    /**
     * Normalisasi setiap jawaban ke tabel indeks dynamic_form_submission_answers
     * untuk query analitik yang cepat dan identik di SQLite maupun PostgreSQL.
     *
     * @param  array<string, mixed>  $schemaSnapshot
     * @param  array<string, mixed>  $answers
     */
    private function indexSubmissionAnswers(
        DynamicForm $form,
        DynamicFormVersion $version,
        DynamicFormSubmission $submission,
        array $schemaSnapshot,
        array $answers,
    ): void {
        $fieldDefs = [];
        foreach ((array) ($schemaSnapshot['sections'] ?? []) as $section) {
            foreach ((array) ($section['fields'] ?? []) as $field) {
                $fieldKey = (string) ($field['field_key'] ?? '');
                if ($fieldKey !== '') {
                    $fieldDefs[$fieldKey] = $field;
                }
            }
        }

        $now = now();
        $rows = [];

        foreach ($answers as $fieldKey => $value) {
            $fieldDef = $fieldDefs[$fieldKey] ?? [];
            $fieldType = (string) ($fieldDef['type'] ?? 'short_text');

            if ($fieldType === DynamicFormFieldType::MatrixGrid->value && is_array($value)) {
                foreach ($value as $rowKey => $cellValue) {
                    $rows[] = [
                        'submission_id' => $submission->id,
                        'dynamic_form_id' => $form->id,
                        'dynamic_form_version_id' => $version->id,
                        'field_key' => (string) $fieldKey,
                        'field_type' => $fieldType,
                        'row_key' => (string) $rowKey,
                        'value_text' => is_scalar($cellValue) ? (string) $cellValue : null,
                        'value_number' => null,
                        'value_json' => json_encode($cellValue, JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                    ];
                }

                continue;
            }

            if ($fieldType === DynamicFormFieldType::Radio->value && is_array($value)) {
                $rows[] = [
                    'submission_id' => $submission->id,
                    'dynamic_form_id' => $form->id,
                    'dynamic_form_version_id' => $version->id,
                    'field_key' => (string) $fieldKey,
                    'field_type' => $fieldType,
                    'row_key' => null,
                    'value_text' => (string) ($value['selected'] ?? ''),
                    'value_number' => null,
                    'value_json' => json_encode($value, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                ];

                continue;
            }

            if ($fieldType === DynamicFormFieldType::Checkbox->value && is_array($value)) {
                $rows[] = [
                    'submission_id' => $submission->id,
                    'dynamic_form_id' => $form->id,
                    'dynamic_form_version_id' => $version->id,
                    'field_key' => (string) $fieldKey,
                    'field_type' => $fieldType,
                    'row_key' => null,
                    'value_text' => implode(', ', (array) ($value['selected'] ?? [])),
                    'value_number' => null,
                    'value_json' => json_encode($value, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                ];

                continue;
            }

            if ($fieldType === DynamicFormFieldType::FileUpload->value && is_array($value)) {
                $names = collect($value)->pluck('original_name')->filter()->implode(', ');
                $rows[] = [
                    'submission_id' => $submission->id,
                    'dynamic_form_id' => $form->id,
                    'dynamic_form_version_id' => $version->id,
                    'field_key' => (string) $fieldKey,
                    'field_type' => $fieldType,
                    'row_key' => null,
                    'value_text' => $names,
                    'value_number' => count($value),
                    'value_json' => json_encode($value, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                ];

                continue;
            }

            $isNumericField = in_array($fieldType, [
                DynamicFormFieldType::LinearScale->value,
                DynamicFormFieldType::Rating->value,
            ], true) || (
                $fieldType === DynamicFormFieldType::ShortText->value
                && (($fieldDef['options']['subtype'] ?? 'text') === 'number')
            );

            $numericValue = $isNumericField && is_numeric($value) && abs((float) $value) < 1e15
                ? (float) $value
                : null;

            $rows[] = [
                'submission_id' => $submission->id,
                'dynamic_form_id' => $form->id,
                'dynamic_form_version_id' => $version->id,
                'field_key' => (string) $fieldKey,
                'field_type' => $fieldType,
                'row_key' => null,
                'value_text' => is_scalar($value) ? (string) $value : null,
                'value_number' => $numericValue,
                'value_json' => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : null,
                'created_at' => $now,
            ];
        }

        if ($rows !== []) {
            DynamicFormSubmissionAnswer::insert($rows);
        }
    }
}
