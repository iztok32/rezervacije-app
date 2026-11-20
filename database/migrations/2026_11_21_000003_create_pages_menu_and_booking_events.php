<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vsebinske strani, javni meni (glava, noga) in zgodovina rezervacije.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug', 120)->unique();
            $table->longText('body')->nullable(); // Markdown
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location', 10); // header | footer
            $table->string('kind', 10); // page | offer | link
            $table->string('label')->nullable(); // prazno = naslov strani / ime ponudbe
            $table->foreignId('page_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('resource_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('url', 500)->nullable();
            $table->string('visibility', 10)->default('all'); // all | guests | users
            $table->boolean('new_tab')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['location', 'display_order']);
        });

        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 30);
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['booking_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('pages');
    }
};
