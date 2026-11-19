<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dynamic_forms', function (Blueprint $table) {
            $table->boolean('is_public_listed')
                ->default(false)
                ->index()
                ->after('allow_multiple_submissions');
        });
    }

    public function down(): void
    {
        Schema::table('dynamic_forms', function (Blueprint $table) {
            $table->dropIndex(['is_public_listed']);
            $table->dropColumn('is_public_listed');
        });
    }
};
