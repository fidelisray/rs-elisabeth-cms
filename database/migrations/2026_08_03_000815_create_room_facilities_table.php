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
        Schema::create('room_facilities', function (Blueprint $table) {
            $table->id();

            // Informasi Utama
            $table->jsonb('name');                              // Nama ruangan multi-bahasa: "President Suite", "VIP", dst
            $table->jsonb('slug');                              // Identifier unik per bahasa: "president-suite", "vip"
            $table->jsonb('tagline');                           // Deskripsi singkat pada card ruangan (wajib, I-1)
            $table->jsonb('description');                       // Deskripsi panjang ruangan (wajib, I-1)

            // Spesifikasi Kamar
            $table->string('room_size', 50)->nullable();        // Luas kamar, misal: "~40 m²"
            $table->string('bed_count', 50)->nullable();        // Jumlah bed, misal: "1 Tempat Tidur"
            $table->string('max_companion', 50)->nullable();    // Maks penunggu, misal: "Max 2 Penunggu"

            // Media
            $table->string('image_path')->nullable();           // Path foto landscape 16:9, disk public

            // Fasilitas & Tags (JSON)
            $table->jsonb('amenities')->nullable();              // Shared groups/items with bilingual text.
            $table->jsonb('highlight_tags')->nullable();         // Shared icons with bilingual labels.

            // CTA
            $table->jsonb('whatsapp_text')->nullable();         // Optional bilingual inquiry message.

            // Pengaturan Tampil
            $table->integer('sort_order')->default(0);          // Urutan tampil di halaman
            $table->boolean('is_active')->default(true);        // Toggle tampil/sembunyikan

            $table->timestamps();
            $table->softDeletes();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
        });

        BilingualJsonConstraints::add('room_facilities', ['name', 'slug', 'tagline', 'description'], ['whatsapp_text']);
        TranslatedSlugIndexes::add('room_facilities');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_facilities');
    }
};
