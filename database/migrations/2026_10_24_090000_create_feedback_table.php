<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kritik dan saran pasien dari Web Frontend (publik, anonim per akun).
     *
     * Data pribadi (nama, nomor telepon) hanya boleh dibaca role yang berwenang.
     * Tidak dicatat ke log. Hard delete hanya lewat panel oleh super_admin.
     */
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();

            $table->string('ticket_number', 32)->unique();

            $table->string('reporter_name', 150);
            $table->string('relation_to_patient', 50);
            $table->string('relation_other', 150)->nullable();
            $table->string('patient_name', 150);
            $table->string('phone_number', 30);

            // Kategori keluhan: array string, disimpan sebagai JSON.
            $table->json('categories');
            $table->string('category_other', 150)->nullable();

            $table->text('complaint_detail');
            $table->text('expected_solution');

            $table->string('locale', 5)->default('id');
            $table->string('source', 30)->default('web_public');
            $table->timestamp('submitted_at')->nullable()->index();

            // Status penanganan oleh tim (diisi di panel).
            $table->string('status', 20)->default('baru')->index();
            $table->text('internal_note')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
