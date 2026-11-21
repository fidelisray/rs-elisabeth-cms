<?php

namespace App\Filament\Resources\DynamicForms\Pages;

use App\Enums\DynamicFormStatus;
use App\Filament\Resources\DynamicForms\Actions\DynamicFormShareAction;
use App\Filament\Resources\DynamicForms\DynamicFormResource;
use App\Filament\Resources\DynamicForms\Schemas\DynamicFormWizardSchema;
use App\Livewire\DynamicFormLivePreview;
use App\Models\DynamicForm;
use App\Services\DynamicForm\DynamicFormLogicEvaluator;
use App\Services\DynamicForm\DynamicFormVersionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\HasWizard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

class EditDynamicForm extends EditRecord
{
    use HasWizard;

    protected static string $resource = DynamicFormResource::class;

    public function getSteps(): array
    {
        return DynamicFormWizardSchema::steps(isEdit: true);
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    public function getSubheading(): ?string
    {
        /** @var DynamicForm $record */
        $record = $this->getRecord();
        $draft = $record->latestDraftVersion();
        $published = $record->publishedVersion;

        if ($published && $draft) {
            return "Menyunting Draf Versi v{$draft->version_number}. Responden publik saat ini tetap mengisi Versi Aktif v{$published->version_number} hingga Anda mempublikasikan draf ini.";
        }

        if ($published) {
            $hasSubmissions = $published->submissions()->withTrashed()->exists();

            return $hasSubmissions
                ? "Versi Aktif saat ini: v{$published->version_number}. Karena sudah ada respons masuk, perubahan pertanyaan akan diterbitkan sebagai versi baru tanpa mengganggu data respons lama."
                : "Versi Aktif saat ini: v{$published->version_number} (0 respons). Perubahan pertanyaan akan langsung memperbarui versi aktif ini saat disimpan.";
        }

        return 'Formulir masih berstatus Draf dan belum tampil pada Web Frontend.';
    }

    protected function getHeaderActions(): array
    {
        return [
            DynamicFormShareAction::make()
                ->record(fn (): DynamicForm => $this->getRecord()),

            Action::make('preview')
                ->label('Pratinjau Interaktif')
                ->icon(Heroicon::OutlinedEye)
                ->visible(fn (): bool => ! $this->getRecord()->trashed())
                ->modalHeading(fn (): string => 'Pratinjau: '.$this->getRecord()->title)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup Pratinjau')
                ->modalContent(function (): HtmlString {
                    $state = $this->form->getRawState();
                    $versionService = app(DynamicFormVersionService::class);
                    $merged = $versionService->mergeLogicConfigIntoSections(
                        (array) ($state['sections'] ?? []),
                        (array) ($state['logic_config'] ?? [])
                    );

                    return new HtmlString(Livewire::mount(DynamicFormLivePreview::class, [
                        'formId' => $this->getRecord()->id,
                        'customSchema' => ['sections' => $merged],
                    ]));
                }),

            Action::make('analytics')
                ->label('Ringkasan & Analitik')
                ->icon(Heroicon::OutlinedChartBar)
                ->visible(fn (): bool => ! $this->getRecord()->trashed())
                ->url(fn (): string => DynamicFormResource::getUrl('analytics', ['record' => $this->getRecord()])),

            Action::make('publish')
                ->label('Publikasikan Versi')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->requiresConfirmation()
                ->authorize(fn (): bool => Gate::allows('update', $this->getRecord()))
                ->visible(fn (): bool => ! $this->getRecord()->trashed())
                ->action(function (): void {
                    $rawState = $this->form->getRawState();
                    $willPublishInSave = (bool) ($rawState['publish_now'] ?? false);

                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    try {
                        $freshRecord = $this->getRecord()->fresh();
                        if ($willPublishInSave && $freshRecord->latestDraftVersion() === null && $freshRecord->published_version_id) {
                            $version = $freshRecord->publishedVersion;
                        } else {
                            $version = app(DynamicFormVersionService::class)->publishDraftVersion(
                                $freshRecord,
                                Auth::user()?->email,
                                consolidateIntoEmptyPublished: true,
                            );
                        }
                        $this->getRecord()->refresh();
                        Notification::make()
                            ->title("Versi v{$version->version_number} berhasil dipublikasikan")
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Publikasi ditolak')
                            ->body(collect($e->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();
                    }
                }),

            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var DynamicForm $form */
        $form = $this->getRecord();
        $versionService = app(DynamicFormVersionService::class);

        $sections = $versionService->loadEditableSectionsData($form);
        $data['sections'] = $sections;
        $data['logic_config'] = $versionService->extractLogicConfigFromSections($sections);
        $data['publish_now'] = $form->status === DynamicFormStatus::Published;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var DynamicForm $record */
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

        return DB::transaction(function () use ($record, $data, $mergedSections, $publishNow, $versionService): DynamicForm {
            $record->update([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'mode' => $data['mode'],
                'locale' => $data['locale'] ?? 'id',
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'max_responses' => $data['max_responses'] ?? null,
                'allow_multiple_submissions' => (bool) ($data['allow_multiple_submissions'] ?? true),
                'is_public_listed' => (bool) ($data['is_public_listed'] ?? false),
                'confirmation_message' => $data['confirmation_message'] ?? null,
                'closed_message' => $data['closed_message'] ?? null,
            ]);

            $versionService->syncDraftStructure($record, $mergedSections, true, $publishNow);

            if ($publishNow) {
                $versionService->publishDraftVersion(
                    $record,
                    Auth::user()?->email,
                    consolidateIntoEmptyPublished: true,
                );
            }

            return $record->fresh();
        });
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}
