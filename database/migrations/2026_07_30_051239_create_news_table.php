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
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->jsonb('title');
            $table->jsonb('slug');
            $table->string('author', 100)->nullable();
            $table->jsonb('shorts')->nullable();
            $table->jsonb('category')->nullable();
            $table->jsonb('content');
            $table->string('image_path')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
        });

        BilingualJsonConstraints::add('news', ['title', 'slug', 'content'], ['shorts', 'category']);
        TranslatedSlugIndexes::add('news');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
