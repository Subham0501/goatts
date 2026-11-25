<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goats', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->char('farm_uuid', 36);
            $table->string('goat_code', 64);
            $table->unsignedSmallInteger('age_value');
            $table->string('age_unit', 16);
            $table->string('sex', 16);
            $table->string('breed', 32);
            $table->string('other_breed')->nullable();
            $table->unsignedTinyInteger('body_condition_score');
            $table->string('last_deworming_duration', 40);
            $table->string('last_deworming_medicine', 32);
            $table->string('other_last_deworming_medicine')->nullable();
            $table->string('deworming_frequency_last_12_months', 40);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['farm_uuid', 'goat_code']);
            $table->index('sex');
            $table->index('breed');
            $table->index('updated_at');
            $table->foreign('farm_uuid')->references('uuid')->on('farms');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goats');
    }
};
