<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('farm_code', 64)->unique();
            $table->unsignedTinyInteger('ward_number');
            $table->unsignedInteger('total_goats');
            $table->string('feeding_system', 32);
            $table->string('deworming_frequency', 40);
            $table->string('common_anthelmintic', 32);
            $table->string('other_anthelmintic')->nullable();
            $table->string('schedule_decision_maker', 32);
            $table->string('other_decision_maker')->nullable();
            $table->string('dose_determination_method', 40);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('ward_number');
            $table->index('feeding_system');
            $table->index('deworming_frequency');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
