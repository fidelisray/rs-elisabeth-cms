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
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();

            // Identitas Master dari SIMRS MedInfras
            $table->string('paramedic_code')->unique();
            $table->string('name');
            $table->string('specialty_code')->nullable()->index();
            $table->string('specialty_name')->nullable();
            $table->string('service_unit_name')->nullable();

            // 4 Field Konten Khusus yang Dikelola Marketing (Multilingual)
            $table->jsonb('bio')->nullable();          // Biografi / Profil Dokter
            $table->jsonb('location')->nullable();     // Data Lokasi Praktik
            $table->jsonb('experience')->nullable();   // Data Pengalaman
            $table->jsonb('education')->nullable();    // Data Riwayat Pendidikan

            // Pengaturan Visibilitas
            $table->boolean('is_active')->default(true)->index();

            // Audit Trails & Soft Deletes
            $table->timestamps();
            $table->softDeletes();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
        });

        // Terapkan constraint JSON bilingual opsional untuk 4 field marketing
        BilingualJsonConstraints::add('doctors', [], ['bio', 'location', 'experience', 'education']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
