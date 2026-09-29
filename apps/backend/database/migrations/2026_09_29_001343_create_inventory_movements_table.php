<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            $table->integer('quantity');
            $table->integer('stock_after');
            $table->string('reason', 500);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['product_id', 'id']);
            $table->index('user_id');
        });

        DB::statement(
            'ALTER TABLE inventory_movements
             ADD CONSTRAINT inventory_movements_quantity_non_zero
             CHECK (quantity <> 0)'
        );

        DB::statement(
            'ALTER TABLE inventory_movements
             ADD CONSTRAINT inventory_movements_stock_after_non_negative
             CHECK (stock_after >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
