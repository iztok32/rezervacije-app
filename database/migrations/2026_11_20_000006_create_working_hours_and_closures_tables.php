<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Delovni čas za storitve in urni najem. Zapisi z resource_id = null so privzeti
         * delovni čas tipa; vir z lastnimi zapisi uporablja samo svoje.
         * Več zapisov za isti dan pomeni več intervalov (npr. 8–12 in 13–17).
         */
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 1 = ponedeljek … 7 = nedelja
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['resource_type_id', 'resource_id', 'weekday']);
        });

        /*
         * Zaprtja in odsotnosti (prazniki, dopust, servis avta …). Obseg:
         * resource_id → samo vir; resource_type_id → cel tip; oboje null → celotno podjetje.
         */
        Schema::create('closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closures');
        Schema::dropIfExists('working_hours');
    }
};
