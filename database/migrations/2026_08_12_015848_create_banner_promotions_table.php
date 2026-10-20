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
        Schema::create('banner_promotions', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('slug')->nullable();
            $table->string('image_path');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            // Timestamps & Soft Deletes
            $table->timestamps();           // created_at, updated_at
            $table->softDeletes();          // deleted_at

            // Audit Trail — siapa yang melakukan operasi
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
        Schema::dropIfExists('banner_promotions');
    }
};
