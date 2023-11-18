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
        // add new column named city
        Schema::table('posts', function (Blueprint $table) {
            $table->string('city')->after('longitude')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop column named city
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }
};
