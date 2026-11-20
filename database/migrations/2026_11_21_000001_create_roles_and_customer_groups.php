<?php

use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vloge osebja (nastavljive pravice) in skupine strank. Zamenja stolpec users.role
 * (admin/staff/customer) z users.role_id; stranka nima vloge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->nullable()->unique(); // sistemske vloge (admin, reception, provider)
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->boolean('is_admin')->default(false); // vse pravice, ni urejljiva
            $table->json('permissions');
            $table->string('booking_scope', 10)->default('all'); // all | own
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        // Omejitev vloge na izbrane tipe virov (prazno = vsi)
        Schema::create('resource_type_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'resource_type_id']);
        });

        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('customer_group_user', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_group_id', 'user_id']);
        });

        // Dostop do ponudbe: prazno = vsi, sicer samo člani izbranih skupin
        Schema::create('customer_group_resource_type', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_group_id', 'resource_type_id']);
        });

        Schema::create('customer_group_timeslot', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timeslot_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_group_id', 'timeslot_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('phone')->constrained()->restrictOnDelete();
        });

        $now = now();
        $roles = [
            'admin' => [
                'name' => __('Administrator'),
                'description' => __('Full access to everything.'),
                'is_admin' => true,
                'permissions' => [],
                'booking_scope' => 'all',
            ],
            'reception' => [
                'name' => __('Reception'),
                'description' => __('Manages all bookings and books on behalf of customers.'),
                'is_admin' => false,
                'permissions' => [Permission::BookingsView->value, Permission::BookingsManage->value, Permission::BookingsCreate->value, Permission::ClosuresManage->value],
                'booking_scope' => 'all',
            ],
            'provider' => [
                'name' => __('Provider'),
                'description' => __('Sees and manages bookings for own resources (e.g. a hairdresser or instructor).'),
                'is_admin' => false,
                'permissions' => [Permission::BookingsView->value, Permission::BookingsManage->value],
                'booking_scope' => 'own',
            ],
        ];

        $ids = [];
        $order = 0;
        foreach ($roles as $slug => $role) {
            $ids[$slug] = DB::table('roles')->insertGetId([
                ...$role,
                'slug' => $slug,
                'permissions' => json_encode($role['permissions']),
                'display_order' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('users')->where('role', 'admin')->update(['role_id' => $ids['admin']]);
        DB::table('users')->where('role', 'staff')->update(['role_id' => $ids['reception']]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->index();
        });

        $admin = DB::table('roles')->where('is_admin', true)->pluck('id');
        DB::table('users')->whereIn('role_id', $admin)->update(['role' => 'admin']);
        DB::table('users')->whereNotNull('role_id')->whereNotIn('role_id', $admin)->update(['role' => 'staff']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('customer_group_timeslot');
        Schema::dropIfExists('customer_group_resource_type');
        Schema::dropIfExists('customer_group_user');
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('resource_type_role');
        Schema::dropIfExists('roles');
    }
};
