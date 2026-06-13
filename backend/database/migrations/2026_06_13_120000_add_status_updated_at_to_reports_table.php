<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->timestamp('status_updated_at')->nullable()->after('status');
        });

        DB::table('reports')->update([
            'status_updated_at' => DB::raw('COALESCE(resolved_at, created_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('status_updated_at');
        });
    }
};
