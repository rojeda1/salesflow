<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock')->default(0);
        });

        DB::statement(
            'ALTER TABLE products
             ADD CONSTRAINT products_stock_non_negative
             CHECK (stock >= 0)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE products
             DROP CONSTRAINT products_stock_non_negative'
        );

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('stock');
        });
    }
};
