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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('service');
            $table->date('booking_date');
            $table->time('booking_time');
            $table->string('customer_name');
            $table->string('mobile');
            $table->string('status')->default('pending');
            $table->string('source')->default('online');
            $table->boolean('slot_active')->nullable()->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'booking_date', 'booking_time', 'slot_active'], 'bookings_one_active_per_slot');
            $table->index(['branch_id', 'booking_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
