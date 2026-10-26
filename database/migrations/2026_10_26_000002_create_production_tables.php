<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per production lot — a single finished item (product or
        // variant) made at one Site (the Factory). See
        // docs/garments-production.md for the stage flow.
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('production_no')->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('planned_quantity', 14, 4)->nullable();
            $table->date('start_date');

            // See App\Models\ProductionOrder::STATUSES. in_progress ->
            // completed, never back.
            $table->string('status')->default('in_progress');

            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            // WIP value left on the lot when it was closed (pieces cut but
            // never finished), written off to production_wastage_expense.
            $table->decimal('wastage_value', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });

        // Each time pieces move through a stage: N pcs cut, N pcs sewn,
        // N pcs finished. Append-only, same as stock_movements.
        Schema::create('production_stage_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            // See App\Models\ProductionOrder::STAGES.
            $table->string('stage');
            $table->decimal('quantity', 14, 4);
            $table->date('entry_date');
            // Only on finishing entries: the per-piece cost the finished
            // goods were stocked at (WIP value ÷ pieces still in WIP).
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['production_order_id', 'stage']);
        });

        // Raw material (fabric, thread, poly bags...) issued from stock to a
        // lot, always alongside a stage entry.
        Schema::create('production_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_stage_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            // Weighted-average cost at the moment of issue.
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_materials');
        Schema::dropIfExists('production_stage_entries');
        Schema::dropIfExists('production_orders');
    }
};
