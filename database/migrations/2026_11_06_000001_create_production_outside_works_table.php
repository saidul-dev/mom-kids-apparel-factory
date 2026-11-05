<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Work a third party does on a lot before it is finished —
        // embroidery, print, wash... Its cost goes into the lot's WIP (so
        // into the finished pieces' cost) and is owed to the vendor. See
        // docs/garments-production.md.
        Schema::create('production_outside_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->string('work_type');
            // Pieces sent out and the price per piece — both optional; when
            // a price is given the amount is pieces × price.
            $table->decimal('quantity', 14, 4)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('entry_date');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_outside_works');
    }
};
