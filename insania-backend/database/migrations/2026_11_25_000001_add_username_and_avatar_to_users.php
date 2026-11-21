<?php

use App\Support\Usernames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Usernames for signing in (email still works), and a profile picture per account (private file, shown across the app). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 30)->nullable()->unique();
            $t->string('avatar_path', 190)->nullable();
            $t->unsignedBigInteger('avatar_v')->nullable();
        });

        // every existing account gets a username from the first part of its email (made unique if two share it)
        $used = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email', 'name']) as $u) {
            $name = Usernames::suggest($u->email ?: $u->name, fn ($c) => isset($used[$c]));
            $used[$name] = true;
            DB::table('users')->where('id', $u->id)->update(['username' => $name]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['username']);
            $t->dropColumn(['username', 'avatar_path', 'avatar_v']);
        });
    }
};
