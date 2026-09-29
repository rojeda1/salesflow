<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('seller');
        });

        DB::statement(
            "ALTER TABLE users
             ADD CONSTRAINT users_role_valid
             CHECK (role IN ('admin', 'supervisor', 'seller'))"
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE users DROP CONSTRAINT users_role_valid'
        );

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
