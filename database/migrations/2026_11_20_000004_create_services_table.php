<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storitev s trajanjem (način "service"): npr. moško striženje 30 min, barvanje 90 min.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes');
            // Čas za pripravo/čiščenje po storitvi, ko vir še ni prost
            $table->unsignedInteger('buffer_minutes')->default(0);
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Kateri viri (izvajalci) opravljajo storitev; brez zapisov = vsi viri tipa
        Schema::create('resource_service', function (Blueprint $table) {
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['resource_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_service');
        Schema::dropIfExists('services');
    }
};
