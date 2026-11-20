<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Obveščanje strank: opomniki pred rezervacijo, ročna sporočila (e-pošta, nabiralnik v portalu)
 * z dnevnikom pošiljanja po prejemnikih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_types', function (Blueprint $table) {
            // Opomnik X ur pred začetkom; null = brez opomnika
            $table->unsignedSmallInteger('reminder_hours')->nullable()->default(24)->after('cancellation_hours');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('cancelled_at');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->json('channels'); // mail, portal
            $table->string('audience', 500)->nullable(); // opis prejemnikov za dnevnik
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('mail_status', 10)->nullable(); // pending | sent | failed; null = brez e-pošte
            $table->string('error', 500)->nullable();
            $table->timestamp('mailed_at')->nullable();
            $table->boolean('in_portal')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'in_portal', 'read_at']);
            $table->index(['message_id', 'mail_status']);
        });

        // Recepcija sme pošiljati sporočila strankam
        $reception = DB::table('roles')->where('slug', 'reception')->first();
        if ($reception) {
            $permissions = json_decode($reception->permissions, true) ?: [];
            if (! in_array('messages.send', $permissions, true)) {
                $permissions[] = 'messages.send';
                DB::table('roles')->where('id', $reception->id)->update(['permissions' => json_encode($permissions)]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_recipients');
        Schema::dropIfExists('messages');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });

        Schema::table('resource_types', function (Blueprint $table) {
            $table->dropColumn('reminder_hours');
        });
    }
};
