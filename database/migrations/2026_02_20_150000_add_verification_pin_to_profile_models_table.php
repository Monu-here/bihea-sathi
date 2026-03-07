<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_models', function (Blueprint $table) {
            $table->string('verification_pin', 6)->nullable()->after('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('profile_models', function (Blueprint $table) {
            $table->dropColumn('verification_pin');
        });
    }
};
