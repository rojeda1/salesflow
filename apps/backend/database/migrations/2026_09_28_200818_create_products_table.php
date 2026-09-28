<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE products
             ADD CONSTRAINT products_price_non_negative
             CHECK (price >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
