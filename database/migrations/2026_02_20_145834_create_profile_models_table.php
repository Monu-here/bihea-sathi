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
        Schema::create('profile_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->string('profile_for')->nullable()->comment('Self, Son, Daughter, Brother, Sister, Relative, Friend');
            $table->string('full_name');
            $table->integer('gender')->comment('0 = Male, 1 = Female, 2 = Other')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('education')->nullable()->comment('High School, Diploma, Bachelor\'s, Master\'s, Doctorate, etc.');
            $table->string('profession')->nullable();
            $table->string('annual_income')->nullable();
            $table->string('company_name')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->unsignedTinyInteger('siblings')->default(0);
            $table->string('family_type')->nullable()->comment('Nuclear, Joint, Extended');
            $table->string('family_values')->nullable()->comment('Orthodox, Traditional, Liberal');
            $table->string('diet')->nullable()->comment('Vegetarian, Non-Vegetarian, Eggetarian, Vegan');
            $table->string('drinking')->nullable()->comment('Never, Socially, Regularly');
            $table->string('smoking')->nullable()->comment('Never, Occasionally, Regularly');
            $table->unsignedTinyInteger('partner_age_min')->default(22);
            $table->unsignedTinyInteger('partner_age_max')->default(28);
            $table->string('partner_height_min')->nullable();
            $table->string('partner_height_max')->nullable();
            $table->string('partner_religion')->nullable();
            $table->string('partner_locations')->nullable();
            $table->string('main_photo')->nullable();
            $table->json('additional_photos')->nullable()->comment('Array of additional photo URLs');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->boolean('is_complete')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index('gender');
            $table->index('date_of_birth');
            $table->index('is_complete');
            $table->index('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_models');
    }
};
