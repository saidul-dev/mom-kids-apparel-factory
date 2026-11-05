<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection as CollectionModel;
use App\Models\Expense;
use App\Models\LedgerAccount;
use App\Models\Party;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionPieceWork;
use App\Models\ProductionStageEntry;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;

/**
 * Owner's Daily Digest (README M9) — every stat here is scoped to the
 * user's Current Site (see SetCurrentSite middleware), except Collections
 * and Expenses: they carry no site_id (pure accounting entries, not tied
 * to a warehouse), so that side of the picture is always company-wide
 * regardless of the site selector.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $siteId = Auth::user()->current_site_id;

        $todayCollection = (float) CollectionModel::whereDate('collection_date', today())->sum('amount');
        $todayExpense = (float) Expense::whereDate('expense_date', today())->sum('amount');

        $totalReceivable = $this->accountBalance('accounts_receivable', $siteId);

        [$lowStockItems, $lowStockCount, $criticalCount] = $this->lowStock($siteId);

        [$chartLabels, $collectionSeries, $expenseSeries] = $this->weeklyChart();

        $topOverdueCustomers = Party::where('is_customer', true)->where('status', true)->get()
            ->map(fn (Party $party) => ['id' => $party->id, 'name' => $party->name, 'due' => $party->receivableBalance()])
            ->filter(fn (array $row) => $row['due'] > 0)
            ->sortByDesc('due')
            ->take(6)
            ->values();

        $recentCollections = CollectionModel::with('party')
            ->orderByDesc('collection_date')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        $user = Auth::user();
        $employee = $user->employee;

        // Pieces only, no money: for anyone running production, and for
        // workers so they see how the floor is doing.
        $production = $user->can('production.view') || $employee
            ? ProductionOrder::floorSummary($siteId)
            : null;

        // Latest stage entries on the floor, for the phone home screen.
        $floorActivity = $user->can('production.view')
            ? ProductionStageEntry::with(['productionOrder.product', 'productionOrder.productVariant.attributeValues'])
                ->when($siteId, fn ($q) => $q->whereHas('productionOrder', fn ($q) => $q->where('site_id', $siteId)))
                ->latest('id')
                ->limit(5)
                ->get()
            : collect();

        // A worker's own piece work: today, this month, latest entries.
        $myWork = null;
        if ($employee && ($employee->salaryStructure?->mode === 'piece_rate' || $employee->pieceWorks()->exists())) {
            $mine = fn () => ProductionPieceWork::where('employee_id', $employee->id);
            $sum = fn ($q) => $q->selectRaw('COALESCE(SUM(quantity), 0) as pcs, COALESCE(SUM(amount), 0) as amount')->first();

            $myWork = [
                'today' => $sum($mine()->whereDate('entry_date', today())),
                'month' => $sum($mine()->whereDate('entry_date', '>=', now()->startOfMonth())->whereDate('entry_date', '<=', today())),
                'recent' => $mine()->with(['productionOrder.product', 'productionOrder.productVariant.attributeValues'])
                    ->orderByDesc('entry_date')->orderByDesc('id')->limit(8)->get(),
            ];
        }

        return view('dashboard', compact(
            'todayCollection', 'todayExpense', 'totalReceivable',
            'lowStockItems', 'lowStockCount', 'criticalCount',
            'chartLabels', 'collectionSeries', 'expenseSeries',
            'topOverdueCustomers', 'recentCollections',
            'production', 'myWork', 'floorActivity',
        ));
    }

    /**
     * Signed balance of a system ledger account, optionally restricted to
     * one Site via the parent ledger_transactions row — mirrors
     * LedgerAccount::balance() but adds the site filter that method doesn't
     * have. Opening-balance postings carry no site_id, so they naturally
     * drop out once a specific Site is selected — acceptable since an
     * opening balance predates any site-level activity anyway.
     */
    protected function accountBalance(string $code, ?int $siteId): float
    {
        $account = LedgerAccount::where('code', $code)->first();

        if (! $account) {
            return 0.0;
        }

        $raw = (float) $account->lines()
            ->when($siteId, fn ($q) => $q->whereHas('transaction', fn ($qt) => $qt->where('site_id', $siteId)))
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as bal')
            ->value('bal');

        return $account->nature === 'credit' ? -$raw : $raw;
    }

    /**
     * Every simple product / variant tracked for reorder (reorder_level > 0)
     * whose current Site balance has dropped to or below that level — same
     * "balance <= reorder_level" convention as StockReportController. A
     * variant has no reorder_level of its own; it inherits its parent
     * product's.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: int, 2: int} [rows to display, total low-stock count, out-of-stock count within it]
     */
    protected function lowStock(?int $siteId): array
    {
        $products = Product::with('stockUnit')
            ->where('status', true)->where('has_variants', false)->where('reorder_level', '>', 0)->get();

        $variantProducts = Product::with(['stockUnit', 'variants.attributeValues'])
            ->where('status', true)->where('has_variants', true)->where('reorder_level', '>', 0)
            ->get()
            ->each(fn (Product $p) => $p->setRelation('variants', $p->variants->where('status', true)->values()));

        $productIds = $products->pluck('id');
        $variantIds = $variantProducts->flatMap(fn (Product $p) => $p->variants->pluck('id'));

        $productBalances = StockMovement::whereIn('product_id', $productIds)->whereNull('product_variant_id')
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->selectRaw("product_id, SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as balance")
            ->groupBy('product_id')->pluck('balance', 'product_id');

        $variantBalances = StockMovement::whereIn('product_variant_id', $variantIds)
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->selectRaw("product_variant_id, SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as balance")
            ->groupBy('product_variant_id')->pluck('balance', 'product_variant_id');

        $rows = collect();

        foreach ($products as $product) {
            $balance = (float) ($productBalances[$product->id] ?? 0);

            if ($balance <= $product->reorder_level) {
                $rows->push((object) [
                    'name' => $product->name,
                    'unit' => $product->stockUnit?->short_name,
                    'balance' => $balance,
                    'reorder_level' => $product->reorder_level,
                ]);
            }
        }

        foreach ($variantProducts as $product) {
            foreach ($product->variants as $variant) {
                $balance = (float) ($variantBalances[$variant->id] ?? 0);

                if ($balance <= $product->reorder_level) {
                    $rows->push((object) [
                        'name' => "{$product->name} — {$variant->label}",
                        'unit' => $product->stockUnit?->short_name,
                        'balance' => $balance,
                        'reorder_level' => $product->reorder_level,
                    ]);
                }
            }
        }

        $rows = $rows->sortBy('balance')->values();

        return [$rows->take(4), $rows->count(), $rows->where('balance', '<=', 0)->count()];
    }

    /**
     * Last 7 days (today inclusive) of Collections received vs Expenses
     * paid, for the "Collection vs Expense" chart. Both are company-wide
     * (no site_id), so there's no site filter to apply here.
     *
     * @return array{0: array<string>, 1: array<float>, 2: array<float>}
     */
    protected function weeklyChart(): array
    {
        $labels = [];
        $collectionSeries = [];
        $expenseSeries = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $labels[] = $date->format('d M');

            $collectionSeries[] = (float) CollectionModel::whereDate('collection_date', $date)->sum('amount');
            $expenseSeries[] = (float) Expense::whereDate('expense_date', $date)->sum('amount');
        }

        return [$labels, $collectionSeries, $expenseSeries];
    }
}
