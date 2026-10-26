<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_no')->unique();
            // The Site the goods are received into (typically the Factory
            // for fabric/raw material).
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->date('purchase_date');
            $table->string('supplier_invoice_no')->nullable();

            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total', 14, 2);

            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['site_id', 'purchase_date']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();

            $table->decimal('quantity', 14, 4);
            // Invoice price as entered. The stock movement's unit_cost is
            // this less the item's share of the header discount, so
            // inventory is valued at what was actually owed.
            $table->decimal('unit_cost', 14, 4);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
