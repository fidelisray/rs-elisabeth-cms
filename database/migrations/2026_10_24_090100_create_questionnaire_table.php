<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jawaban kuesioner kepuasan pasien (satu baris per pengisian).
     *
     * questionnaire_type: igd | rawat-jalan | rawat-inap (format URL, sama di seluruh kontrak).
     * demographics dan ratings disimpan JSON agar jumlah pertanyaan dapat berubah tanpa migration baru.
     */
    public function up(): void
    {
        Schema::create('questionnaire_responses', function (Blueprint $table) {
            $table->id();

            $table->string('questionnaire_type', 20)->index();

            $table->string('age_group', 20);
            $table->string('gender', 20);
            $table->string('payment_method', 40);

            // Pasangan kode pertanyaan => skala jawaban.
            $table->json('ratings');
            $table->text('suggestions')->nullable();

            $table->string('locale', 5)->default('id');
            $table->string('source', 30)->default('qr_barcode');
            $table->timestamp('submitted_at')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_responses');
    }
};
