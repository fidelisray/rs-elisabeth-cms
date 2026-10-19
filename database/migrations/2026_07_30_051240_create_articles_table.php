<?php

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
            $table->string('title', 250)->nullable();
            $table->string('slug', 255)->unique()->nullable();
            $table->string('image_path', 100)->nullable();
            $table->text('shorts')->nullable();
            $table->longText('content')->nullable();
            $table->string('tags', 100)->nullable();
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
