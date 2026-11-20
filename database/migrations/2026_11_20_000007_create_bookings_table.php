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
            // Kratka oznaka za stranko (npr. "R7K3QX"), prikazana v e-pošti
            $table->string('reference', 12)->unique();
            // Skrivni žeton za povezavo, s katero gost brez računa upravlja rezervacijo
            $table->string('access_token', 64);

            $table->foreignId('resource_type_id')->constrained();
            $table->foreignId('resource_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('timeslot_id')->nullable()->constrained()->nullOnDelete();

            // Prijavljen uporabnik ali gost; podatki o stranki so vedno shranjeni na rezervaciji
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 40)->nullable();
            // Jezik obvestil za stranko
            $table->string('locale', 5)->nullable();

            // Lokalni čas podjetja (config app.timezone)
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // Do kdaj je vir zaseden (ends_at + čas za pripravo pri storitvah)
            $table->dateTime('blocks_until');
            $table->unsignedSmallInteger('quantity')->default(1);

            // pending | confirmed | cancelled | rejected | completed | no_show
            $table->string('status', 20)->default('confirmed');
            $table->decimal('total_price', 10, 2)->nullable();

            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'starts_at', 'blocks_until']);
            $table->index(['timeslot_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
