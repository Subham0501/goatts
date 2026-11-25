<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entity', 16);
            $table->char('record_uuid', 36);
            $table->string('action', 32);
            $table->string('message')->nullable();
            $table->timestamps();

            $table->index(['entity', 'record_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
