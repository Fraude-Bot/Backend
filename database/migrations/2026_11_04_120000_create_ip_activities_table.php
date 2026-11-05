<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_activities', function (Blueprint $table) {
            $table->id();
            $table->string('action', 64);
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('ip_address', 45)->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_activities');
    }
};
