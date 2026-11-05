<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PieceRate;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Rate chart: what a piece-rate worker earns per piece of each finished
 * item at Sewing, Finishing and Blister/Poly. All rates are saved from one
 * grid; a blank cell means the item isn't paid by the piece at that stage.
 */
class PieceRateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:hrm.edit|hrm.approve', only: ['index']),
            new Middleware('permission:hrm.edit', only: ['update']),
        ];
    }

    public function index()
    {
        $products = Product::where('type', 'finished_good')->where('status', true)->orderBy('name')->get(['id', 'name', 'sku']);
        $rates = PieceRate::all()->groupBy('product_id')->map(fn ($r) => $r->pluck('rate', 'stage'));

        return view('admin.piece-rates.index', compact('products', 'rates'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'rates' => ['nullable', 'array'],
            'rates.*' => ['array'],
            'rates.*.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $productIds = Product::where('type', 'finished_good')->pluck('id')->flip();

        DB::transaction(function () use ($validated, $productIds) {
            foreach ($validated['rates'] ?? [] as $productId => $stages) {
                if (! isset($productIds[$productId])) {
                    continue;
                }

                foreach (PieceRate::STAGES as $stage) {
                    $rate = $stages[$stage] ?? null;

                    if ($rate === null || $rate === '') {
                        PieceRate::where('product_id', $productId)->where('stage', $stage)->delete();
                    } else {
                        PieceRate::updateOrCreate(['product_id' => $productId, 'stage' => $stage], ['rate' => $rate]);
                    }
                }
            }
        });

        return back()->with('success', 'Piece rates saved.');
    }
}
