<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menghapus tabel kode pemulihan web seiring penerapan Enterprise Pure CLI-Only Break-Glass
     * (pemulihan darurat super_admin eksklusif lewat php artisan app:super-admin:reset-password).
     */
    public function up(): void
    {
        Schema::dropIfExists('super_admin_recovery_codes');
    }

    public function down(): void
    {
        if (Schema::hasTable('super_admin_recovery_codes')) {
            return;
        }

        Schema::create('super_admin_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash');
            $table->char('lookup_hash', 64);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'lookup_hash']);
        });
    }
};
