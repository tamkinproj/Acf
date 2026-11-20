<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Replaces the old numeric role_id: the role is a name (see config/roles.php).
            $table->string('role', 40)->default('user')->after('password');
            $table->unsignedBigInteger('foundation_id')->nullable()->after('role');
            $table->unsignedBigInteger('department_id')->nullable()->after('foundation_id');
            $table->unsignedBigInteger('guardian_id')->nullable()->after('department_id');
            $table->string('phone', 40)->nullable()->after('guardian_id');
            $table->boolean('active')->default(true)->after('phone');
            $table->json('preferences')->nullable()->after('active');

            $table->index('role');
            $table->index('foundation_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['foundation_id']);
            $table->dropColumn(['role', 'foundation_id', 'department_id', 'guardian_id', 'phone', 'active', 'preferences']);
        });
    }
};
