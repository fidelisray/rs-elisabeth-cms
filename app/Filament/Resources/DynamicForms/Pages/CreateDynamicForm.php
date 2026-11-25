<?php

namespace App\Filament\Resources\DynamicForms\Pages;

use App\Enums\DynamicFormStatus;
use App\Filament\Resources\DynamicForms\DynamicFormResource;
use App\Filament\Resources\DynamicForms\Schemas\DynamicFormWizardSchema;
use App\Models\DynamicForm;
use App\Services\DynamicForm\DynamicFormHtmlSanitizer;
use App\Services\DynamicForm\DynamicFormLogicEvaluator;
use App\Services\DynamicForm\DynamicFormVersionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDynamicForm extends CreateRecord
{
    use HasWizard;

    protected static string $resource = DynamicFormResource::class;

    public function getSteps(): array
    {
        return DynamicFormWizardSchema::steps(isEdit: false);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $versionService = app(DynamicFormVersionService::class);
        $logicEvaluator = app(DynamicFormLogicEvaluator::class);

        $sections = (array) ($data['sections'] ?? []);
        $logicConfig = (array) ($data['logic_config'] ?? []);
        $publishNow = (bool) ($data['publish_now'] ?? false);

        $dupErrors = $logicEvaluator->validateUniqueRawKeys($sections);
        if ($dupErrors !== []) {
            Notification::make()
                ->danger()
                ->title('Terdapat Kunci Duplikat pada Formulir')
                ->body(implode(' ', $dupErrors))
                ->send();

            throw ValidationException::withMessages([
                'data.sections' => $dupErrors,
                'sections' => $dupErrors,
            ]);
        }

        $rawLogicErrors = $logicEvaluator->validateRawLogicConfig($logicConfig);
        $mergedSections = $versionService->mergeLogicConfigIntoSections($sections, $logicConfig);
        $logicErrors = array_values(array_unique([
            ...$rawLogicErrors,
            ...$logicEvaluator->validateSchemaLogic($mergedSections),
        ]));
        if ($logicErrors !== []) {
            Notification::make()
                ->danger()
                ->title('Aturan Percabangan Logika Tidak Valid')
                ->body(implode(' ', $logicErrors))
                ->send();

            throw ValidationException::withMessages([
                'data.logic_config.visibility_rules' => $logicErrors,
                'logic_config' => $logicErrors,
            ]);
        }

        return DB::transaction(function () use ($data, $mergedSections, $publishNow, $versionService): DynamicForm {
            $sanitizer = app(DynamicFormHtmlSanitizer::class);

            $form = DynamicForm::create([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'description' => $sanitizer->sanitize(isset($data['description']) ? (string) $data['description'] : null),
                'mode' => $data['mode'],
                'locale' => $data['locale'] ?? 'id',
                'status' => DynamicFormStatus::Draft,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'max_responses' => $data['max_responses'] ?? null,
                'allow_multiple_submissions' => (bool) ($data['allow_multiple_submissions'] ?? true),
                'is_public_listed' => (bool) ($data['is_public_listed'] ?? false),
                'confirmation_message' => $data['confirmation_message'] ?? null,
                'closed_message' => $data['closed_message'] ?? null,
            ]);

            $versionService->syncDraftStructure($form, $mergedSections, false);

            if ($publishNow) {
                $versionService->publishDraftVersion($form, Auth::user()?->email);
            }

            return $form->fresh();
        });
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Formulir dinamis berhasil disimpan');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
