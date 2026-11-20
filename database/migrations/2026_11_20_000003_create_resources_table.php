<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vir, ki ga je mogoče rezervirati: konj, frizer, avto, hiška, soba, igrišče …
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('color', 20)->nullable();

            // Koliko rezervacij lahko vir hkrati sprejme (običajno 1)
            $table->unsignedSmallInteger('capacity')->default(1);
            // Največ oseb na rezervacijo (sobe, hiške, avtomobili)
            $table->unsignedSmallInteger('max_guests')->nullable();
            // Cena na enoto (noč/dan/uro) pri nočitvah in urnem najemu
            $table->decimal('price', 10, 2)->nullable();

            // Zaposleni, ki je ta vir (npr. frizer) — vidi svoje rezervacije
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Poljubne lastnosti za prikaz strankam: [{"label": "Letnik", "value": "2015"}]
            $table->json('properties')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
