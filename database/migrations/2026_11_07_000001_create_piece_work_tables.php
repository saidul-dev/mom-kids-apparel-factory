<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a piece-rate worker earns per piece of an item at a stage,
        // e.g. Girls Frock / Sewing = 12. A size paid differently is its
        // own item. See docs/garments-production.md.
        Schema::create('piece_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // See App\Models\PieceRate::STAGES.
            $table->string('stage');
            $table->decimal('rate', 10, 2);
            $table->timestamps();

            $table->unique(['product_id', 'stage']);
        });

        // Who did the pieces of a production stage entry. The rate is
        // copied in at entry time so a later rate change never rewrites
        // what was already earned.
        Schema::create('production_piece_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_stage_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('stage');
            $table->decimal('quantity', 14, 4);
            $table->decimal('rate', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->date('entry_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'entry_date']);
        });

        // Pieces a piece-rate employee was paid for in the run's month.
        Schema::table('payroll_run_items', function (Blueprint $table) {
            $table->decimal('piece_quantity', 14, 4)->nullable()->after('conveyance');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table) {
            $table->dropColumn('piece_quantity');
        });
        Schema::dropIfExists('production_piece_works');
        Schema::dropIfExists('piece_rates');
    }
};
