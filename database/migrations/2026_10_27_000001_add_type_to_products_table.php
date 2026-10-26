<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // See App\Models\Product::TYPES — raw_material (fabric, thread,
            // poly bags…) is issued to production; finished_good is what
            // production makes and sales sell. No BOM: production starts
            // from whatever fabric is issued to it.
            $table->string('type')->default('finished_good')->after('name');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
