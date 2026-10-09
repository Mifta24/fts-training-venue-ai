<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name');
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('contact_type', 20)->nullable(); // whatsapp | phone | email
            $table->string('locale', 2)->nullable(); // language the organiser wrote in
            $table->string('organization')->nullable();
            $table->string('event_title')->nullable();
            $table->date('start_date');
            $table->date('end_date'); // the last day of the programme, inclusive
            $table->unsignedSmallInteger('participants')->default(1);
            $table->unsignedTinyInteger('room_count')->default(1);
            $table->string('seating_layout', 30)->nullable();
            $table->boolean('catering')->default(false);
            $table->decimal('total_price', 12, 2);
            $table->string('status')->default('pending'); // pending | confirmed | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
