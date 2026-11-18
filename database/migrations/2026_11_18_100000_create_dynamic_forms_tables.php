<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_forms', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('mode', 30)->default('questionnaire')->index();
            $table->string('locale', 10)->default('id')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedBigInteger('published_version_id')->nullable()->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_responses')->nullable();
            $table->boolean('allow_multiple_submissions')->default(true);
            $table->string('ticket_prefix', 20)->nullable();
            $table->text('confirmation_message')->nullable();
            $table->text('closed_message')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dynamic_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_form_id')->constrained('dynamic_forms')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 30)->default('draft')->index();
            $table->json('schema_snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('published_by')->nullable();
            $table->timestamps();

            $table->unique(['dynamic_form_id', 'version_number']);
        });

        Schema::create('dynamic_form_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_form_version_id')->constrained('dynamic_form_versions')->cascadeOnDelete();
            $table->string('section_key', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('next_section_rule')->nullable();
            $table->timestamps();

            $table->unique(['dynamic_form_version_id', 'section_key']);
        });

        Schema::create('dynamic_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_form_version_id')->constrained('dynamic_form_versions')->cascadeOnDelete();
            $table->foreignId('dynamic_form_section_id')->constrained('dynamic_form_sections')->cascadeOnDelete();
            $table->string('field_key', 80);
            $table->string('type', 40);
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->string('placeholder')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('conditional_logic')->nullable();
            $table->timestamps();

            $table->unique(['dynamic_form_version_id', 'field_key']);
        });

        Schema::create('dynamic_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_form_id')->constrained('dynamic_forms')->cascadeOnDelete();
            $table->foreignId('dynamic_form_version_id')->constrained('dynamic_form_versions')->restrictOnDelete();
            $table->string('ticket_number', 40)->nullable()->unique();
            $table->string('status', 30)->nullable()->index();
            $table->text('internal_note')->nullable();
            $table->json('answers');
            $table->string('locale', 10)->default('id');
            $table->string('source', 50)->default('web_public');
            $table->string('respondent_identifier_hash', 64)->nullable()->index();
            $table->timestamp('submitted_at')->index();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dynamic_form_id', 'submitted_at']);
            $table->index(['dynamic_form_id', 'dynamic_form_version_id'], 'dfs_form_version_idx');
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('dynamic_form_submission_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('dynamic_form_submissions')->cascadeOnDelete();
            $table->foreignId('dynamic_form_id')->constrained('dynamic_forms')->cascadeOnDelete();
            $table->foreignId('dynamic_form_version_id')->constrained('dynamic_form_versions')->cascadeOnDelete();
            $table->string('field_key', 80);
            $table->string('field_type', 40);
            $table->string('row_key', 80)->nullable();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 20, 4)->nullable();
            $table->json('value_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['dynamic_form_id', 'field_key', 'row_key'], 'dfsa_form_field_row_idx');
            $table->index(['submission_id', 'field_key'], 'dfsa_submission_field_idx');
        });

        Schema::create('dynamic_form_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('dynamic_form_id')->constrained('dynamic_forms')->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained('dynamic_form_submissions')->nullOnDelete();
            $table->string('field_key', 80);
            $table->string('disk', 50);
            $table->string('storage_path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamp('expires_at')->index();
            $table->timestamp('attached_at')->nullable();
            $table->timestamps();

            $table->index(['dynamic_form_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_form_uploads');
        Schema::dropIfExists('dynamic_form_submission_answers');
        Schema::dropIfExists('dynamic_form_submissions');
        Schema::dropIfExists('dynamic_form_fields');
        Schema::dropIfExists('dynamic_form_sections');
        Schema::dropIfExists('dynamic_form_versions');
        Schema::dropIfExists('dynamic_forms');
    }
};
