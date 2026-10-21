<?php

use App\Support\BilingualJsonConstraints;
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
        Schema::create('facility_services', function (Blueprint $table) {
            $table->id();
            $table->jsonb('name');
            $table->jsonb('slug');
            $table->jsonb('description');
            $table->jsonb('short_description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('category')->nullable();
            $table->json('highlights')->nullable();
            $table->jsonb('wa_link_text')->nullable();
            $table->string('wa_number')->nullable();
            $table->jsonb('wa_prefilled_message')->nullable();
            $table->boolean('has_appointment_cta')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
        });

        BilingualJsonConstraints::add('facility_services', ['name', 'slug', 'description'], ['short_description', 'wa_link_text', 'wa_prefilled_message']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facility_services');
    }
};
