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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->jsonb('title');
            $table->jsonb('slug');
            $table->jsonb('shorts')->nullable();
            $table->jsonb('description');
            $table->string('image_path')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
        });

        BilingualJsonConstraints::add('promotions', ['title', 'slug', 'description'], ['shorts']);
        TranslatedSlugIndexes::add('promotions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
