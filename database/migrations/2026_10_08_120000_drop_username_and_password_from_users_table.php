<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username', 'email']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'password']);
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 100)->after('id');
            $table->char('password', 255)->after('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['username', 'email']);
        });
    }
};
