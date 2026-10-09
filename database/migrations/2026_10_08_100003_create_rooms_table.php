<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('translations')->nullable();
            $table->string('room_type', 30)->default('classroom'); // classroom | workshop | boardroom | auditorium | studio
            $table->unsignedSmallInteger('size_sqm')->nullable();
            $table->string('floor_label', 20)->nullable();
            $table->unsignedSmallInteger('capacity'); // most participants the room holds in its roomiest set-up
            $table->json('seating_layouts')->nullable(); // [{type: classroom, capacity: 24}, ...]
            $table->boolean('catering_included')->default(false);
            $table->boolean('catering_available')->default(false);
            $table->decimal('catering_price', 12, 2)->nullable(); // per participant, per day
            $table->decimal('base_price', 12, 2); // per room, per day
            $table->json('amenities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['venue_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
