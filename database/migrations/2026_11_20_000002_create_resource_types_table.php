<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tip vira (npr. "Jahanje", "Frizerski salon", "Najem avtomobilov", "Glamping hiške").
 * Določa način rezervacije in pravila, ki veljajo za vse vire tega tipa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 40)->default('calendar');
            $table->string('color', 20)->nullable();

            // timeslot | service | nightly | hourly (glej App\Enums\BookingMode)
            $table->string('booking_mode', 20);

            // Poimenovanje vira v uporabniškem vmesniku (npr. "Konj", "Frizer", "Soba")
            $table->string('resource_label')->nullable();
            // customer | auto | staff (glej App\Enums\ResourceSelection)
            $table->string('resource_selection', 20)->default('customer');

            $table->boolean('allow_guests')->default(false);
            $table->boolean('requires_approval')->default(false);

            // Okno za rezervacije: najmanj X minut in največ Y dni vnaprej
            $table->unsignedInteger('min_advance_minutes')->default(0);
            $table->unsignedInteger('max_advance_days')->default(90);
            // Stranka lahko odpove najkasneje X ur pred začetkom (null = kadarkoli)
            $table->unsignedInteger('cancellation_hours')->nullable()->default(24);

            // Storitve in urni najem: razmik med možnimi začetki ter omejitve trajanja
            $table->unsignedSmallInteger('slot_interval_minutes')->default(30);
            $table->unsignedInteger('min_duration_minutes')->nullable();
            $table->unsignedInteger('max_duration_minutes')->nullable();

            // Dnevi/nočitve: enota, prihod/odhod in omejitve dolžine
            $table->string('nightly_unit', 10)->default('night'); // night | day
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->unsignedSmallInteger('min_units')->default(1);
            $table->unsignedSmallInteger('max_units')->nullable();

            // Koliko mest (oseb) lahko zasede ena rezervacija
            $table->unsignedSmallInteger('max_quantity_per_booking')->default(1);

            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_types');
    }
};
