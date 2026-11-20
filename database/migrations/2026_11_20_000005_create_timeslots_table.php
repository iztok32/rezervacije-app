<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ponavljajoč se termin s kapaciteto (način "timeslot"): npr. "Jahanje začetniki",
 * ob torkih in četrtkih 17:00–18:00, 6 mest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeslots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // ISO dnevi v tednu (1 = ponedeljek … 7 = nedelja)
            $table->json('weekdays');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            // null = brez omejitve
            $table->unsignedInteger('capacity')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Viri, ki so na voljo v terminu (npr. konji); brez zapisov = vsi viri tipa
        Schema::create('resource_timeslot', function (Blueprint $table) {
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timeslot_id')->constrained()->cascadeOnDelete();
            $table->primary(['resource_id', 'timeslot_id']);
        });

        // Zaposleni, ki vodijo termin (npr. učitelji)
        Schema::create('timeslot_user', function (Blueprint $table) {
            $table->foreignId('timeslot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['timeslot_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeslot_user');
        Schema::dropIfExists('resource_timeslot');
        Schema::dropIfExists('timeslots');
    }
};
