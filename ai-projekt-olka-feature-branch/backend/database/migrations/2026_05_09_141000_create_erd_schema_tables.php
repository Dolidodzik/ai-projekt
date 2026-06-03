<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('validity_minutes')->nullable();
            $table->boolean('is_long_term')->default(false);
        });

        Schema::create('user_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();
            $table->timestamp('purchase_date')->useCurrent();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(false);
        });

        Schema::create('gtfs_stops', function (Blueprint $table) {
            $table->id();
            $table->string('stop_id', 50)->unique();
            $table->string('stop_name');
            $table->decimal('stop_lat', 10, 8);
            $table->decimal('stop_lon', 11, 8);
        });

        Schema::create('gtfs_routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_id', 50)->unique();
            $table->string('route_short_name', 50);
            $table->string('route_long_name')->nullable();
            $table->integer('route_type');
        });

        Schema::create('gtfs_shape_groups', function (Blueprint $table) {
            $table->string('shape_id', 50)->primary();
        });

        Schema::create('gtfs_shapes', function (Blueprint $table) {
            $table->id();
            $table->string('shape_id', 50);
            $table->decimal('shape_pt_lat', 10, 8);
            $table->decimal('shape_pt_lon', 11, 8);
            $table->integer('shape_pt_sequence');
            $table->foreign('shape_id')->references('shape_id')->on('gtfs_shape_groups')->cascadeOnDelete();
        });

        Schema::create('gtfs_calendars', function (Blueprint $table) {
            $table->string('service_id', 50)->primary();
            $table->char('monday', 1);
            $table->char('tuesday', 1);
            $table->char('wednesday', 1);
            $table->char('thursday', 1);
            $table->char('friday', 1);
            $table->char('saturday', 1);
            $table->char('sunday', 1);
            $table->date('start_date');
            $table->date('end_date');
        });

        Schema::create('gtfs_calendar_dates', function (Blueprint $table) {
            $table->id();
            $table->string('service_id', 50)->nullable();
            $table->date('date');
            $table->integer('exception_type');
            $table->foreign('service_id')->references('service_id')->on('gtfs_calendars')->nullOnDelete();
        });

        Schema::create('gtfs_trips', function (Blueprint $table) {
            $table->id();
            $table->string('trip_id', 50)->unique();
            $table->foreignId('route_id')->constrained('gtfs_routes')->cascadeOnDelete();
            $table->string('service_id', 50);
            $table->string('shape_id', 50)->nullable();
            $table->integer('direction_id')->nullable();
            $table->foreign('service_id')->references('service_id')->on('gtfs_calendars')->restrictOnDelete();
            $table->foreign('shape_id')->references('shape_id')->on('gtfs_shape_groups')->nullOnDelete();
        });

        Schema::create('gtfs_stop_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('gtfs_trips')->cascadeOnDelete();
            $table->foreignId('stop_id')->constrained('gtfs_stops')->cascadeOnDelete();
            $table->time('arrival_time');
            $table->time('departure_time');
            $table->integer('stop_sequence');
        });

        Schema::create('ride_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained('gtfs_trips')->cascadeOnDelete();
            $table->foreignId('from_stop_id')->constrained('gtfs_stops')->cascadeOnDelete();
            $table->foreignId('to_stop_id')->constrained('gtfs_stops')->cascadeOnDelete();
        });

        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('file_name');
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->timestamp('created_at')->useCurrent();
            $table->enum('status', ['new', 'in_progress', 'resolved'])->default('new');
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
        });

        Schema::create('report_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('image_id')->constrained('images')->cascadeOnDelete();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->timestamp('published_at')->useCurrent();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('report_images');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('images');
        Schema::dropIfExists('ride_history');
        Schema::dropIfExists('gtfs_stop_times');
        Schema::dropIfExists('gtfs_trips');
        Schema::dropIfExists('gtfs_calendar_dates');
        Schema::dropIfExists('gtfs_calendars');
        Schema::dropIfExists('gtfs_shapes');
        Schema::dropIfExists('gtfs_shape_groups');
        Schema::dropIfExists('gtfs_routes');
        Schema::dropIfExists('gtfs_stops');
        Schema::dropIfExists('user_tickets');
        Schema::dropIfExists('ticket_types');
    }
};
