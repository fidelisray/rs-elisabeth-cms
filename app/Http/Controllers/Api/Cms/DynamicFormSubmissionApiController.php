<?php

namespace App\Http\Controllers\Api\Cms;

use App\Enums\DynamicFormStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cms\StoreDynamicFormSubmissionRequest;
use App\Models\DynamicForm;
use App\Services\DynamicForm\DynamicFormSubmissionService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DynamicFormSubmissionApiController extends Controller
{
    public function __construct(
        private readonly DynamicFormSubmissionService $submissionService,
    ) {}

    #[OA\Post(
        path: '/api/v1/cms/forms/{slug}/submissions',
        summary: 'Submit answers to a published dynamic form',
        security: [['HmacAuth' => []]],
        tags: ['DynamicForms'],
        description: 'Validates conditional visibility, section jumps, field rules, atomic quota, and upload_tokens, then stores the submission.'
    )]
    #[OA\Parameter(name: 'slug', description: 'Dynamic form slug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['answers'],
            properties: [
                new OA\Property(property: 'version_id', type: 'integer', nullable: true),
                new OA\Property(property: 'answers', type: 'object'),
                new OA\Property(property: 'locale', type: 'string', enum: ['id', 'en'], nullable: true),
                new OA\Property(property: 'source', type: 'string', nullable: true),
                new OA\Property(property: 'respondent_key', type: 'string', nullable: true),
                new OA\Property(property: 'hp_website', type: 'string', nullable: true),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Submission recorded')]
    #[OA\Response(response: 422, description: 'Validation or quota error')]
    #[OA\Response(response: 404, description: 'Form not found')]
    public function store(StoreDynamicFormSubmissionRequest $request, string $slug): JsonResponse
    {
        $form = DynamicForm::query()
            ->where('slug', $slug)
            ->whereNotNull('published_version_id')
            ->whereIn('status', [DynamicFormStatus::Published->value, DynamicFormStatus::Closed->value])
            ->first();

        if (! $form) {
            return response()->json([
                'success' => false,
                'status_code' => 404,
                'message' => 'Formulir tidak ditemukan.',
                'errors' => null,
            ], 404);
        }

        $submission = $this->submissionService->submit($form, $request->validated());
        $confirmation = $form->defaultConfirmationMessage();

        return response()->json([
            'success' => true,
            'status_code' => 201,
            'message' => $confirmation,
            'data' => [
                'submission_id' => $submission->id,
                'ticket_number' => $submission->ticket_number,
                'confirmation_message' => $confirmation,
                'submitted_at' => $submission->submitted_at->toIso8601String(),
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
