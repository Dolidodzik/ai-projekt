<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ride_history', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('to_stop_id');
        });
    }

    public function down(): void
    {
        Schema::table('ride_history', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
