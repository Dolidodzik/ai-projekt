<?php

// Migracja: tabela gtfs_feed_versions - loguje wersje feedu i status każdego importu.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tworzy tabelę z historią importów GTFS.
    public function up(): void
    {
        Schema::create('gtfs_feed_versions', function (Blueprint $table) {
            $table->id();
            $table->string('feed_version');
            $table->string('source_url')->nullable();
            $table->string('status');
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index('feed_version');
            $table->index('status');
        });
    }

    // Cofa migrację - usuwa tabelę.
    public function down(): void
    {
        Schema::dropIfExists('gtfs_feed_versions');
    }
};
