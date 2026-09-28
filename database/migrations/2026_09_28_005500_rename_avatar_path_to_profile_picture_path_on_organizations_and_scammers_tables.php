<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->renameColumn('avatar_path', 'profile_picture_path');
        });

        Schema::table('scammers', function (Blueprint $table) {
            $table->renameColumn('avatar_path', 'profile_picture_path');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->renameColumn('profile_picture_path', 'avatar_path');
        });

        Schema::table('scammers', function (Blueprint $table) {
            $table->renameColumn('profile_picture_path', 'avatar_path');
        });
    }
};
