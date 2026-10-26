# Garments Production

How the mini garments factory runs end to end in this app, and which
stock/ledger entries each step posts. Builds on the `stock_movements`
ledger ([stock-movements.md](stock-movements.md)) and `LedgerService`
([accounting-foundation.md](accounting-foundation.md)).

```
Purchase fabric ──► Factory stock
                       │  (issued as material)
                       ▼
   Production lot: Cutting ──► Sewing ──► Finishing (iron + poly)
                                              │
                                              ▼
                                  Finished stock at Factory
                                   │                    │
                    Sales Order (deliver)     Stock Transfer ──► Outlet stock
                                                                    │
                                                    Sales Order (deliver) from Outlet
```

Sites: the Factory and the wholesale Outlet are ordinary `sites` rows
(`type` = `Factory` / `Outlet`). Every screen below picks its Site
explicitly, the same as Stock Transfer.

## 1. Purchase (built)

Route `admin/purchases*` (`purchases.*`), `PurchaseController`,
`inventory.view` / `inventory.create`.

Pick the receiving Site → Supplier, date, optional supplier invoice no. →
cart of items with quantity and unit cost → optional header discount.

On save (no draft step):
- one `purchase` stock movement per line at the Site. Its `unit_cost` is
  the entered cost less the line's pro-rata share of the discount, so the
  item's weighted-average cost reflects what was actually owed;
- voucher `PUR-…`: **Dr Inventory / Cr Accounts Payable** (supplier).

The supplier is paid later from Accounts → Payments.

## 2. Production lot (built)

Route `admin/production-orders*` (`production-orders.*`),
`ProductionOrderController`. Tables: `production_orders`,
`production_stage_entries`, `production_materials`.

Each production has a tracking number (`PRD-000001`, searchable on the
list) and is **one item in one size** (product or variant) at one Site —
another size is another production.

**Creating a production is the cutting step.** Pick the Factory → item &
size → the fabric used → pieces cut. Fabric is an ordinary inventory item
entered in its own stock unit, whatever that is (Roll, Gaj…): e.g. Silk
Fabric with 20 Roll in stock, 5 Roll used, 500 pcs cut → 15 Roll left.
Several fabrics can be used for one production. The page shows fabric per
piece and pieces per unit when all fabrics share one unit.

After that, progress is recorded as stage entries on the production page:

| Stage | Takes pieces from | Can record at most |
|---|---|---|
| Cutting | — (recorded on create; "Extra Cutting" if more fabric is issued later) | anything |
| Sewing | Cutting | cut − sewn |
| Finishing (iron & poly) | Sewing | sewn − finished |

Example: **500** cut → **300** sewn → **200** finished. The production
page shows a stage tracker (Cutting → Sewing → Finishing → Finished
Stock), a bar of where the 500 pcs are now (200 finished, 100 waiting for
finishing, 200 waiting for sewing), the fabric used and a timeline of
every entry.

**Materials.** Fabric is issued on create; any later stage entry can
also issue material from the Site (thread at sewing, poly bags at
finishing). Each
line posts a `production_consumption` out movement at the item's current
average cost (balance-checked, never negative) and the entry's total goes
**Dr Work in Progress / Cr Inventory** (voucher `PRD-…`).

**Finishing** is the only stage that touches finished stock: it posts a
`production` in movement of the lot's item at the Site. Cost per piece =
the lot's WIP value ÷ pieces still in WIP (cut but not yet finished), so
the cost of every material issued is spread over the pieces actually
produced; the last pieces out take whatever WIP is left so rounding never
strands a balance. Ledger: **Dr Inventory / Cr Work in Progress**.

**Complete Lot** (`inventory.approve`) closes it. Pieces still on the
floor are treated as wastage (cutting waste, rejects): their remaining
WIP value is posted **Dr Production Wastage / Cr Work in Progress** and
stored on the lot as `wastage_value`. A completed lot accepts no more
entries.

New ledger accounts (`LedgerAccountSeeder`): `work_in_progress`
(inventory group, shows on the Balance Sheet) and
`production_wastage_expense` (shows in P&L operating expenses). On an
existing database run `php artisan db:seed --class=LedgerAccountSeeder`.

## 3. Sales Order (built)

Route `admin/sales-orders*` (`sales-orders.*`), `SalesOrderController`,
new `sales.view/create/edit/approve` permissions (Admin gets them
automatically, Manager is granted them; re-run `RolePermissionSeeder`).

Pick the Site the order ships from — the Factory for direct customer
orders, or the Outlet for wholesale — then customer, items, prices,
discount. Saving only records the order (`pending`); an order can be
taken before production has finished.

**Deliver** (`sales.approve`) checks every line is in stock at the Site,
then:
- one `sale` out movement per line at its current average cost (stored
  on the line as `unit_cost`);
- voucher `SAL-…`: **Dr Accounts Receivable** (customer) + **Dr Discount
  Allowed** / **Cr Sales Revenue**;
- voucher `SAL-…`: **Dr Cost of Goods Sold / Cr Inventory**.

**Cancel** (`sales.edit`) is only for pending orders and has no stock or
ledger effect. Customer payment is received from Accounts → Collections.

## 4. Factory → Outlet

Uses the existing Stock Transfer (dispatch → receive) — finished goods
carry their production cost through to the Outlet unchanged.

## Not covered yet

- Rejects per stage aren't recorded separately — they surface as wastage
  when the lot is completed.
- Labour/overhead isn't added to finished-goods cost (materials only).
- Partial delivery and sales returns.
