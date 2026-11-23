<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sharjah Charity International now has its logo; partners made by the first partners update had none.
        DB::table('partners')->where('code', 'sharjah_charity')->whereNull('logo')->update(['logo' => 'assets/partners/sharjah_charity.jpg']);
    }

    public function down(): void
    {
        DB::table('partners')->where('code', 'sharjah_charity')->where('logo', 'assets/partners/sharjah_charity.jpg')->update(['logo' => null]);
    }
};
