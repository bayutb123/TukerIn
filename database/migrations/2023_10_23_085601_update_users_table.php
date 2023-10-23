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
        Schema::table('users', function ($table) {
            $table->string('api_token', 80)->after('password')
                                ->unique()
                                ->nullable()
                                ->default(null);
            // rename email_verified_at to verified_at
            $table->renameColumn('email_verified_at', 'verified_at');
            $table->string('profile_photo_path', 2048)->nullable()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function ($table) {
            $table->dropColumn('api_token');
            $table->renameColumn('verified_at', 'email_verified_at');
            $table->dropColumn('profile_photo_path');
        });
    }
};
