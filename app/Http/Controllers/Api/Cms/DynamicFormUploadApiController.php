<?php

namespace App\Http\Controllers\Api\Cms;

use App\Enums\DynamicFormFieldType;
use App\Enums\DynamicFormStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cms\UploadDynamicFormFileRequest;
use App\Models\DynamicForm;
use App\Models\DynamicFormUpload;
use finfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class DynamicFormUploadApiController extends Controller
{
    /**
     * Peta ekstensi yang diizinkan ke MIME asli (finfo) untuk mencegah file palsu/eksekusi.
     *
     * @var array<string, list<string>>
     */
    private const EXTENSION_MIME_MAP = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'txt' => ['text/plain'],
    ];

    #[OA\Post(
        path: '/api/v1/cms/forms/{slug}/uploads',
        summary: 'Step-1 private file upload for a dynamic form field',
        security: [['HmacAuth' => []]],
        tags: ['DynamicForms'],
        description: 'Validates MIME/extension/size against the published form schema and stores the file in private storage, returning a short-lived upload_token.'
    )]
    #[OA\Parameter(name: 'slug', description: 'Dynamic form slug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'X-Content-SHA256', description: 'Hex SHA-256 hash of the uploaded file bytes; HMAC signs {timestamp}{consId}{X-Content-SHA256}', in: 'header', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['field_key', 'file'],
                properties: [
                    new OA\Property(property: 'field_key', type: 'string'),
                    new OA\Property(property: 'file', type: 'string', format: 'binary'),
                ]
            )
        )
    )]
    #[OA\Response(response: 201, description: 'File uploaded')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(UploadDynamicFormFileRequest $request, string $slug): JsonResponse
    {
        $form = DynamicForm::query()
            ->with('publishedVersion')
            ->where('slug', $slug)
            ->whereNotNull('published_version_id')
            ->whereIn('status', [DynamicFormStatus::Published->value, DynamicFormStatus::Closed->value])
            ->first();

        if (! $form || ! $form->publishedVersion) {
            return response()->json([
                'success' => false,
                'status_code' => 404,
                'message' => 'Formulir tidak ditemukan.',
                'errors' => null,
            ], 404);
        }

        $availability = $form->evaluateAvailability();
        if (! $availability['is_accepting_responses']) {
            return response()->json([
                'success' => false,
                'status_code' => 422,
                'message' => $form->defaultClosedMessage($availability['closed_reason']),
                'errors' => ['form' => [$form->defaultClosedMessage($availability['closed_reason'])]],
            ], 422);
        }

        $fieldKey = (string) $request->validated('field_key');
        $fieldSchema = $this->findFileField($form->publishedVersion->schema_snapshot ?? [], $fieldKey);

        if (! $fieldSchema) {
            return response()->json([
                'success' => false,
                'status_code' => 422,
                'message' => 'Field unggahan tidak ditemukan pada formulir ini.',
                'errors' => ['field_key' => ['Field unggahan tidak valid untuk formulir ini.']],
            ], 422);
        }

        $file = $request->file('file');
        $options = (array) ($fieldSchema['options'] ?? []);
        $allowedExtensions = array_values(array_map(
            'strtolower',
            (array) ($options['allowed_extensions'] ?? ['pdf', 'jpg', 'jpeg', 'png'])
        ));
        $maxMb = max(1, min(10, (int) ($options['max_file_size_mb'] ?? 5)));
        $maxBytes = $maxMb * 1024 * 1024;

        $sizeBytes = (int) ($file->getSize() ?: 0);
        if ($sizeBytes <= 0 || $sizeBytes > $maxBytes) {
            return response()->json([
                'success' => false,
                'status_code' => 422,
                'message' => "Ukuran berkas maksimal {$maxMb} MB.",
                'errors' => ['file' => ["Ukuran berkas harus antara 1 byte hingga {$maxMb} MB."]],
            ], 422);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '' || ! in_array($ext, $allowedExtensions, true) || ! array_key_exists($ext, self::EXTENSION_MIME_MAP)) {
            return response()->json([
                'success' => false,
                'status_code' => 422,
                'message' => 'Ekstensi berkas tidak diizinkan.',
                'errors' => ['file' => ['Ekstensi berkas yang diizinkan: '.implode(', ', $allowedExtensions).'.']],
            ], 422);
        }

        $realPath = $file->getRealPath();
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $realPath ? (string) $finfo->file($realPath) : '';

        $expectedMimes = self::EXTENSION_MIME_MAP[$ext];
        if ($detectedMime === '' || ! in_array($detectedMime, $expectedMimes, true)) {
            return response()->json([
                'success' => false,
                'status_code' => 422,
                'message' => 'Isi berkas tidak cocok dengan tipe file yang diizinkan.',
                'errors' => ['file' => ["Tipe MIME asli berkas ({$detectedMime}) tidak sesuai dengan ekstensi .{$ext}."]],
            ], 422);
        }

        $disk = (string) config('filesystems.dynamic_form_private_disk', 'local');
        $directory = "dynamic-forms/{$form->id}/".now()->format('Y/m');
        $storedFilename = Str::uuid()->toString().'.'.$ext;
        $storedPath = $file->storeAs($directory, $storedFilename, ['disk' => $disk, 'visibility' => 'private']);

        if (! is_string($storedPath) || $storedPath === '') {
            return response()->json([
                'success' => false,
                'status_code' => 500,
                'message' => 'Gagal menyimpan berkas lampiran.',
                'errors' => null,
            ], 500);
        }

        $rawName = str_replace('\\', '/', (string) $file->getClientOriginalName());
        $originalName = Str::limit(basename($rawName), 240, '');
        $token = Str::uuid()->toString();
        $expiresAt = now()->addHours(24);

        $upload = DynamicFormUpload::create([
            'token' => $token,
            'dynamic_form_id' => $form->id,
            'field_key' => $fieldKey,
            'disk' => $disk,
            'storage_path' => $storedPath,
            'original_name' => $originalName !== '' ? $originalName : $storedFilename,
            'mime_type' => $detectedMime,
            'size_bytes' => $sizeBytes,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'success' => true,
            'status_code' => 201,
            'message' => 'Berkas berhasil diunggah.',
            'data' => [
                'upload_token' => $upload->token,
                'original_name' => $upload->original_name,
                'size_bytes' => $upload->size_bytes,
                'mime_type' => $upload->mime_type,
                'expires_at' => $upload->expires_at->toIso8601String(),
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>|null
     */
    private function findFileField(array $snapshot, string $fieldKey): ?array
    {
        foreach ((array) ($snapshot['sections'] ?? []) as $section) {
            foreach ((array) ($section['fields'] ?? []) as $field) {
                if (($field['field_key'] ?? '') === $fieldKey
                    && ($field['type'] ?? '') === DynamicFormFieldType::FileUpload->value) {
                    return $field;
                }
            }
        }

        return null;
    }
}
