<?php

use App\Support\BilingualJsonConstraints;
use App\Support\TranslatedSlugIndexes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->jsonb('title');
            $table->jsonb('slug');
            $table->string('image_path', 100)->nullable();
            $table->jsonb('shorts')->nullable();
            $table->jsonb('content');
            $table->jsonb('tags')->nullable();
            $table->string('author', 50)->nullable();
            $table->string('is_active', 10)->nullable()->default('no');
            $table->integer('views')->nullable()->default(0);

            // Audit Trails & Timestamps
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
        });

        BilingualJsonConstraints::add('articles', ['title', 'slug', 'content'], ['shorts', 'tags']);
        TranslatedSlugIndexes::add('articles');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
